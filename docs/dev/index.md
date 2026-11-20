# Settings : contrat développeur

Module natif de stockage de paramètres applicatifs en base de données. Adaptation ciblée du module XCore Settings 1.1.35. Le module ne modifie ni le bootstrap Symfony, ni les fichiers `.env`, ni les transports de mails.

## Stockage et migrations

L'entité `SymSvel\Module\Settings\Entity\Setting` possède une clé unique (`setting_key`, 190 caractères) et une valeur texte nullable. La table `symsvel_settings` appartient exclusivement à ce module, conformément à `module.json`.

`Migration/Version20200101000400.php` utilise Doctrine Schema (aucun SQL spécifique MySQL). La compilation, l'ordonnancement et l'exécution relèvent de SymSvel Tooling. La version source Settings a été corrigée avant validation de son premier CT : l'ancien nom `Version20200101000000.php` entrait en collision avec la migration native Admins. Le nouveau nom `Version20200101000400.php` est unique parmi les migrations natives vérifiées (Admins, Roles, ACLs, Users, Settings). Une fois une migration intégrée à un projet, sa version et son contenu deviennent immuables ; ne pas renommer une source déjà appliquée.

## Convention architecturale des clés (ADR 0001)

La clé canonique est `module.parametre`. Le module consommateur peut déclarer `api_key` ; le registre la qualifie en `brevo.api_key` lorsque l'inventaire Composer vérifié associe son fournisseur au module `brevo`. Une clé déjà correctement qualifiée est conservée ; un préfixe étranger et les doublons après qualification sont rejetés. Le stockage conserve une seule colonne `setting_key` (190 caractères), sans nouvelle migration.

Le compiler pass `SettingProviderOwnershipPass` exploite `backend/.symsvel/modules.json` publié par Tooling, associe chaque service fournisseur à un seul chemin de module installé et injecte la carte des propriétaires dans `SettingRegistry`. Il ajoute l'attribut de tag `settings_provider_id` à chaque fournisseur, utilisé par `AutowireIterator(indexAttribute: 'settings_provider_id')` pour conserver les identifiants de services comme clés de l'itérateur. Cette association se fait lors de la compilation du conteneur, jamais par découverte de modules pendant une requête.

Sans inventaire, le registre conserve son comportement historique pour les tests isolés. Les clés historiques déjà persistées sans préfixe nécessitent une migration explicite, sans conversion silencieuse. Les tests du compiler pass et du registre vérifient les contrats séparément ; le test fonctionnel d'un fournisseur tiers dans un projet installé reste nécessaire avant clôture du chantier.


## Cycle de vie des paramètres (ADR 0002)

Le dictionnaire cible `backend/.symsvel/modules-params.json` est un inventaire de définitions synchronisées, sans valeurs. Le service pur `SettingInventoryDiff` compare deux états par module et produit les clés qualifiées ajoutées, supprimées ou dont les métadonnées changent. Il valide les identifiants et garantit un résultat déterministe et idempotent. **Ce comparateur n'écrit ni la BDD ni le JSON** : la synchronisation transactionnelle par Tooling, sa commande et le CT XT restent à implémenter et à valider.

`SettingInventory::fromRegistry()` projette les définitions qualifiées en structure `schema: 1` / `modules`, triée par module et clé, avec les métadonnées de présentation et de validation mais sans valeur persistée. Les deux services sont enregistrés par `SettingsBundle`. La projection refuse les clés sans propriétaire valide. Le fichier n'est pas encore publié automatiquement.

`SettingInventoryStore` lit et publie atomiquement `backend/.symsvel/modules-params.json` (écriture temporaire puis renommage), refuse les destinations symboliques et ne réécrit pas un contenu inchangé. L'absence de fichier représente un inventaire initial vide. **Le store n'est pas encore appelé par Tooling** : aucune synchronisation BDD ni coordination transactionnelle n'est activée à ce stade. Le fichier ne doit être publié qu'après synchronisation BDD réussie et selon la politique de reprise de l'ADR 0002.

## Synchronisation explicite (implémentation en cours)

La commande Symfony interne `symsvel:settings:sync` construit les définitions qualifiées du registre, compare l'inventaire précédent, crée les lignes manquantes avec valeur `NULL`, supprime les clés retirées et conserve les valeurs des clés inchangées. Une modification de `type` ou `secret` avec valeur déjà renseignée est refusée. Elle publie ensuite `backend/.symsvel/modules-params.json`. Cette commande est destinée à Tooling, pas aux développeurs finaux. La cohérence entre commit BDD et publication fichier, ainsi que le parcours de Setup initial, nécessitent encore validation et reprise robuste. Ne pas considérer le CT terminé sans réinstallation réelle du module Mails.

