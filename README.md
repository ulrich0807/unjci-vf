# UNJCI — gestion des adhésions

Les sources sont `unjci-front/` (Angular 21) et `unjci-api/` (Laravel 12, PHP 8.2+).
`deploiement-complet-sans-terminal/`, `test/` et `test_extract/` sont des copies de livraison ou de vérification : ne pas y développer.

## Parcours

Inscription → vérification de l’e-mail par OTP → connexion → paiement Wave → confirmation → approbation UNJCI → carte QR.
Les premières adhésions coûtent 10 000 FCFA et les renouvellements 5 000 FCFA.
Les anciens numéros sont acceptés après contrôle d’unicité et vérification administrative.
L’administrateur peut rouvrir un dossier rejeté ou générer un nouveau numéro pour un ancien adhérent sans numéro.
Il peut aussi réexaminer un dossier validé : la carte devient indisponible jusqu’à la nouvelle approbation. Le numéro, les pièces, les paiements et la date d’expiration existante sont conservés ; ce réexamen ne prolonge pas l’adhésion.

## Configuration

Copier `unjci-api/.env.example` vers `.env` uniquement pour une nouvelle installation.
Renseigner la base, la messagerie, `APP_KEY`, `APP_URL` et `FRONTEND_URL`.
Configurer les secrets Wave exclusivement dans `.env` ou dans l’environnement de l’hébergeur :

- `WAVE_API_KEY` : clé API du compte marchand.
- `WAVE_WEBHOOK_SECRET` : secret de signature du webhook, obligatoire.
- `WAVE_SIGNING_SECRET` : secret de signature des requêtes, si activé sur la clé API.

Le webhook est `POST /api/webhooks/wave`. Il vérifie la signature du corps brut, sa date, la session, la devise, le montant et l’état du paiement.
Les anciens secrets présents dans des fichiers exemple versionnés doivent être renouvelés dans Wave : les supprimer du dépôt courant ne les retire pas de son historique.

## Fichiers et accès

Les nouveaux justificatifs vont dans `unjci-api/storage/app/private`. Les fichiers historiques restent accessibles par la route authentifiée `/api/storage/{path}` après contrôle du propriétaire ou de la permission.
L’interface charge les photos et PDF avec le jeton d’authentification, sans le placer dans une URL.
Les règles Apache de `unjci-api/public/.htaccess` doivent être déployées dans `/api/.htaccess` pour empêcher un accès direct aux anciens fichiers.
La vérification QR est réservée aux administrateurs et agents scanners, et contrôle le statut et l’expiration de l’adhésion.

## Vérification locale

Dans `unjci-api/` : `php vendor/phpunit/phpunit/phpunit` (SQLite en mémoire, aucune base de production).
Dans `unjci-front/` : `npm test -- --watch=false` puis `npm run build`.
Pour travailler en local, lancer `php artisan serve --host=127.0.0.1 --port=8000` dans `unjci-api/` et `npm start` dans `unjci-front/`. Le proxy de développement transmet `/api` à Laravel.
La compilation n’a pas besoin de télécharger une police Google.

## Déploiement

Compiler le frontend, puis lancer `build-deploiement.ps1` depuis PowerShell pour préparer une livraison manuelle.
Le dossier contient des fichiers générés : le reconstruire après les changements.
Consulter `DEPLOIEMENT-SANS-TERMINAL.txt` pour les mises à jour de base nécessaires aux installations historiques.
Préserver le `.env` et les fichiers existants dans tous les dossiers de stockage ; déployer également les règles `.htaccess` mises à jour.
Ne jamais publier directement le dossier `unjci-api/` sans sa règle `Require all denied`.
L’envoi FTP automatique sur les branches principales est défini dans `.github/workflows/deploy.yml`.
