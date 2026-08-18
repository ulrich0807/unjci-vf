import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-adhesion-guide',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './adhesion-guide.component.html',
  styleUrls: ['./adhesion-guide.component.css']
})
export class AdhesionGuideComponent {
  steps = [
    {
      title: 'Accès et Choix de la démarche',
      description: 'Choisissez d\'effectuer une "Nouvelle adhésion" ou un "Renouvellement" de votre carte UNJCI.',
      icon: 'cursor'
    },
    {
      title: 'Constitution du dossier en ligne',
      description: 'Remplissez le formulaire avec vos informations (identité, profession) et téléversez vos pièces justificatives (photo, carte de presse).',
      icon: 'form'
    },
    {
      title: 'Sécurisation du compte (Vérification OTP)',
      description: 'Saisissez le code de vérification à 6 chiffres reçu par email pour activer et sécuriser votre compte.',
      icon: 'shield'
    },
    {
      title: 'Accès à l\'Espace Membre personnel',
      description: 'Une fois votre compte vérifié, accédez à votre tableau de bord. Votre dossier s\'affiche "En attente de paiement".',
      icon: 'user'
    },
    {
      title: 'Phase de Paiement',
      description: 'Effectuez le paiement (10 000 FCFA pour une adhésion, 5 000 FCFA pour un renouvellement) et déclarez la transaction pour validation.',
      icon: 'wallet'
    },
    {
      title: 'Finalisation et Examen du dossier',
      description: 'Après validation du paiement et examen des pièces, le système génère votre matricule et délivre votre carte de membre numérique.',
      icon: 'card'
    }
  ];
}
