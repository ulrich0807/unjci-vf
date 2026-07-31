# UNJCI Membership — Angular 21

Prototype front-end pour la gestion des demandes de carte de membre UNJCI, la génération d'une carte avec QR code et la vérification d'authenticité par caméra.

## Fonctionnalités

- Formulaire complet d'adhésion et de renouvellement
- Montant automatique : 10 000 FCFA (adhésion) ou 5 000 FCFA (renouvellement)
- Sélection du paiement mobile : Wave, MTN MoMo, Orange Money, Moov Money
- Ajout des pièces justificatives et aperçu de la photo
- Création d'un numéro d'adhérent et d'un jeton QR uniques
- Carte numérique imprimable
- Scan QR avec la caméra grâce à `@zxing/browser`
- Vérification manuelle par URL ou jeton
- Affichage du statut, de la photo et des informations principales
- Stockage de démonstration dans `localStorage`

## Prérequis

- Node.js 22 ou version compatible avec Angular 21
- npm 10+

## Lancement

```bash
npm install
npm start
```

Puis ouvrir `http://localhost:4200`.

## Compilation

```bash
npm run build
```

## Important pour la production

Cette version active automatiquement la carte après la soumission pour permettre une démonstration complète. En production :

1. remplacer `localStorage` par une API sécurisée (Laravel recommandé) ;
2. stocker les fichiers sur le serveur ou un stockage objet ;
3. ajouter un back-office de validation des pièces et paiements ;
4. générer et signer les jetons QR côté serveur ;
5. utiliser HTTPS, indispensable pour l'accès caméra sur mobile ;
6. ajouter les statuts suspendue, expirée, perdue, remplacée et révoquée ;
7. journaliser les scans et limiter les informations publiques affichées.
