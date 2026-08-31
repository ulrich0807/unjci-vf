import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../../environments/environment';

type PaymentMode = 'adhesion' | 'renewal';
type MembershipStage = 'awaiting_payment' | 'payment_pending' | 'under_review' | 'approved' | 'rejected';

const MEMBERSHIP_STAGE_COPY: Record<MembershipStage, { label: string; description: string }> = {
  awaiting_payment: {
    label: 'En attente de paiement',
    description: 'Effectuez le paiement depuis votre espace personnel pour poursuivre votre demande.',
  },
  payment_pending: {
    label: 'Paiement en cours de confirmation',
    description: 'Votre paiement a été transmis. Sa confirmation est en cours.',
  },
  under_review: {
    label: 'Dossier en cours de traitement',
    description: 'Votre paiement est confirmé et votre demande est examinée par l’UNJCI.',
  },
  approved: {
    label: 'Adhésion approuvée',
    description: 'Votre adhésion est validée. Votre numéro UNJCI est disponible dans votre espace.',
  },
  rejected: {
    label: 'Demande non approuvée',
    description: 'Votre demande nécessite une régularisation. Contactez l’UNJCI pour connaître la suite à donner.',
  },
};

@Component({
  selector: 'app-member-dashboard',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './member-dashboard.component.html',
  styleUrl: './member-dashboard.component.css'
})
export class MemberDashboard implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  private http = inject(HttpClient);
  private fb = inject(FormBuilder);

  expectedAmount = 10000;
  paymentMode: PaymentMode = 'adhesion';
  membershipStage: MembershipStage = 'awaiting_payment';
  savingPayment = false;
  paymentError = '';
  
  // 1. On injecte le détecteur de changement
  private cdr = inject(ChangeDetectorRef);

  member: any = null;
  isLoading = true;
  
  editing = false;
  saved = false;
  addingPaymentProof = false;
  paymentSaved = false;

  selectedProfileFiles: { photo?: File; pressCardRecto?: File; pressCardVerso?: File } = {};
  savingProfile = false;
  profileError = '';


  profileForm: FormGroup;
  paymentProofForm: FormGroup;
  passwordForm: FormGroup;
  changingPassword = false;
  passwordSaved = false;
  passwordError = '';

  oldCardForm: FormGroup;
  uploadingOldCards = false;
  oldCardsUploaded = false;
  oldCardsError = '';
  selectedOldCards: { recto?: File; verso?: File } = {};
  
  history: any[] = [];

  readonly membershipSteps: ReadonlyArray<{ stage: Exclude<MembershipStage, 'rejected'>; label: string }> = [
    { stage: 'awaiting_payment', label: 'En attente de paiement' },
    { stage: 'payment_pending', label: 'Paiement en confirmation' },
    { stage: 'under_review', label: 'Dossier en validation' },
    { stage: 'approved', label: 'Approuvé' },
  ];

  constructor() {
    this.profileForm = this.fb.group({
      phone: [''],
      personalEmail: [''],
      postalAddress: [''],
      employers: [''],
      functionTitle: ['']
    });

    this.paymentProofForm = this.fb.group({
      paymentPhone: ['', Validators.required],
      transactionId: ['', Validators.required],
    });

    this.passwordForm = this.fb.group({
      currentPassword: ['', Validators.required],
      newPassword: ['', [Validators.required, Validators.minLength(8)]],
      confirmPassword: ['', Validators.required],
    });

    this.oldCardForm = this.fb.group({
      oldCardRecto: [''],
      oldCardVerso: [''],
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

    this.http.get(`${environment.apiUrl}/member/profile`, { headers }).subscribe({
      next: (data: any) => {
        // 1. On mappe les données
        this.member = {
          ...data,
          firstName: data.first_name,
          lastName: data.last_name,
          memberNumber: data.member_number,
          proposedMemberNumber: data.current_member_number,
          requestType: data.request_type,
          photoDataUrl: data.photo_file_path ? `${environment.storageUrl}/${data.photo_file_path}` : null,
          paymentPhone: data.payment_phone,
          transactionId: data.transaction_id,
          old_card_recto_path: data.old_card_recto_path,
          old_card_verso_path: data.old_card_verso_path,
        };

        // 2. On remplit le formulaire de profil
        this.profileForm.patchValue({
          phone: data.phone,
          personalEmail: data.personal_email,
          postalAddress: data.postal_address,
          employers: data.employers,
          functionTitle: data.function_title
        });
        
        this.history = data.payments || [];
        this.paymentMode = this.resolvePaymentMode(data);
        this.membershipStage = this.resolveMembershipStage(data, this.history);

        this.expectedAmount = this.amountFor(this.paymentMode);
        
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

  onProfileFileSelected(event: Event, type: 'photo' | 'pressCardRecto' | 'pressCardVerso'): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
      this.selectedProfileFiles[type] = file;
    }
  }

  saveProfile(): void {
    if (this.profileForm.invalid) {
      this.profileForm.markAllAsTouched();
      return;
    }

    this.savingProfile = true;
    this.profileError = '';
    
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });
    const payload = new FormData();

    const formValues = this.profileForm.getRawValue();
    Object.keys(formValues).forEach(key => {
      if (formValues[key]) payload.append(key, formValues[key]);
    });

    if (this.selectedProfileFiles.photo) payload.append('photoFile', this.selectedProfileFiles.photo);
    if (this.selectedProfileFiles.pressCardRecto) payload.append('pressCardRectoFile', this.selectedProfileFiles.pressCardRecto);
    if (this.selectedProfileFiles.pressCardVerso) payload.append('pressCardVersoFile', this.selectedProfileFiles.pressCardVerso);

    this.http.post(`${environment.apiUrl}/member/profile/update`, payload, { headers }).subscribe({
      next: (res: any) => {
        this.savingProfile = false;
        this.editing = false;
        this.saved = true;
        this.selectedProfileFiles = {};
        
        // Mettre à jour l'affichage avec la nouvelle photo si présente
        if (res.data?.photo_file_path) {
          this.member.photoDataUrl = `${environment.storageUrl}/${res.data.photo_file_path}`;
        }
        
        setTimeout(() => {
          this.saved = false;
          this.cdr.detectChanges();
        }, 3000);
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.savingProfile = false;
        this.profileError = err.error?.message || 'Erreur lors de la mise à jour du profil.';
        this.cdr.detectChanges();
      }
    });
  }

  changePassword(): void {
    this.passwordError = '';
    this.passwordSaved = false;
    if (this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const value = this.passwordForm.getRawValue();
    if (value.newPassword !== value.confirmPassword) {
      this.passwordError = 'Les deux nouveaux mots de passe ne correspondent pas.';
      return;
    }

    this.changingPassword = true;
    this.auth.changePassword({
      current_password: value.currentPassword,
      password: value.newPassword,
      password_confirmation: value.confirmPassword,
    }).subscribe({
      next: () => {
        this.changingPassword = false;
        this.passwordSaved = true;
        this.passwordForm.reset();
        this.cdr.detectChanges();
      },
      error: error => {
        this.changingPassword = false;
        this.passwordError = error.error?.errors?.current_password?.[0]
          || error.error?.errors?.password?.[0]
          || 'Le mot de passe n’a pas pu être modifié.';
        this.cdr.detectChanges();
      },
    });
  }

  startPayment(): void {
    this.paymentMode = this.resolvePaymentMode(this.member);
    this.expectedAmount = this.amountFor(this.paymentMode);
    this.paymentError = '';
    this.paymentProofForm.reset();
    this.addingPaymentProof = true;
  }

  cancelPayment(): void {
    this.addingPaymentProof = false;
    this.paymentError = '';
    this.paymentProofForm.reset();
  }

  savePaymentProof(): void {
    this.paymentError = '';
    if (this.paymentProofForm.invalid) {
      this.paymentProofForm.markAllAsTouched();
      return;
    }

    this.savingPayment = true;
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    const payload = new FormData();
    payload.append('paymentPhone', this.paymentProofForm.value.paymentPhone || '');
    payload.append('transactionId', this.paymentProofForm.value.transactionId || '');
    payload.append('paymentType', this.paymentMode);

    this.http.post(`${environment.apiUrl}/member/payment`, payload, { headers }).subscribe({
      next: (res: any) => {
        this.addingPaymentProof = false;
        this.savingPayment = false;
        this.paymentSaved = true;
        
        // On ajoute directement le nouveau paiement à l'historique visuel
        this.history.unshift(res.payment);
        this.membershipStage = 'payment_pending';
        this.cancelPayment();

        setTimeout(() => {
          this.paymentSaved = false;
          this.cdr.detectChanges();
        }, 4000);
        
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Erreur lors de l\'enregistrement', err);
        this.paymentError = err.error?.message || 'Le paiement n’a pas pu être transmis.';
        this.savingPayment = false;
      }
    });
  }

  get isMissingOldCards(): boolean {
    return this.paymentMode === 'renewal' && (!this.member?.old_card_recto_path || !this.member?.old_card_verso_path);
  }

  scrollToOldCards(): void {
    const el = document.getElementById('anciennes-cartes');
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  onOldCardSelected(event: Event, type: 'recto' | 'verso'): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
      this.selectedOldCards[type] = file;
      this.oldCardForm.get(type === 'recto' ? 'oldCardRecto' : 'oldCardVerso')?.setValue(file.name);
    }
  }

  submitOldCards(): void {
    if (!this.selectedOldCards.recto && !this.selectedOldCards.verso) {
      this.oldCardsError = 'Veuillez sélectionner au moins un fichier.';
      return;
    }

    this.uploadingOldCards = true;
    this.oldCardsError = '';
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });
    const payload = new FormData();

    if (this.selectedOldCards.recto) {
      payload.append('oldCardRectoFile', this.selectedOldCards.recto);
    }
    if (this.selectedOldCards.verso) {
      payload.append('oldCardVersoFile', this.selectedOldCards.verso);
    }

    this.http.post(`${environment.apiUrl}/member/upload-old-cards`, payload, { headers }).subscribe({
      next: (res: any) => {
        this.uploadingOldCards = false;
        this.oldCardsUploaded = true;
        if (res.data) {
          this.member.old_card_recto_path = res.data.old_card_recto_path;
          this.member.old_card_verso_path = res.data.old_card_verso_path;
        }
        this.selectedOldCards = {};
        this.oldCardForm.reset();
        
        setTimeout(() => {
          this.oldCardsUploaded = false;
          this.cdr.detectChanges();
        }, 4000);
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.uploadingOldCards = false;
        this.oldCardsError = err.error?.message || 'Erreur lors de l\'envoi des fichiers.';
        this.cdr.detectChanges();
      }
    });
  }

  get membershipStageLabel(): string {
    return MEMBERSHIP_STAGE_COPY[this.membershipStage].label;
  }

  get membershipStageDescription(): string {
    return MEMBERSHIP_STAGE_COPY[this.membershipStage].description;
  }

  get hasMemberNumber(): boolean {
    return Boolean(this.member?.member_number);
  }

  get proposedMemberNumber(): string {
    if (this.hasMemberNumber) return '';
    return String(this.member?.current_member_number || this.member?.proposedMemberNumber || '').trim();
  }

  get paymentActionAvailable(): boolean {
    return !['payment_pending', 'under_review', 'rejected'].includes(this.membershipStage);
  }

  isCurrentMembershipStep(stage: Exclude<MembershipStage, 'rejected'>): boolean {
    return this.membershipStage === stage;
  }

  isCompletedMembershipStep(stage: Exclude<MembershipStage, 'rejected'>): boolean {
    if (this.membershipStage === 'rejected') return false;

    const currentIndex = this.membershipSteps.findIndex(item => item.stage === this.membershipStage);
    const stepIndex = this.membershipSteps.findIndex(item => item.stage === stage);
    return currentIndex > stepIndex;
  }

  private resolvePaymentMode(member: any): PaymentMode {
    const requestType = String(member?.request_type || member?.requestType || '').toLowerCase();
    if (requestType === 'renewal') return 'renewal';
    if (requestType === 'adhesion') return 'adhesion';

    return member?.member_number ? 'renewal' : 'adhesion';
  }

  private amountFor(mode: PaymentMode): number {
    return mode === 'renewal' ? 5000 : 10000;
  }

  private resolveMembershipStage(member: any, payments: any[]): MembershipStage {
    const reportedStage = String(member?.membership_stage || '').toLowerCase();
    const memberStatus = String(member?.status || '').toLowerCase();
    if (reportedStage === 'rejected' || memberStatus === 'rejected') return 'rejected';

    const latestPayment = payments[0];
    if (latestPayment?.status === 'pending') return 'payment_pending';
    if (this.isMembershipStage(reportedStage)) return reportedStage;

    const submittedAt = this.toTimestamp(member?.application_submitted_at);
    const approvedAt = this.toTimestamp(member?.approved_at);
    if (submittedAt !== null && (approvedAt === null || submittedAt > approvedAt)) return 'under_review';

    if (memberStatus === 'approved' || memberStatus === 'active' || approvedAt !== null) return 'approved';
    if (latestPayment?.status === 'approved') return 'under_review';

    return 'awaiting_payment';
  }

  private isMembershipStage(value: string): value is MembershipStage {
    return ['awaiting_payment', 'payment_pending', 'under_review', 'approved', 'rejected'].includes(value);
  }

  private toTimestamp(value: unknown): number | null {
    if (!value) return null;
    const timestamp = Date.parse(String(value));
    return Number.isNaN(timestamp) ? null : timestamp;
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
