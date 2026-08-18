import { Component, HostListener, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { AuthService } from './core/auth.service'; 

@Component({
  selector: 'app-root', 
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './app.html', 
  styleUrl: './app.css' 
})
export class App {
  private auth = inject(AuthService);
  private router = inject(Router);

  isMenuOpen = false;
  isScrolled = false;

  get session() {
    return this.auth.getSession();
  }

  get role() {
    // CORRECTION : On accède directement au rôle depuis la session
    return this.session?.role;
  }

  toggleMenu(): void {
    this.isMenuOpen = !this.isMenuOpen;
  }

  closeMenu(): void {
    this.isMenuOpen = false;
  }

  logout(): void {
    this.auth.logout();
    this.closeMenu();
    this.router.navigate(['/login']);
  }

  @HostListener('window:scroll', [])
  onWindowScroll() {
    this.isScrolled = window.scrollY > 50;
  }
}