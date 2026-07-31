import { Component, HostListener, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AuthService } from './core/auth.service'; 

@Component({
  selector: 'app-root', // Ou le sélecteur correspondant à ce composant
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './app.html', // Ajuste le chemin
  styleUrl: './app.css' // Ajuste le chemin
})
export class App {
  private auth = inject(AuthService);

  isMenuOpen = false;
  isScrolled = false;

  // Vérifie dynamiquement si une session existe
  get isLoggedIn(): boolean {
    return this.auth.getSession() !== null;
  }

  // Détermine la bonne URL pour le bouton "mon espace"
  get dashboardLink(): string {
    const session = this.auth.getSession();
    if (!session) return '/login';
    return session.role === 'admin' ? '/administration' : '/espace-membre';
  }

  toggleMenu(): void {
    this.isMenuOpen = !this.isMenuOpen;
  }

  closeMenu(): void {
    this.isMenuOpen = false;
  }

  // Détection du défilement pour l'effet sur le header
  @HostListener('window:scroll', [])
  onWindowScroll() {
    this.isScrolled = window.scrollY > 50;
  }
}