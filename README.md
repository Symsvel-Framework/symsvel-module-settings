# SymSvel Settings

Module natif de paramètres applicatifs, issu du contrat XCore Settings et adapté à SymSvel.

- Stockage Doctrine dans `symsvel_settings` (propriété exclusive du module).
- Registre de définitions extensible par les modules.
- Validation des valeurs, chiffrement libsodium des secrets et détection des paramètres obligatoires manquants.
- Migration portable Doctrine Schema, compilée et exécutée par Tooling.
- Lecture publique contrôlée via `GET /api/settings/public`, sans accès aux secrets.

**État : développement sur `dev`.** Pas encore d'API BO d'administration, de configuration initiale, de connexion Brevo ni de crash-test multibase validé. Le tag `v0.1.0` reste le squelette initial.

Voir `docs/dev/index.md` pour le contrat technique et les limites.

## Intégration continue GitHub-hosted (22/11/2026)

Le workflow public `.github/workflows/qa.yml` utilise désormais **GitHub-hosted Ubuntu 24.04** au lieu des runners Docker du mainteneur. Les tests PHP/SQLite et l'intégration BO sont conservés. Les tests d'interface installent Chromium et ses dépendances système lorsque nécessaire.

Les runners auto-hébergés sont réservés à `symsvel-packages` et `symsvel-recipes`, qui restent privés. Une CI verte ne dispense pas des crash-tests locaux.

## Licence MIT et contributions

Ce dépôt est distribué sous [licence MIT](LICENSE). Le code peut être utilisé, modifié et redistribué, y compris dans des projets commerciaux ou propriétaires, dans les conditions de cette licence (notamment la conservation de la notice de copyright et de licence).

**Contribuer :** créer un fork, puis proposer une pull request vers `dev` lorsque cette branche existe, sinon vers la branche par défaut. Expliquer la modification et fournir les tests et la documentation concernés. Les mainteneurs conservent la maîtrise des revues, des validations (CI et crash-tests), de l'intégration et des releases officielles. Une PR ne garantit pas son acceptation.

**Versions officielles :** seules les versions diffusées par l'organisation [Symsvel-Framework](https://github.com/Symsvel-Framework) sont officielles. Les forks sont libres sous MIT, mais ne doivent pas être présentés comme officiellement approuvés par les mainteneurs. Cette distinction n'ajoute aucune restriction aux droits accordés par la licence MIT sur le code.
