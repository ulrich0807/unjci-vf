import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-verify',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './verify.component.html',
  styleUrl: './verify.component.css'
})
export class VerifyComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private http = inject(HttpClient);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  token: string | null = null;
  member: any = null;
  errorMsg: string | null = null;
  isLoading = true;
  photoUrl: string | null = null;

  ngOnInit(): void {
    this.token = this.route.snapshot.paramMap.get('token');

    if (!this.token) {
      this.errorMsg = "Aucun code de carte détecté.";
      this.isLoading = false;
      return;
    }

    this.checkAndVerify(this.token);
  }

  checkAndVerify(token: string): void {
    this.isLoading = true;
    this.member = null;
    this.photoUrl = null;
    this.errorMsg = null;
    const verificationUrl = `${environment.apiUrl}/verify-card/${encodeURIComponent(token)}`;
    this.http.get(verificationUrl).subscribe({
      next: (res: any) => {
        this.member = res.member;
        this.photoUrl = res.member.has_photo ? `${verificationUrl}/photo` : null;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.errorMsg = error.status === 404 || error.status === 422
          ? "Cette carte est introuvable, expirée ou son adhésion n’est pas approuvée."
          : error.status === 429
          ? 'Trop de vérifications. Réessayez dans une minute.'
          : 'La vérification est momentanément indisponible. Réessayez dans quelques instants.';
        this.isLoading = false;
        this.cdr.markForCheck();
      }
    });
  }

  goBack(): void {
    this.router.navigate(['/']);
  }
}
