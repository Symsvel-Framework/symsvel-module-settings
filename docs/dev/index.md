# Settings : contrat développeur

Module natif de stockage de paramètres applicatifs en base de données. Adaptation ciblée du module XCore Settings 1.1.35. Le module ne modifie ni le bootstrap Symfony, ni les fichiers `.env`, ni les transports de mails.

## Stockage et migrations

L'entité `SymSvel\Module\Settings\Entity\Setting` possède une clé unique (`setting_key`, 190 caractères) et une valeur texte nullable. La table `symsvel_settings` appartient exclusivement à ce module, conformément à `module.json`.

`Migration/Version20200101000400.php` utilise Doctrine Schema (aucun SQL spécifique MySQL). La compilation, l'ordonnancement et l'exécution relèvent de SymSvel Tooling. La version source Settings a été corrigée avant validation de son premier CT : l'ancien nom `Version20200101000000.php` entrait en collision avec la migration native Admins. Le nouveau nom `Version20200101000400.php` est unique parmi les migrations natives vérifiées (Admins, Roles, ACLs, Users, Settings). Une fois une migration intégrée à un projet, sa version et son contenu deviennent immuables ; ne pas renommer une source déjà appliquée.

## Convention architecturale des clés (ADR acceptée)

La décision [ADR 0001](../adr/0001-setting-key-namespace.md) impose la clé canonique `module.parametre`. SymSvel devra préfixer automatiquement les clés courtes avec l'identifiant technique stable du module propriétaire, conserver les clés déjà correctement qualifiées et refuser les préfixes d'un autre module. Le stockage reste sur `setting_key` unique, sans migration. **Cette convention est actée mais sa qualification automatique n'est pas encore implémentée** : le registre actuel reçoit encore directement les clés déclarées. L'association entre fournisseurs et identifiants de modules sera raccordée au mécanisme existant après vérification des ADR et du code de chargement.

`SettingKeyQualifier::qualify($module, $key)` fournit désormais la transformation pure, à partir d'un slug de module **déjà vérifié** : ajout du préfixe aux clés courtes, conservation des clés qualifiées appartenant au module, refus des préfixes étrangers, des noms invalides et des clés dépassant 190 caractères. Les tests unitaires couvrent également la limite exacte de 190 caractères, le rejet d'un préfixe ressemblant à celui du propriétaire (`brevo-extra` pour `brevo`) et les caractères interdits dans un nom de paramètre. Le service est maintenant enregistré et autowirable par `SettingsBundle`, avec un test vérifiant sa définition Symfony. **L'association automatique entre fournisseur, propriétaire et registre n'est pas encore réalisée** : les fournisseurs existants continuent à déclarer leurs clés selon le contrat actuel jusqu'à la transmission fiable de leur provenance. Aucun scan de module n'est effectué par ce service.

`SettingDefinition::withKey()` permet maintenant de créer une **copie immuable** de la définition avec sa clé qualifiée, en conservant toutes ses métadonnées et les validations du constructeur. Le fournisseur original n'est jamais modifié ; des tests vérifient la copie, les métadonnées et le rejet d'une clé invalide. Le registre expose désormais `registerOwned($provider, $module, $qualifier)` pour qualifier les définitions à partir d'un slug de module déjà vérifié, sans modifier les objets fournis. Les tests couvrent les clés courtes, les collisions après qualification et les préfixes étrangers. Cette méthode est réservée à l'intégration du chargement des modules : **le conteneur ne fournit pas encore automatiquement la provenance des fournisseurs**. Le constructeur actuel du registre conserve son comportement historique en attendant ce raccordement.

## Déclaration des paramètres

Un module fournit un service implémentant `SettingDefinitionProviderInterface` ; Symfony l'étiquette automatiquement `symsvel.settings.definition_provider`. Sa méthode `definitions()` retourne des `SettingDefinition` (clé, libellé, groupe, type, required, secret, public, blocking, description).

