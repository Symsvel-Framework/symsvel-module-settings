# Settings : contrat développeur

Module natif de stockage de paramètres applicatifs en base de données. Adaptation ciblée du module XCore Settings 1.1.35. Le module ne modifie ni le bootstrap Symfony, ni les fichiers `.env`, ni les transports de mails.

## Stockage et migrations

L'entité `SymSvel\Module\Settings\Entity\Setting` possède une clé unique (`setting_key`, 190 caractères) et une valeur texte nullable. La table `symsvel_settings` appartient exclusivement à ce module, conformément à `module.json`.

`Migration/Version20200101000000.php` utilise Doctrine Schema (aucun SQL spécifique MySQL). La compilation, l'ordonnancement et l'exécution relèvent de SymSvel Tooling. Une migration source déjà publiée ne doit pas être réécrite.

## Déclaration des paramètres

Un module fournit un service implémentant `SettingDefinitionProviderInterface` ; Symfony l'étiquette automatiquement `symsvel.settings.definition_provider`. Sa méthode `definitions()` retourne des `SettingDefinition` (clé, libellé, groupe, type, required, secret, public, blocking, description).

`SettingRegistry` rassemble ces déclarations et refuse les clés dupliquées. Les types supportés sont `string`, `boolean` (valeurs `0` et `1`), `email` et `url`. Un paramètre ne peut pas être simultanément `secret` et `public`.

`SettingManager::get()` retourne la valeur déchiffrée si nécessaire ; `set()` valide, normalise et prépare l'enregistrement ; `flush()` valide la transaction Doctrine. Les clés non déclarées sont refusées en lecture et écriture.

`missingRequired()`, `missingBlocking()`, `missingNonBlocking()` et `isComplete()` sont calculés depuis les déclarations et la base, sans flag d'onboarding.

## Secrets

`SettingSecretCipher` utilise libsodium secretbox, avec une clé dérivée de `kernel.secret`. Les valeurs sont chiffrées en base. **Changer APP_SECRET sans migration/rechiffrement des secrets rend les valeurs existantes illisibles.** Ne jamais exposer les valeurs secrètes dans les réponses API, logs ou écrans. La rotation de clé et l'API BO restent hors de ce premier jalon.

## Portée et validation

Cette première étape fournit uniquement le socle backend. Aucun fournisseur de paramètres applicatifs natifs, contrôleur BO, écran Svelte, transport Brevo ou règle d'onboarding bloquant n'est activé. Les modules consommateurs déclareront leurs propres clés.

Validation encore requise : tests automatisés, CI, installation et crash-tests PostgreSQL, MySQL, MariaDB, SQLite, puis validation utilisateur. Le squelette `v0.1.0` sur `main` reste intact jusqu'à validation.
