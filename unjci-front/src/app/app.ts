import { Component, ElementRef, HostListener, effect, inject, viewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { AuthService } from './core/auth.service'; 
import { MembershipClosedDirective, MembershipClosure } from './core/membership-closure';

@Component({
  selector: 'app-root', 
  standalone: true,
  imports: [CommonModule, RouterModule, MembershipClosedDirective],
  templateUrl: './app.html', 
  styleUrl: './app.css' 
})
export class App {
  private auth = inject(AuthService);
  private router = inject(Router);
  protected readonly membershipClosure = inject(MembershipClosure);
  private readonly closureDialog = viewChild<ElementRef<HTMLDialogElement>>('closureDialog');

  constructor() {
    effect(() => {
      const dialog = this.closureDialog()?.nativeElement;
      if (dialog && this.membershipClosure.visible() && !dialog.open) {
        dialog.showModal();
      }
    });
  }

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
