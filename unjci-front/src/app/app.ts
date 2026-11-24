import { Component, ElementRef, HostListener, OnInit, effect, inject, viewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { AuthService } from './core/auth.service'; 
import { MembershipActionDirective, MembershipClosure } from './core/membership-closure';

@Component({
  selector: 'app-root', 
  standalone: true,
  imports: [CommonModule, RouterModule, MembershipActionDirective],
  templateUrl: './app.html', 
  styleUrl: './app.css' 
})
export class App implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  protected readonly membershipClosure = inject(MembershipClosure);
  private readonly closureDialog = viewChild<ElementRef<HTMLDialogElement>>('closureDialog');

  constructor() {
    effect(() => {
      const dialog = this.closureDialog()?.nativeElement;
      if (!dialog) return;
      if (this.membershipClosure.visible() && !dialog.open) dialog.showModal();
      if (!this.membershipClosure.visible() && dialog.open) dialog.close();
    });
  }

  ngOnInit(): void { this.membershipClosure.refresh().subscribe(); }

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
