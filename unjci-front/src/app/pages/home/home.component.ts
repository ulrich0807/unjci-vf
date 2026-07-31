import { Component, OnInit, OnDestroy } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgFor, NgClass,CommonModule } from '@angular/common';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [RouterLink, NgFor, NgClass, CommonModule],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit, OnDestroy {
  
  currentSlide = 0;
  slideInterval: any;

  slides = [
    {
      image: 'assets/images/scan1.jpg',
      badge: 'Plateforme Numérique Officielle',
      titre: 'L\'Union Nationale des Journalistes de Côte d\'Ivoire',
      description: 'Bienvenue sur la plateforme officielle de gestion et de vérification des cartes de membre de l\'UNJCI.'
    },
    {
      image: 'assets/images/scan6.jpg',
      badge: 'Innovation',
      titre: 'Dématérialisation totale des adhésions',
      description: 'Centralisez vos informations personnelles et professionnelles. Soumettez vos demandes d\'adhésion entièrement en ligne.'
    },
    {
      image: 'assets/images/scan3.jpg',
      badge: 'Technologie',
      titre: 'Votre carte numérique sécurisée',
      description: 'Chaque membre validé bénéficie d\'une carte dotée d\'un QR Code unique garantissant son authenticité sur le terrain.'
    }
  ];

  benefices = [
    {
      image: 'assets/images/scan2.jpg',
      titre: 'Identification Sécurisée',
      description: 'Obtenez une carte authentique, attestant de votre appartenance à la profession. Le QR code réduit les risques de falsification.'
    },
    {
      image: 'assets/images/scan3.jpg',
      titre: 'Solidarité et Défense',
      description: 'Bénéficiez du soutien du plus grand réseau de journalistes en Côte d\'Ivoire. L\'UNJCI protège vos droits dans l\'exercice de vos fonctions.'
    }
  ];

  opportunites = [
    {
      image: 'assets/images/scan4.jpg',
      titre: 'Renforcement des Capacités',
      description: 'Accédez en priorité à nos programmes de formation continue, à des séminaires exclusifs et à des ateliers de perfectionnement.'
    },
    {
      image: 'assets/images/scan5.jpg',
      titre: 'Célébration de l\'Excellence',
      description: 'Participez aux grandes instances de l\'Union et devenez éligible aux prestigieux Prix Ebony récompensant les meilleurs professionnels.'
    }
  ];

  ngOnInit() {
    this.startSlider();
  }

  ngOnDestroy() {
    this.stopSlider();
  }

  startSlider() {
    this.slideInterval = setInterval(() => {
      this.nextSlide();
    }, 7000);
  }

  stopSlider() {
    if (this.slideInterval) {
      clearInterval(this.slideInterval);
    }
  }

  nextSlide() {
    this.currentSlide = (this.currentSlide + 1) % this.slides.length;
  }

  prevSlide() {
    this.currentSlide = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
    this.stopSlider();
    this.startSlider();
  }

  setSlide(index: number) {
    this.currentSlide = index;
    this.stopSlider();
    this.startSlider();
  }
  chiffresCles = [
    { valeur: "30+", label: "Années d'existence" },
    { valeur: "1500+", label: "Membres actifs" },
    { valeur: "1", label: "Maison de la Presse" }
  ];
}