import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from '../../core/auth.service';
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
  private auth = inject(AuthService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  token: string | null = null;
  member: any = null;
  errorMsg: string | null = null;
  isLoading = true;
  readonly storageUrl = environment.storageUrl;

  ngOnInit(): void {
    // 1. On récupère le code unique dans l'URL (ex: /verification/ABcd123...)
    this.token = this.route.snapshot.paramMap.get('token');

    if (!this.token) {
      this.errorMsg = "Aucun code de carte détecté.";
      this.isLoading = false;
      return;
    }

    this.checkAndVerify(this.token);
  }

  checkAndVerify(token: string): void {
    const session = this.auth.getSession();
    
    // 2. Si l'agent n'est pas connecté, on l'envoie vers le login avec l'URL en mémoire
    if (!session || !session.token) {
      this.router.navigate(['/login'], { queryParams: { returnUrl: `/verification/${token}` } });
      return;
    }

    // 3. Si l'agent est connecté, on interroge Laravel
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session.token}` });

    this.http.get(`${environment.apiUrl}/verify-card/${token}`, { headers }).subscribe({
      next: (res: any) => {
        this.member = res.member;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMsg = "Cette carte est introuvable ou n'est plus valide.";
        this.isLoading = false;
        this.cdr.markForCheck();
      }
    });
  }

  goBack(): void {
    this.router.navigate(['/']);
  }
}
