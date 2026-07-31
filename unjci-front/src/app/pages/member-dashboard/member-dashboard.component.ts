import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { HttpClient, HttpHeaders } from '@angular/common/http';

@Component({
  selector: 'app-member-dashboard',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './member-dashboard.component.html',
  styleUrl: './member-dashboard.component.css'
})
export class MemberDashboard implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  private http = inject(HttpClient);
  private fb = inject(FormBuilder);

  // Ajoute ces variables au début de ta classe
  expectedAmount: number = 10000;
  hasPendingPayment = false;
  savingPayment = false;
  
  // 1. On injecte le détecteur de changement
  private cdr = inject(ChangeDetectorRef);

  member: any = null;
  isLoading = true;
  
  editing = false;
  saved = false;
  addingPaymentProof = false;
  paymentSaved = false;

  profileForm: FormGroup;
  paymentProofForm: FormGroup;
  
  history: any[] = [];

  constructor() {
    this.profileForm = this.fb.group({
      phone: [''],
      personalEmail: [''],
      postalAddress: [''],
      employers: [''],
      functionTitle: ['']
    });

    this.paymentProofForm = this.fb.group({
      paymentPhone: [''],
      transactionId: ['']
    });
  }

  ngOnInit(): void {
    this.loadProfile();
  }

  loadProfile(): void {
    const session = this.auth.getSession();
    
    if (!session || !session.token) {
      this.logout();
      return;
    }

    const headers = new HttpHeaders({
      'Authorization': `Bearer ${session.token}`
    });

    this.http.get('http://127.0.0.1:8000/api/member/profile', { headers }).subscribe({
      next: (data: any) => {
        // 1. On mappe les données
        this.member = {
          ...data,
          firstName: data.first_name,
          lastName: data.last_name,
          memberNumber: data.member_number,
          photoDataUrl: data.photo_file_path ? `http://127.0.0.1:8000/storage/${data.photo_file_path}` : null,
          paymentPhone: data.payment_phone,
          transactionId: data.transaction_id,
        };

        // 2. On remplit le formulaire de profil
        this.profileForm.patchValue({
          phone: data.phone,
          personalEmail: data.personal_email,
          postalAddress: data.postal_address,
          employers: data.employers,
          functionTitle: data.function_title
        });
        
        // 3. ⚠️ LES NOUVELLES LIGNES DOIVENT ÊTRE ICI ! ⚠️
        this.expectedAmount = data.request_type === 'Renouvellement' ? 5000 : 10000;
        this.history = data.payments || [];
        this.hasPendingPayment = this.history.some((p: any) => p.status === 'pending');
        
        // 4. On arrête le chargement et on met à jour l'écran
        this.isLoading = false;
        this.cdr.detectChanges(); 
      },
      error: () => {
        console.error("Impossible de charger le profil");
        this.isLoading = false;
        
        // On force le rafraîchissement même en cas d'erreur
        this.cdr.detectChanges(); 
      }
    });
  }

  saveProfile(): void {
    this.editing = false;
    this.saved = true;
    setTimeout(() => {
      this.saved = false;
      this.cdr.detectChanges(); // Rafraîchissement après disparition du message
    }, 3000);
  }

savePaymentProof(): void {
    if (this.paymentProofForm.invalid) return;

    this.savingPayment = true;
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    const payload = {
      ...this.paymentProofForm.value,
      amount: this.expectedAmount
    };

    this.http.post('http://127.0.0.1:8000/api/member/payment', payload, { headers }).subscribe({
      next: (res: any) => {
        this.addingPaymentProof = false;
        this.savingPayment = false;
        this.paymentSaved = true;
        
        // On ajoute directement le nouveau paiement à l'historique visuel
        this.history.unshift(res.payment);
        this.hasPendingPayment = true;
        this.paymentProofForm.reset();

        setTimeout(() => {
          this.paymentSaved = false;
          this.cdr.detectChanges();
        }, 4000);
        
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Erreur lors de l\'enregistrement', err);
        this.savingPayment = false;
      }
    });
  }

  scrollToSection(sectionId: string): void {
    document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth' });
  }

  openProfile(): void {
    this.scrollToSection('profil');
    this.editing = true;
  }

  logout(): void {
    this.auth.logout();
    this.router.navigate(['/login']);
  }
}