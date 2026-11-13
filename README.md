# SymSvel Settings

Module natif de paramètres applicatifs, issu du contrat XCore Settings et adapté à SymSvel.

- Stockage Doctrine dans `symsvel_settings` (propriété exclusive du module).
- Registre de définitions extensible par les modules.
- Validation des valeurs, chiffrement libsodium des secrets et détection des paramètres obligatoires manquants.
- Migration portable Doctrine Schema, compilée et exécutée par Tooling.

**État : développement sur `dev`.** Pas encore d'API BO, de configuration initiale, de connexion Brevo ni de crash-test multibase validé. Le tag `v0.1.0` reste le squelette initial.

Voir `docs/dev/index.md` pour le contrat technique et les limites.