`SettingRegistry` rassemble les déclarations de plusieurs fournisseurs, conserve leur ordre d'itération et refuse les clés dupliquées, y compris entre deux modules distincts. Les tests unitaires couvrent l'ordre des déclarations et les collisions entre fournisseurs. Les clés déclarées doivent être canoniques, sans espaces en début ou fin : l'entité `Setting` retire ces espaces à la construction, ce qui provoquerait sinon une divergence entre le registre et la clé réellement stockée. `SettingDefinition` refuse désormais ces clés incohérentes ; des tests couvrent leur rejet. Les types supportés sont `string`, `boolean` (valeurs `0` et `1`), `email` et `url`. Un booléen facultatif accepte aussi une valeur vide ou `null` pour effacer sa configuration ; un booléen obligatoire refuse l'effacement. Les booléens PHP natifs sont convertis explicitement (`false` → `0`, `true` → `1`) avant la conversion en chaîne, car `(string) false` produit une chaîne vide. Les valeurs booléennes non reconnues sont rejetées. Ces cas sont couverts par les tests de normalisation. La même suite vérifie les chaînes nettoyées des espaces périphériques, le refus d'une valeur obligatoire vide, la validation des emails et URL, et le rejet des valeurs non scalaires. Ces tests restent unitaires : ils ne valident pas encore l'enregistrement Doctrine en base. Un paramètre ne peut pas être simultanément `secret` et `public`. Une définition `blocking: true` doit aussi être `required: true` : une configuration facultative ne peut pas bloquer l'onboarding. La construction de la définition refuse cette incohérence ; des tests unitaires couvrent les deux cas.

`SettingManager::get()` retourne la valeur déchiffrée si nécessaire ; `set()` valide, normalise et prépare l'enregistrement ; `flush()` valide la transaction Doctrine. Lors de plusieurs `set()` successifs sur une clé encore absente de la base, le gestionnaire conserve temporairement l'entité nouvellement créée pour la réutiliser : un seul INSERT est produit au `flush()`, avec la dernière valeur. `get()` peut lire cette valeur en attente ; la liste temporaire est vidée après un `flush()` réussi. Ce mécanisme évite les collisions d'unicité Doctrine sans modifier le schéma. Deux tests d'intégration couvrent les écritures répétées sur une valeur simple et sur un secret chiffré. Les clés non déclarées sont refusées en lecture et écriture.

`missingRequired()`, `missingBlocking()`, `missingNonBlocking()` et `isComplete()` sont calculés depuis les déclarations et la base, sans flag d'onboarding.

## Onboarding dynamique

`SettingOnboarding::status()` construit un état `{ complete, missing, pending }` depuis `SettingManager::missingBlocking()` et `missingNonBlocking()`. `missing` contient les clés obligatoires bloquantes absentes ; `pending` les obligatoires non bloquantes absentes. `complete` vaut `true` dès qu'aucune clé bloquante ne manque, même si des éléments restent en attente. Aucun indicateur de complétude n'est enregistré en base : les nouvelles déclarations de modules sont prises en compte au prochain calcul. La projection pure `summarize()` est couverte par des tests unitaires ; le calcul réel en base et l'intégration HTTP restent à tester en CT.

L'API BO d'écriture et d'onboarding n'est **pas encore exposée** : les modules Admins/ACLs ne fournissent pas encore le contrat de contrôle d'accès opérationnel requis. Ne pas ajouter de route PATCH avant authentification et autorisation vérifiables.

## Projection pour le futur BO

`SettingPresentation::all()` prépare les métadonnées d'affichage des paramètres (`key`, `label`, `group`, `type`, `required`, `secret`, `public`, `blocking`, `description`, `configured`, `value`). Pour une définition secrète, `value` reste toujours `null` ; seul le booléen `configured` est exposé. Les tests vérifient qu'une valeur sensible n'apparaît pas dans la sérialisation JSON. Cette projection est destinée **uniquement à un futur endpoint BO authentifié et autorisé** ; elle ne doit jamais être raccordée à l'API publique. Aucun endpoint BO n'est ouvert à ce stade.

## Lecture publique

`GET /api/settings/public` expose uniquement les valeurs non vides dont la définition porte explicitement `public: true`. Les définitions `secret: true` sont exclues avant toute lecture, même si un fournisseur est mal configuré. La réponse est `{ "data": { "cle": "valeur" } }`. Aucune route d'écriture publique n'est prévue. Les définitions sont fournies par les modules consommateurs ; en leur absence, la réponse contient un objet vide. La route est déclarée par attribut et chargée par la recette Flex existante.