La synchronisation tente de restaurer le dictionnaire antérieur si le commit BDD échoue après publication, y compris en supprimant le fichier lorsqu'il n'existait pas. Une restauration incomplète est signalée explicitement. Ce mécanisme n'est pas une transaction atomique distribuée entre filesystem et SGBD : les interruptions brutales et les garanties de reprise du lanceur Composer restent à tester.

Les tests du stockage couvrent aussi la restauration d'un dictionnaire antérieur après publication et le refus d'un inventaire de version de schéma inconnue. Ces tests unitaires ne remplacent pas un test de transaction Composer avec SGBD réel.

Un test de régression du différentiel simule la suppression du module Mails puis sa réinstallation : seules ses trois clés sont retirées puis réintroduites, les clés d'un autre module restent intactes et une nouvelle synchronisation identique ne produit aucun changement. Il s'agit d'un test du différentiel, non d'une désinstallation Composer réelle.

### Visibilité des tests ignorés

La CI affiche les raisons des tests PHPUnit ignorés avec `--display-skipped` (directement ou via le script Composer QA). Un test ignoré ne devient pas bloquant pour autant : les motifs sont examinés pour distinguer prérequis absents et couverture manquante.

## API d'administration BO des Settings (16/11/2026)

L'API `GET /apibo/settings` et `PUT /apibo/settings/{key}` utilise les services existants `SettingPresentation`, `SettingRegistry` et `SettingManager` : seules les valeurs sont modifiables, jamais les définitions. L'autorisation est déléguée au contrat `Acls\Access\BoAccessGate` implémenté par Admins pour éviter une dépendance cyclique Settings/Auth/Admins. Permissions requises : `settings:list` et `settings:update`. Un secret défini ne ressort jamais dans l'API. Les erreurs 401/403/400/404 et toutes les réponses portent `Cache-Control: no-store`. Pas de changement des migrations ni de l'inventaire des modules. Tests, CI et crash-test XT requis.

### Interface BO Settings (16/11/2026)

`bo/src/routes/settings/+page.svelte` utilise `currentBo()` et `boApi()` depuis le BO hôte, présente la définition de chaque paramètre par groupe et permet d'enregistrer les valeurs via `PUT /apibo/settings/{key}`. Les champs changent selon le type (`email`, `url`, booléen, texte, secret) ; l'API masque les secrets et l'UI ne réaffiche jamais une ancienne valeur secrète. Les libellés FR/EN et le regroupement sont testés dans `bo/tests/settings.test.ts`. CI de l'intégration BO et CT navigateur à confirmer.

### Tests HTTP BO Settings (16/11/2026)

`BoSettingsControllerTest` assemble le vrai registre Settings, `SettingManager`, Doctrine ORM/SQLite et un faux `BoAccessGate` pour éprouver le contrôle d'ACL. Il valide l'écriture, la lecture, le masquage des secrets, les refus 403, les clés inconnues et la validation de type email. Ce test ne remplace pas le CT de l'interface assemblée et de la configuration Mailpit.

### CI de l'écran Settings assemblé (16/11/2026)

Le job `bo-integration` clone le BO hôte `dev`, copie la route Settings et ses helpers comme Tooling, installe npm et vérifie formatage, TypeScript/Svelte, ESLint, build statique et tests unitaires du module. Ce test ne fait aucun appel HTTP réel ni accès à la BDD XT ; les crash-tests navigateur et la vérification des paramètres Mails restent requis.

### Correctif du banc Svelte BO Settings (16/11/2026)

L'assemblage CI conserve désormais un test Vitest colocataire du helper `presentation.ts` dans `bo/src/lib/modules/settings/presentation.test.ts`. Il est copié naturellement par Tooling et résout ses imports de manière identique dans le module et dans le BO hôte. L'ancien test `bo/tests/settings.test.ts` reste dans le module pour sa validation unitaire historique ; il n'est plus copié sous `src/routes` du BO, où ses chemins relatifs étaient erronés. La CI assemblée vérifie TypeScript, lint, build et tests.

### Déclaration de navigation BO (16/11/2026)

Le module fournit `bo/src/lib/modules/settings/navigation.ts`, qui décrit sa page `/settings` avec libellés FR/EN et sa permission `settings:list`. Le dashboard du BO hôte importe les déclarations des modules effectivement installés ; il n'affiche cette entrée que si `GET /apibo/settings` réussit via `boApi`, ce qui vérifie le droit côté PHP avant toute navigation. Aucune route Symfony centrale ni règle d'accès Svelte parallèle n'est créée. Le test `bo/tests/settings.test.ts` valide la déclaration, et la CI de l'assemblage BO doit confirmer l'import. CT XT après mise à jour du BO et des modules.


