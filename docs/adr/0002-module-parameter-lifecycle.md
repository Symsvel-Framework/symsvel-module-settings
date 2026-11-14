# ADR 0002 : cycle de vie des paramètres de modules

- Statut : **accepté sur le principe, implémentation à réaliser**
- Date : 13 novembre 2026
- Portée : Settings, Tooling, modules consommateurs et BO
- Complète l'ADR 0001 sur la qualification des clés `module.parametre`.

## Contexte

Le registre Settings découvre les définitions PHP des modules installés, mais les lignes de `symsvel_settings` n'existent actuellement qu'après un appel à `SettingManager::set()`. Un module installé peut donc déclarer des paramètres sans qu'ils soient matérialisés en base. Le BO ne doit pas effectuer une synchronisation implicite à chaque consultation.

## Décision

1. Le code de chaque module est la source des **définitions souhaitées**. Les clés canoniques suivent l'ADR 0001.
2. Tooling synchronise les définitions pendant le cycle de vie des modules : installation, mise à jour et désinstallation, **après** disponibilité de la base et de la migration Settings.
3. `backend/.symsvel/modules-params.json` est le dictionnaire versionné par schéma de l'**état effectivement synchronisé**. Il répertorie par module les clés et les métadonnées utiles (`label`, `group`, `type`, `required`, `secret`, `public`, `blocking`, `description`). Il ne contient **aucune valeur** ni secret.
4. La table `symsvel_settings` reste propriétaire de Settings ; chaque paramètre installé possède une ligne, initialement `NULL`. Le BO modifie uniquement les valeurs, jamais les définitions, et ne propose aucune suppression de paramètre.
5. À la mise à jour d'un module, Tooling compare ses définitions souhaitées à son dernier état synchronisé. Il ajoute les clés nouvelles, conserve les valeurs existantes et supprime les clés explicitement retirées du code du module. La suppression ne concerne jamais les clés des autres modules.
6. Les modifications de métadonnées sont répercutées dans le dictionnaire. Une modification incompatible avec une valeur existante (notamment type ou confidentialité) doit être détectée et traitée explicitement avant validation, jamais corrigée silencieusement.
7. Une synchronisation répétée sans changement est idempotente. La publication du dictionnaire doit être cohérente avec la transaction BDD et les mécanismes de reprise existants ; une erreur ne doit pas annoncer un état synchronisé fictif.
8. Les définitions et leur provenance continuent d'utiliser les mécanismes de propriété déjà vérifiés. Ne pas déduire un propriétaire d'un namespace, ni scanner les modules lors des requêtes HTTP.
9. L'initialisation du projet doit traiter les modules natifs **après** les migrations, et les transactions de maintenance doivent intégrer la synchronisation et son rollback. Ne pas introduire de dépendance circulaire Tooling ↔ Settings.

## Validation exigée

- Tests automatisés : première installation, ajout, suppression, modification de métadonnées, réinstallation idempotente, conservation des valeurs, isolation entre modules, échec et reprise, secrets absents du dictionnaire.
- Test d'intégration réel avec Mails : `mails.sender_email`, `mails.sender_name` et `mails.transport` présents avec valeurs `NULL` dès installation ; mise à jour préserve les valeurs.
- CI vertes sur les dépôts modifiés et crash-tests locaux XT, y compris le parcours de mise à jour.
- Toute modification du code doit mettre à jour les documentations développeur et administrateur concernées.

## Limite de cette étape

Cette ADR formalise le contrat ; elle ne constitue **pas** une implémentation ni une validation du mécanisme.

## Couverture d'intégration de l'inventaire (14/11/2026)

`back/tests/Integration/SettingInventoryLifecycleTest.php` exerce `SettingInventorySynchronizer`, le registre, le dictionnaire sur disque et la vraie persistance Doctrine ORM sur SQLite en mémoire. Les scénarios couvrent la première matérialisation des clés Mails avec valeurs `NULL`, la répétition idempotente, la conservation d'une valeur lors d'une mise à jour, la suppression isolée des définitions retirées, la désinstallation et la réinstallation, ainsi que l'absence de valeurs dans `modules-params.json`.

Ce test utilise des définitions de fixture correspondant aux clés Mails : il **ne constitue pas encore** le test de bout en bout avec le véritable provider Mails, Composer, le conteneur Symfony et les migrations du projet hôte demandé dans la section Validation. CI et crash-test XT restent à valider.
