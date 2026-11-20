# ADR 0001 : qualification automatique des clés Settings

- Statut : **accepté**, 13 novembre 2026
- Portée : module Settings et contrats de déclaration des modules consommateurs
- Implémentation : **raccordement conteneur ajouté sur `dev`, installation `--dev` validée ; test fournisseur tiers et CI du dernier raccordement à confirmer**

## Contexte

Les définitions de paramètres de plusieurs modules partagent une même table et un même registre. Une clé non qualifiée telle que `api_key` peut entrer en collision avec une déclaration d'un autre module. Ajouter une colonne `module` et un index composite complexifierait le stockage sans nécessité.

## Décision

1. L'identifiant canonique d'un paramètre est `module.parametre`, avec le préfixe correspondant à l'identifiant technique stable du module propriétaire.
2. Un fournisseur peut déclarer une clé courte, par exemple `api_key`. SymSvel la qualifie automatiquement avec l'identifiant de son module propriétaire : `brevo.api_key`.
3. Une clé déjà qualifiée, par exemple `brevo.api_key`, est conservée si son préfixe correspond au module propriétaire. Un fournisseur ne peut pas déclarer de clés sous l'espace de noms d'un autre module.
4. L'identité du module doit provenir du mécanisme de métadonnées et de chargement des modules déjà validé, en s'appuyant sur son identifiant technique stable déclaré dans `module.json`. Elle ne doit pas être déduite d'un namespace PHP, du nom d'une classe ou d'un libellé affiché.
5. Le registre continue de rejeter les collisions de clés **après qualification**.
6. Le stockage conserve une seule colonne `setting_key` avec sa contrainte d'unicité. Aucune colonne `module` et aucune nouvelle migration ne sont introduites pour ce besoin.
7. L'identification du propriétaire doit être fournie sans découverte dynamique supplémentaire de modules au runtime. Le mécanisme concret d'injection ou d'association fournisseur → module sera choisi après inspection du code et des ADR existants.

## Conséquences et validation

- L'API de lecture/écriture et les données persistées utilisent la clé canonique qualifiée.
- Les développeurs peuvent omettre le préfixe lors de la déclaration sans perdre l'isolation des modules.
- Les clés historiques non qualifiées nécessiteront une stratégie explicite de compatibilité ou de migration avant activation ; aucune conversion silencieuse de données existantes.
- Tests attendus : qualification automatique, conservation d'une clé qualifiée valide, rejet d'un préfixe étranger, collisions entre fournisseurs après qualification, cohérence avec le chargement réel des modules.
- Cette ADR acte la **décision architecturale** ; elle ne prétend pas que la qualification automatique est déjà implémentée ou validée en CI.

## Vérification de l'architecture existante (13 novembre 2026)

- `symsvel-tooling/src/Module/ComposerModuleRegistry.php` récupère déjà le slug stable depuis `symsvel-framework/module-<slug>` dans les métadonnées Composer, valide le nom de `module.json` et fournit une entrée indexée par slug.
- `symsvel-tooling/src/Module/ModuleManifest.php` vérifie le manifeste, dont `name`, mais ne définit pas de champ `id` indépendant.
- `symsvel-back/src/Kernel.php` repose sur `MicroKernelTrait` ; `config/services.yaml` n'injecte aucune identité de module dans les services.
- `SettingsBundle` enregistre `SettingRegistry` ; son `#[AutowireIterator('symsvel.settings.definition_provider')]` fournit les instances des fournisseurs, pas leur slug propriétaire.

**Conséquence :** le préfixage ne peut pas être ajouté correctement par une simple manipulation des chaînes dans `SettingRegistry`. Il faut d'abord transmettre l'association *fournisseur → slug Composer vérifié* au conteneur Symfony à partir des métadonnées déjà établies. Ne pas déduire cette association du namespace ou du nom de classe, et ne pas demander aux fournisseurs de répéter manuellement le slug. Ne pas ouvrir de scan de fichiers au runtime.

**Séquence de réalisation :** identifier le point existant où les modules installés sont reliés à leurs services Symfony ; transmettre cette provenance au registre ; qualifier les clés avant l'indexation ; adapter les tests et la documentation ; valider sur projet installé. Tant que la provenance n'est pas disponible, conserver le comportement actuel et le rejet des collisions, sans prétendre que la qualification automatique fonctionne.

## Implémentation du raccordement (13 novembre 2026)

Le `SettingsBundle` enregistre un compiler pass Symfony exécuté après la résolution des tags de services. Ce pass lit exclusivement l'inventaire `backend/.symsvel/modules.json` déjà publié par `symsvel:module:sync --source=composer`. Pour chaque fournisseur tagué, il résout le chemin réel du fichier PHP de sa classe, puis l'associe à **un et un seul** répertoire de module présent dans cet inventaire. La comparaison de chemins canoniques tient compte des dépôts Composer installés par lien symbolique et ne déduit jamais le slug d'un namespace ou du nom d'une classe.

Le pass enrichit également chaque tag fournisseur avec l'attribut `settings_provider_id`, et le registre utilise `AutowireIterator(indexAttribute: 'settings_provider_id')` pour obtenir des clés d'itération égales aux identifiants de services. La carte `service ID → slug` est injectée dans `SettingRegistry` lors de la compilation du conteneur. Les définitions sont ensuite qualifiées avec `SettingKeyQualifier` avant indexation. Un fournisseur non attribuable ou attribuable à plusieurs modules échoue explicitement lors de la compilation ; aucune attribution implicite à `application` n'est faite. L'absence d'inventaire conserve la compatibilité des tests isolés, mais **ne valide pas le fonctionnement d'un projet SymSvel installé**.

Cette étape n'ajoute aucune découverte de modules aux requêtes HTTP et ne modifie ni les tables ni les migrations. Les tests du compiler pass couvrent l'association, l'absence d'inventaire, les métadonnées malformées et les fournisseurs hors module. La validation complète exige encore CI verte et crash-test avec l'inventaire réel après installation.
