# Settings : administration

Le socle Settings en cours de développement stocke des paramètres déclarés par les modules. Les valeurs sensibles sont chiffrées en base.

Un endpoint de lecture publique `GET /api/settings/public` est disponible pour les paramètres explicitement marqués publics. Il ne retourne jamais de valeur secrète. Aucun écran d'administration ni endpoint de modification n'est livré à ce stade. Les paramètres obligatoires manquants sont calculés dynamiquement ; le service `SettingOnboarding` fournit désormais les clés bloquantes manquantes (`missing`), non bloquantes en attente (`pending`) et la complétude (`complete`). Ce service n'expose pas de route HTTP et n'enregistre aucun drapeau de complétude.

Le service interne `SettingPresentation` prépare désormais les données de l'interface BO : un secret configuré apparaît comme tel, mais sa valeur reste masquée (`null`). Aucun accès HTTP à ce service n'est encore exposé.

Une future interface ne devra jamais retourner la valeur d'un paramètre `secret` et devra appliquer les ACL SymSvel. Cette documentation sera complétée lors de l'implémentation des écrans et des endpoints.
