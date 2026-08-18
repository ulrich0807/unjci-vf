import { CommonModule } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService, UserRole } from '../../core/auth.service';

@Component({
  selector: 'app-renewal-entry',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './renewal-entry.component.html',
  styleUrl: './renewal-entry.component.css',
})
export class RenewalEntryComponent implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  showChoices = false;

  ngOnInit(): void {
    const session = this.auth.getSession();

    if (!session) {
      this.showChoices = true;
      return;
    }

    const destinations: Record<UserRole, string> = {
      member: '/espace-membre',
      admin: '/administration',
      scanner: '/scanner',
    };

    void this.router.navigateByUrl(destinations[session.role]);
  }
}
