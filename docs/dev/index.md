# Settings : contrat développeur

Module natif de stockage de paramètres applicatifs en base de données. Adaptation ciblée du module XCore Settings 1.1.35. Le module ne modifie ni le bootstrap Symfony, ni les fichiers `.env`, ni les transports de mails.

## Stockage et migrations

L'entité `SymSvel\Module\Settings\Entity\Setting` possède une clé unique (`setting_key`, 190 caractères) et une valeur texte nullable. La table `symsvel_settings` appartient exclusivement à ce module, conformément à `module.json`.

`Migration/Version20200101000000.php` utilise Doctrine Schema (aucun SQL spécifique MySQL). La compilation, l'ordonnancement et l'exécution relèvent de SymSvel Tooling. Une migration source déjà publiée ne doit pas être réécrite.

## Déclaration des paramètres

Un module fournit un service implémentant `SettingDefinitionProviderInterface` ; Symfony l'étiquette automatiquement `symsvel.settings.definition_provider`. Sa méthode `definitions()` retourne des `SettingDefinition` (clé, libellé, groupe, type, required, secret, public, blocking, description).

`SettingRegistry` rassemble les déclarations de plusieurs fournisseurs, conserve leur ordre d'itération et refuse les clés dupliquées, y compris entre deux modules distincts. Les tests unitaires couvrent l'ordre des déclarations et les collisions entre fournisseurs. Les clés déclarées doivent être canoniques, sans espaces en début ou fin : l'entité `Setting` retire ces espaces à la construction, ce qui provoquerait sinon une divergence entre le registre et la clé réellement stockée. `SettingDefinition` refuse désormais ces clés incohérentes ; des tests couvrent leur rejet. Les types supportés sont `string`, `boolean` (valeurs `0` et `1`), `email` et `url`. Un booléen facultatif accepte aussi une valeur vide ou `null` pour effacer sa configuration ; un booléen obligatoire refuse l'effacement. Les booléens PHP natifs sont convertis explicitement (`false` → `0`, `true` → `1`) avant la conversion en chaîne, car `(string) false` produit une chaîne vide. Les valeurs booléennes non reconnues sont rejetées. Ces cas sont couverts par les tests de normalisation. La même suite vérifie les chaînes nettoyées des espaces périphériques, le refus d'une valeur obligatoire vide, la validation des emails et URL, et le rejet des valeurs non scalaires. Ces tests restent unitaires : ils ne valident pas encore l'enregistrement Doctrine en base. Un paramètre ne peut pas être simultanément `secret` et `public`. Une définition `blocking: true` doit aussi être `required: true` : une configuration facultative ne peut pas bloquer l'onboarding. La construction de la définition refuse cette incohérence ; des tests unitaires couvrent les deux cas.

`SettingManager::get()` retourne la valeur déchiffrée si nécessaire ; `set()` valide, normalise et prépare l'enregistrement ; `flush()` valide la transaction Doctrine. Les clés non déclarées sont refusées en lecture et écriture.

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

## Portée et validation

Le socle backend inclut désormais la lecture publique des paramètres explicitement exposables. Aucun fournisseur de paramètres applicatifs natifs, contrôleur BO, écran Svelte, transport Brevo ou règle d'onboarding bloquant n'est activé. Les modules consommateurs déclareront leurs propres clés.

Validation encore requise : tests automatisés, CI, installation et crash-tests PostgreSQL, MySQL, MariaDB, SQLite, puis validation utilisateur. Le squelette `v0.1.0` sur `main` reste intact jusqu'à validation.