## Interface BO : accordéons par groupe de module (16/11/2026)

L'écran Settings reprend la propriété métier `group` des définitions fournies
par chaque module. Chaque groupe est un élément HTML natif `details/summary`
avec `name` commun : une section s'ouvre à la fois. Le premier groupe est
ouvert à l'arrivée ; les autres sont repliés. Les valeurs restent modifiables
et enregistrées individuellement comme auparavant, et les secrets masqués
ne sont jamais préremplis. Aucune requête ni règle d'accès n'est déplacée
vers le composant de présentation.

Validation : QA module + assemblage BO et crash-test XT (navigation au clavier,
changement de groupe, sauvegarde et rafraîchissement des alertes).


### Correction de la portée des accordéons Settings (16/11/2026)

Les accordéons sont organisés par **module propriétaire**, établi par le
préfixe de la clé qualifiée `<module>.<setting>` (par exemple `auth.*`,
`mails.*`). La propriété `group` de `SettingDefinition` demeure un groupe
de présentation indépendant, pas une preuve d'appartenance. `groupSettings`
est conservé pour les usages existants ; `settingsByModule` prépare les
accordéons sans modifier ni le registre ni la réponse API. Le test assure
qu'un même module reste dans un unique accordéon malgré plusieurs groupes.


## Déclaration des permissions au registre ACLs (16/11/2026)

Le module fournit désormais `SettingsPermissionProvider` avec le tag
`symsvel.acls.permission_provider`. Le catalogue expose uniquement les
actions BO réelles, sans duplications dans le Core ou dans ACLs. Le runtime
conserve les contrôles `BoAccessGate` déjà en place ; la déclaration
n'accorde aucun droit automatiquement. Pas de migration ni de changement
sur les routes. CI et CT XT requis.


## Choix contraints de valeurs dans Settings (16/11/2026)

`SettingDefinition(type: 'choice', choices: ['slug' => 'Libellé'])` permet à
un module de proposer des valeurs contrôlées par son propre code, sans ajouter
de règle métier dans Settings. Le registre qualifie la clé normalement et
préserve les choix lors de `withKey()`. `SettingManager::set()` refuse
côté PHP toute valeur absente des choix déclarés, même si une requête
`PUT /apibo/settings/...` est forgée. La valeur `NULL` reste possible
pour un paramètre facultatif ; une liste vide n'autorise aucune nouvelle
valeur. La présentation BO n'envoie que les identifiants et libellés,
jamais un DSN ni un secret. L'inventaire de Tooling contient les choix
d'une définition `choice` sans valeurs persistées.

Le BO rend un `select` HTML natif pour ce type, tandis que les champs
existants gardent leur comportement. Ce contrat reste générique et permet
à Mails d'exposer uniquement les transports nommés disponibles depuis
ses providers. Tests : validation de choix côté PHP, persistance SQLite,
métadonnées et formulaire assemblé. CT XT requis.


## Paramètres non modifiables après le Setup (16/11/2026)

`SettingDefinition(editable: false)` est une métadonnée contrôlée
par le code du module et non par le navigateur. `SettingPresentation`
et l'inventaire la publient sans inclure de secret ; le BO affiche la
valeur en lecture seule et ne propose plus de bouton Enregistrer.
`BoSettingsController` rejette les écritures forcées avec HTTP 409
`read_only_setting`. Les commandes locales de Setup conservent la
possibilité de renseigner la valeur via `SettingManager`, qui reste
responsable de la persistance. Le contrôle métier de non-changement
après initialisation reste dans le module propriétaire, pas dans
Settings. Utilisé par `languages.primary_language`.

## Fallback des libellés BO (17/11/2026)

Les libellés français sont la valeur de repli lorsque la langue d'interface n'a pas encore de traduction disponible ; l'anglais reste servi pour `en`. Les codes ISO enregistrés en base ne changent pas. Cette correction ne crée pas d'éditeur de traductions ni de lecteur de fichiers dynamiques ; le déploiement d'un tel mécanisme relève du chantier i18n dédié. Tests FR, EN et locale non traduite ajoutés ; CI et crash-test local à confirmer.


## Traductions BO du module (18/11/2026)

`bo/src/lib/translations/<lang>.ts` fournit les valeurs de référence de Settings, destinées à `bo/src/lib/translations/settings/<lang>.ts` dans l'hôte installé. Languages persiste les clés `bo.settings.<clé>` après migrations et protège les personnalisations administrateur. Tests et CT XT requis.
