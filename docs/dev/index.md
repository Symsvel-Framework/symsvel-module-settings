# Settings : contrat développeur

Module natif de stockage de paramètres applicatifs en base de données. Adaptation ciblée du module XCore Settings 1.1.35. Le module ne modifie ni le bootstrap Symfony, ni les fichiers `.env`, ni les transports de mails.

## Stockage et migrations

L'entité `SymSvel\Module\Settings\Entity\Setting` possède une clé unique (`setting_key`, 190 caractères) et une valeur texte nullable. La table `symsvel_settings` appartient exclusivement à ce module, conformément à `module.json`.

`Migration/Version20200101000400.php` utilise Doctrine Schema (aucun SQL spécifique MySQL). La compilation, l'ordonnancement et l'exécution relèvent de SymSvel Tooling. La version source Settings a été corrigée avant validation de son premier CT : l'ancien nom `Version20200101000000.php` entrait en collision avec la migration native Admins. Le nouveau nom `Version20200101000400.php` est unique parmi les migrations natives vérifiées (Admins, Roles, ACLs, Users, Settings). Une fois une migration intégrée à un projet, sa version et son contenu deviennent immuables ; ne pas renommer une source déjà appliquée.

## Convention architecturale des clés (ADR 0001)

La clé canonique est `module.parametre`. Le module consommateur peut déclarer `api_key` ; le registre la qualifie en `brevo.api_key` lorsque l'inventaire Composer vérifié associe son fournisseur au module `brevo`. Une clé déjà correctement qualifiée est conservée ; un préfixe étranger et les doublons après qualification sont rejetés. Le stockage conserve une seule colonne `setting_key` (190 caractères), sans nouvelle migration.

Le compiler pass `SettingProviderOwnershipPass` exploite `backend/.symsvel/modules.json` publié par Tooling, associe chaque service fournisseur à un seul chemin de module installé et injecte la carte des propriétaires dans `SettingRegistry`. Il ajoute l'attribut de tag `settings_provider_id` à chaque fournisseur, utilisé par `AutowireIterator(indexAttribute: 'settings_provider_id')` pour conserver les identifiants de services comme clés de l'itérateur. Cette association se fait lors de la compilation du conteneur, jamais par découverte de modules pendant une requête.

Sans inventaire, le registre conserve son comportement historique pour les tests isolés. Les clés historiques déjà persistées sans préfixe nécessitent une migration explicite, sans conversion silencieuse. Les tests du compiler pass et du registre vérifient les contrats séparément ; le test fonctionnel d'un fournisseur tiers dans un projet installé reste nécessaire avant clôture du chantier.

