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