Le filtrage est isolé dans `PublicSettingFilter` et testé avec un lecteur espion : seules les clés publiques sont lues ; les clés privées et secrètes ne sont jamais consultées. Les valeurs `null`, vides ou composées uniquement d'espaces sont exclues. Le test unitaire couvre ce contrat de confidentialité, mais ne remplace pas un test HTTP dans un projet installé.

Ce endpoint est public par conception : un module ne doit jamais marquer comme public une donnée personnelle ou sensible. Le comportement HTTP et les règles de sécurité du projet hôte restent à valider en crash-test.

### Test de routage

Le test unitaire du contrôleur inspecte les arguments de l'attribut PHP `#[Route]` par `ReflectionAttribute::getArguments()`. Il ne faut pas utiliser `getPath()` ou `getMethods()` sur `Symfony\\Component\\Routing\\Attribute\\Route` : ces accesseurs ne font pas partie de son API. Le test de routage ne remplace pas le test HTTP de filtrage des secrets.

## Secrets

`SettingSecretCipher` utilise libsodium secretbox, avec une clé dérivée de `kernel.secret`. Les valeurs sont chiffrées en base. **Changer APP_SECRET sans migration/rechiffrement des secrets rend les valeurs existantes illisibles.** Ne jamais exposer les valeurs secrètes dans les réponses API, logs ou écrans. La rotation de clé et l'API BO restent hors de ce premier jalon.

## Tests d'intégration Doctrine

`SettingPersistenceTest` exerce l'entité `Setting` avec un vrai `EntityManager` Doctrine et une base SQLite en mémoire, créée puis isolée pour chaque test. Il vérifie insertion, relecture après `clear()`, mise à jour nullable et contrainte d'unicité de `setting_key`. Le test exige `pdo_sqlite` ; si l'extension est absente, PHPUnit signale explicitement un test ignoré, ce qui **ne vaut pas validation**. `symfony/cache` est déclaré en dépendance de développement pour la configuration de métadonnées Doctrine. Le `EntityManager` autonome du test active explicitement les lazy objects natifs de PHP 8.4 via `enableNativeLazyObjects(true)` ; cela évite de dépendre de Symfony LazyGhost (`symfony/var-exporter`) pour cette configuration de test. Ce test ne valide ni le compilateur de migrations Tooling ni les SGBD PostgreSQL, MySQL et MariaDB : leurs crash-tests restent nécessaires.


`SettingManagerPersistenceTest` complète ce test par des parcours réels via `SettingRepository` et `SettingManager` : écriture et relecture après vidage de l'EntityManager, normalisation des booléens et effacement facultatif, chiffrement des secrets en base puis déchiffrement, recalcul dynamique de l'onboarding et refus des clés inconnues sans écriture. Le workflow CI exige désormais explicitement `pdo_sqlite` avant PHPUnit : l'absence du pilote fait échouer la CI plutôt que de laisser les tests d'intégration ignorés. Ces tests ne constituent pas une validation multibase ou HTTP.

## Garde-fou CI : syntaxe PHP

Le workflow QA exécute désormais `php -l` sur chaque fichier PHP de `back/src` et `back/tests` **avant** PHPUnit. Une erreur de syntaxe bloque immédiatement le job et ne se retrouve plus dispersée en erreurs de chargement dans les tests. Ce contrôle syntaxique complète les tests mais ne remplace pas les validations fonctionnelles et crash-tests.

## Portée et validation

Le socle backend inclut désormais la lecture publique des paramètres explicitement exposables. Aucun fournisseur de paramètres applicatifs natifs, contrôleur BO, écran Svelte, transport Brevo ou règle d'onboarding bloquant n'est activé. Les modules consommateurs déclareront leurs propres clés.

Validation encore requise : tests automatisés, CI, installation et crash-tests PostgreSQL, MySQL, MariaDB, SQLite, puis validation utilisateur. Le squelette `v0.1.0` sur `main` reste intact jusqu'à validation.
