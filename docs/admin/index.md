# Settings : administration

Le socle Settings en cours de développement stocke des paramètres déclarés par les modules. Les valeurs sensibles sont chiffrées en base.

Un endpoint de lecture publique `GET /api/settings/public` est disponible pour les paramètres explicitement marqués publics. Il ne retourne jamais de valeur secrète. L'écran BO et les endpoints de modification sont désormais livrés dans le module. Les paramètres obligatoires manquants sont calculés dynamiquement ; le service `SettingOnboarding` fournit désormais les clés bloquantes manquantes (`missing`), non bloquantes en attente (`pending`) et la complétude (`complete`). Le calcul ne persiste aucun drapeau de complétude.

Le service interne `SettingPresentation` prépare désormais les données de l'interface BO : un secret configuré apparaît comme tel, mais sa valeur reste masquée (`null`). La présentation est exposée uniquement via le contrôleur BO protégé par ACL.

L'interface ne retourne jamais la valeur d'un paramètre `secret` et les ACL SymSvel sont appliquées côté PHP. Cette documentation sera complétée lors de l'implémentation des écrans et des endpoints.

## Écran BO Settings

La page `/settings` présente les paramètres regroupés par module, avec leurs types, descriptions et état configuré. Elle modifie uniquement les valeurs et ne révèle jamais les secrets ; la lecture et la modification exigent respectivement les ACL `settings:list` et `settings:update`. Les URLs déduites automatiquement du Setup, notamment la confirmation de l'adresse App, ne sont pas éditables. Les définitions restent régies par le code des modules et Tooling.


## Coque BO et alertes de configuration (16/11/2026)

La page `/settings` est rendue par le layout hôte `symsvel-bo`; elle
conserve uniquement ses formulaires et appels `boApi`. Elle déclare en
option `boAlerts` dans sa navigation, qui signale les paramètres obligatoires
non configurés en utilisant les métadonnées non secrètes de
`GET /apibo/settings`. Le shell affiche ces avertissements seulement si
le module est installé et si son endpoint de lecture est autorisé. Les ACL
restent imposées côté PHP et les secrets ne sont jamais renvoyés.
Voir `symsvel-bo/docs/bo-ui.md`.

## Composants génériques (16/11/2026)

La page Settings utilise les composants partagés `Button` et `Alert`
au lieu de dupliquer leurs styles. Après une mise à jour réussie d'un
paramètre, elle émet `symsvel:alerts-changed` afin que le shell BO
rafraîchisse immédiatement les alertes d'administration sans quitter la page.
Voir `symsvel-bo/docs/bo-ui.md`.


## Listes de choix gérées par les modules (16/11/2026)

Certains paramètres affichent désormais un menu déroulant, dont les
valeurs sont fournies par le module propriétaire. Une valeur inconnue
est refusée par l'API et ne remplace jamais l'ancienne. Pour Mails, les
choix proviennent des transports installés, et ne contiennent pas les
DSN ou identifiants privés.


## Paramètres initialisés définitivement (16/11/2026)

Les définitions de modules peuvent signaler un paramètre en
**lecture seule** : son affichage est conservé, mais l'enregistrement
est désactivé et l'API refuse les écritures directes. La langue
principale définie par le Setup utilise ce mécanisme.

### Normalisation Design System et sauvegarde ciblée BO (19/11/2026)

Les types `boolean` et `choice` de Settings utilisent désormais `Dropdown.svelte` natif du BO (palette clair/sombre, navigation clavier), avec les valeurs déclarées par chaque module via `choices`. Les types `email`, `url`, `string` et `secret` conservent les champs HTML adaptés. Les surfaces, accordéons, entrées, bordures, textes secondaires et focus consomment les jetons `--bo-*`, sans palette de module parallèle.

Une sauvegarde réussie actualise **uniquement le paramètre modifié** dans l'état Svelte et envoie `symsvel:alerts-changed`. Elle ne rappelle plus `GET /apibo/settings` pour remplacer tous les brouillons : la saisie non validée d'un autre paramètre est préservée, ainsi que l'accordéon et le scroll. Une erreur conserve la saisie de la ligne. Les valeurs `secret` restent **toujours null** dans l'état affichable et leur saisie est vidée après succès ; seul `configured` est recalculé. Le bouton Actualiser permet une resynchronisation explicite. L'API et ses ACL restent inchangées. Des tests vérifient l'absence de fuite de secrets et l'état confirmé. CI et CT XT requis.

Les libellés de champs texte conservent leur association HTML `for/id` ; pour les deux champs Dropdown, un libellé visuel distinct est conservé et l'association accessible passe par la prop `label` du composant, sans `for` orphelin.

### Correction des accordéons et des menus de choix (19/11/2026)

Le groupe de paramètres ouvert est désormais piloté par `bind:open` sur
chaque `details` natif, avec un état mémorisé par identifiant de module.
Le premier groupe est ouvert uniquement lors du chargement initial ; une
sauvegarde de valeur ne réinitialise plus le groupe choisi. La propriété HTML
`name` conserve l'exclusivité native entre accordéons. Une actualisation
explicite conserve le groupe sélectionné s'il existe encore.

Les menus `Dropdown` des paramètres `boolean` et `choice` ne doivent pas
être coupés par les panneaux : le conteneur des accordéons utilise
`overflow: visible`, et `:focus-within` place temporairement l'accordéon
actif devant ses voisins. Les coins du résumé restent arrondis lorsque le
panneau est fermé. Aucun changement du composant `Dropdown` commun,
des APIs ni des règles Settings/Mails.

Tests de non-régression du contrat Svelte/CSS ajoutés ; vérifier sur XT :
ouvrir un groupe non initial, modifier puis enregistrer une valeur, constater
que le groupe et la position de défilement restent inchangés ; ouvrir le
transport Mails au-dessus des groupes suivants et sélectionner une option.
Validation complète soumise aux CI et à ce crash-test navigateur.

### Actualisation de navigation sans rechargement (19/11/2026)

Après l'enregistrement de `languages.multilingual`, Settings émet
`symsvel:navigation-changed` en plus de l'actualisation des alertes.
Le BO hôte relance uniquement les sondes des modules installés : Countries
apparaît ou disparaît immédiatement du menu selon le réglage et les ACL.
Le paramètre demeure propriétaire de Languages ; Settings ne connaît
ni n'instancie le module Countries. CI et CT sur XT requis.

### Conventions de couleur des boutons (19/11/2026)

Enregistrer un paramètre est une validation (vert). Les boutons Actualiser et les contrôles de saisie restent neutres ; aucun rechargement n'est introduit.
Les couleurs et l'état désactivé proviennent des variantes natives du BO hôte,
voir `symsvel-bo/docs/dev/bo-components.md`, sans CSS de module additionnel.
CI et CT visuel clair/sombre requis.

## Valeurs par défaut et paramètres imposés (27/11/2026)

Un module peut proposer un paramètre obligatoire déjà renseigné par défaut, modifiable si aucune contrainte ne s'applique. Un paramètre imposé par le module apparaît en lecture seule avec sa valeur effective ; une requête directe de modification est également refusée. Lors de la synchronisation d'installation, mise à jour ou retrait, la valeur imposée est enregistrée. Après retrait de la contrainte, l'ancien choix antérieur à son activation n'est pas restauré automatiquement. Le responsable peut alors choisir une autre valeur si le module l'autorise. Aucun changement pour les paramètres existants sans ces métadonnées. La validation sur XT avec un module consommateur reste requise.
