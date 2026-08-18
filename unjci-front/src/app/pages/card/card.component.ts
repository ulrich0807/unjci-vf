import { Component, OnInit, inject,ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from '../../core/auth.service';
import { QRCodeComponent } from 'angularx-qrcode'; 
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-card',
  standalone: true,
  imports: [CommonModule, QRCodeComponent], 
  templateUrl: './card.component.html',
  styleUrl: './card.component.css'
})
export class CardComponent implements OnInit { 
  private auth = inject(AuthService);
  private http = inject(HttpClient);
  private cdr = inject(ChangeDetectorRef);

  member: any = null;
  qrCodeUrl: string = '';
  readonly storageUrl = environment.storageUrl;

  ngOnInit(): void {
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    this.http.get(`${environment.apiUrl}/member/profile`, { headers }).subscribe({
      next: (data: any) => {
        this.member = data;
        this.cdr.detectChanges();
        // Si le membre a un token, on crée le lien de vérification
        if (this.member.qr_token) {
          this.qrCodeUrl = `${environment.frontendUrl}/verification/${this.member.qr_token}`;
        } else {
          // Sinon, on met un texte par défaut pour forcer le QR Code à s'afficher
          this.qrCodeUrl = 'CARTE_EN_ATTENTE_DE_VALIDATION'; 
        }
      },
      error: (err) => {
        console.error('Erreur lors de la récupération du profil du membre:', err);
      }
    });
  }
} // <-- L'accolade fermante manquante a été ajoutée ici
