import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { Observable } from 'rxjs';
import {
  ManagedPressMedia,
  PressCompanyRecord,
  PressMediaService,
  PressMediaType,
} from '../../core/press-media.service';

export type LoginAuditStatusFilter = 'all' | 'success' | 'failure';

export interface LoginAuditRecord {
  id: number;
  login: string;
  userId: number | null;
  userName: string | null;
  userEmail: string | null;
  role: string | null;
  success: boolean;
  reason: string | null;
  ipAddress: string | null;
  userAgent: string | null;
  createdAt: string;
}

interface LoginAuditResponse {
  data: LoginAuditRecord[];
  meta: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
}

@Component({
  selector: 'app-admin-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './admin-dashboard.component.html',
  styleUrl: './admin-dashboard.component.css'
})
export class AdminDashboard implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  private http = inject(HttpClient);
  private pressMediaService = inject(PressMediaService);
  private cdr = inject(ChangeDetectorRef);

  members: any[] = [];
  filteredMembers: any[] = [];
  pendingPayments: any[] = [];
  memberNumberDrafts: Record<number, string> = {};
  memberActionErrors: Record<number, string> = {};
  memberActionInProgress: Record<number, boolean> = {};
  adminDataError = '';

  selectedMember: any = null;
  isEditingMember = false;
  editMemberData: any = {};
  editMemberError = '';
  editMemberSuccess = '';

  allPayments: any[] = [];
  filteredPayments: any[] = [];
  
  paymentTypeFilter = '';
  paymentStatusFilter = '';
  totalPaymentsAmount = 0;
  
  search = '';
  statusFilter = '';
  scannerVisible = false;

  pressCompanies: PressCompanyRecord[] = [];
  catalogSearch = '';
  catalogLoading = false;
  catalogAction = '';
  catalogError = '';
  catalogSuccess = '';
  catalogPage = 1;
  readonly catalogPageSize = 10;
  readonly mediaTypes: readonly PressMediaType[] = ['Écrit', 'Numérique'];
  readonly expandedCompanyIds = new Set<number>();

  loginAudits: LoginAuditRecord[] = [];
  loginAuditSearch = '';
  loginAuditStatus: LoginAuditStatusFilter = 'all';
  loginAuditsLoading = false;
  loginAuditsError = '';
  loginAuditPage = 1;
  loginAuditLastPage = 1;
  readonly loginAuditPerPage = 10;
  loginAuditTotal = 0;
  private loginAuditRequestId = 0;

  memberPage = 1;
  readonly memberPageSize = 10;

  paymentPage = 1;
  readonly paymentPageSize = 10;

  pendingPaymentPage = 1;
  readonly pendingPaymentPageSize = 10;

  contacts: any[] = [];
  contactPage = 1;
  readonly contactPageSize = 10;
  contactTotal = 0;
  contactsLoading = false;

  newCompanyName = '';
  newCompanyActive = true;
  newMediaCompanyId: number | null = null;
  newMediaName = '';
  newMediaType: PressMediaType = 'Numérique';
  newMediaActive = true;

  editingCompanyId: number | null = null;
  editCompanyName = '';
  editCompanyActive = true;
  editingMediaId: number | null = null;
  editMediaCompanyId: number | null = null;
  editMediaName = '';
  editMediaType: PressMediaType = 'Numérique';
  editMediaActive = true;

  ngOnInit(): void {
    this.loadAdminData();
    this.loadPressCompanies();
    this.loadLoginAudits();
    this.loadContacts();
  }

  // Charge tous les membres et les paiements en attente depuis Laravel
loadAdminData(): void {
    const session = this.auth.getSession();
    if (!session || session.role !== 'admin') {
      this.logout();
      return;
    }

    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session.token}` });
    this.adminDataError = '';

    this.http.get(`${environment.apiUrl}/admin/dashboard`, { headers }).subscribe({
      next: (data: any) => {
        this.members = data.members || [];
        this.allPayments = data.payments || [];

        for (const member of this.members) {
          if (this.memberNumberDrafts[member.id] === undefined) {
            this.memberNumberDrafts[member.id] = String(
              member.current_member_number || member.member_number || '',
            ).trim();
          }
        }
        
        // On conserve la liste des "En attente" pour la section d'alerte en haut
        this.pendingPayments = this.allPayments.filter(p => p.status === 'pending'); 
        
        this.filterMembers();
        this.filterPayments(); // On lance le filtre des paiements
        this.cdr.markForCheck();
      },
      error: (err) => {
        console.error('Erreur de chargement des données admin', err);
        this.adminDataError = err.error?.message
          || 'Impossible de charger les adhérents et les paiements.';
        this.cdr.markForCheck();
      }
    });
  }

  // Filtrage local du tableau
  filterMembers(): void {
    const term = this.search.toLowerCase().trim();
    this.filteredMembers = this.members.filter(m => {
      const matchSearch = m.first_name.toLowerCase().includes(term) || 
                          m.last_name.toLowerCase().includes(term) || 
                          (m.member_number && m.member_number.toLowerCase().includes(term)) ||
                          (m.current_member_number && m.current_member_number.toLowerCase().includes(term));
      const matchStatus = this.statusFilter ? m.status === this.statusFilter : true;
      return matchSearch && matchStatus;
    });
    this.memberPage = 1;
  }

  get paginatedMembers(): any[] {
    const startIndex = (this.memberPage - 1) * this.memberPageSize;
    return this.filteredMembers.slice(startIndex, startIndex + this.memberPageSize);
  }

  get memberLastPage(): number {
    return Math.max(1, Math.ceil(this.filteredMembers.length / this.memberPageSize));
  }

  goToMemberPage(page: number): void {
    if (page >= 1 && page <= this.memberLastPage) {
      this.memberPage = page;
    }
  }

  // Compteur pour les métriques du haut
  count(status: string): number {
    return this.members.filter(m => m.status === status).length;
  }

  countUnderReview(): number {
    return this.members.filter(m => m.membership_stage === 'under_review').length;
  }

  memberStageLabel(member: any): string {
    const labels: Record<string, string> = {
      awaiting_payment: 'Paiement requis',
      payment_pending: 'Paiement à confirmer',
      under_review: 'Dossier à valider',
      approved: 'Adhésion validée',
      rejected: 'Demande rejetée',
    };

    return labels[member.membership_stage] || member.status;
  }

  requiresMemberNumberVerification(member: any): boolean {
    return String(member?.request_type || '').toLowerCase() === 'renewal'
      && !member?.member_number;
  }

  onVerifiedMemberNumberInput(member: any, rawValue: string): void {
    this.memberNumberDrafts[member.id] = String(rawValue || '')
      .toUpperCase()
      .replace(/[^A-Z0-9-]/g, '')
      .slice(0, 10);
    delete this.memberActionErrors[member.id];
  }

  approvalActionLabel(member: any): string {
    if (this.requiresMemberNumberVerification(member)) {
      return 'Approuver et confirmer le numéro';
    }

    return member?.request_type === 'renewal'
      ? 'Approuver le renouvellement'
      : 'Approuver et attribuer le numéro';
  }

  // Mettre à jour le statut d'un membre (Appel API)
  setStatus(member: any, newStatus: string): void {
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });
    const payload: { status: string; verifiedMemberNumber?: string } = { status: newStatus };
    delete this.memberActionErrors[member.id];

    if (newStatus === 'approved' && this.requiresMemberNumberVerification(member)) {
      const verifiedMemberNumber = String(this.memberNumberDrafts[member.id] || '').trim();
      if (!verifiedMemberNumber) {
        this.memberActionErrors[member.id] = 'Le numéro UNJCI vérifié est requis pour approuver ce renouvellement.';
        return;
      }
      if (!/^UJ\d{2}-\d{5}$/.test(verifiedMemberNumber)) {
        this.memberActionErrors[member.id] = 'Format attendu : UJ25-00122.';
        return;
      }

      payload.verifiedMemberNumber = verifiedMemberNumber;
    }

    this.memberActionInProgress[member.id] = true;

    this.http.put(`${environment.apiUrl}/admin/members/${member.id}/status`, payload, { headers })
      .subscribe({
        next: (response: any) => {
          delete this.memberActionInProgress[member.id];
          Object.assign(member, response.member);
          this.memberNumberDrafts[member.id] = String(
            member.current_member_number || member.member_number || '',
          ).trim();
          this.filterMembers();
          this.cdr.markForCheck();
        },
        error: (err) => {
          console.error('Erreur lors de la mise à jour du statut', err);
          delete this.memberActionInProgress[member.id];
          const validationErrors = err.error?.errors as Record<string, string[]> | undefined;
          this.memberActionErrors[member.id] = validationErrors
            ? Object.values(validationErrors).flat().join(' ')
            : (err.error?.message || 'Le statut de cet adhérent n’a pas pu être mis à jour.');
        }
      });
  }
  // --- NOUVEAU : GESTION DES DÉTAILS D'UN ADHÉRENT ---
  openMemberDetails(member: any): void {
    this.selectedMember = member;
    this.isEditingMember = false;
    this.editMemberError = '';
    this.editMemberSuccess = '';
    // Pré-remplir les données d'édition
    this.editMemberData = {
      lastName: member.last_name,
      firstName: member.first_name,
      alias: member.alias || '',
      birthDate: member.birth_date,
      birthPlace: member.birth_place,
      phone: member.phone,
      postalAddress: member.postal_address || '',
      personalEmail: member.personal_email,
      professionalStatus: member.professional_status,
      employers: member.employers,
      mediaName: member.media_name,
      mediaType: member.media_type,
      functionTitle: member.function_title,
      pressCardNumber: member.press_card_number,
      pressCardExpiry: member.press_card_expiry,
      requestType: member.request_type,
      currentMemberNumber: member.current_member_number
    };
  }

  closeMemberDetails(): void {
    this.selectedMember = null;
    this.isEditingMember = false;
  }

  toggleEditMember(): void {
    this.isEditingMember = !this.isEditingMember;
    this.editMemberSuccess = '';
    this.editMemberError = '';
  }

  saveMemberDetails(): void {
    if (!this.selectedMember) return;
    
    this.editMemberError = '';
    this.editMemberSuccess = '';
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    this.http.put<{success: boolean, member: any, message: string}>(`${environment.apiUrl}/admin/members/${this.selectedMember.id}/details`, this.editMemberData, { headers })
      .subscribe({
        next: (response) => {
          this.editMemberSuccess = response.message || 'Modifications enregistrées.';
          this.isEditingMember = false;
          // Mettre à jour la liste locale
          Object.assign(this.selectedMember, response.member);
          this.filterMembers();
          this.cdr.markForCheck();
        },
        error: (err) => {
          console.error('Erreur lors de la sauvegarde', err);
          const validationErrors = err.error?.errors as Record<string, string[]> | undefined;
          this.editMemberError = validationErrors
            ? Object.values(validationErrors).flat().join(' ')
            : (err.error?.message || 'Impossible de sauvegarder les modifications.');
          this.cdr.markForCheck();
        }
      });
  }
  // --- FIN GESTION DES DÉTAILS ---

  // Valider ou rejeter un paiement Wave (Appel API)
  validatePayment(paymentId: number, status: 'approved' | 'rejected'): void {
    if (!confirm(`Voulez-vous vraiment ${status === 'approved' ? 'approuver' : 'rejeter'} ce paiement ?`)) return;

    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    this.http.put(`${environment.apiUrl}/admin/payments/${paymentId}/validate`, { status }, { headers })
      .subscribe({
        next: () => {
          // On recharge les données pour mettre à jour les tableaux
          this.loadAdminData();
        },
        error: (err) => console.error('Erreur lors de la validation du paiement', err)
      });
  }

  getPhotoUrl(path: string): string {
    return `${environment.storageUrl}/${path}`;
  }

  openScanner(): void {
    this.scannerVisible = true;
  }

  logout(): void {
    this.auth.logout();
    this.router.navigate(['/login']);
  }

  filterPayments(): void {
    this.filteredPayments = this.allPayments.filter(p => {
      const matchType = this.paymentTypeFilter ? p.payment_type === this.paymentTypeFilter : true;
      const matchStatus = this.paymentStatusFilter ? p.status === this.paymentStatusFilter : true;
      return matchType && matchStatus;
    });
    
    // Calculer le montant total filtré (seulement les approuvés ?)
    // Ou le total de tout ce qui est affiché. Ici on fait la somme de tous les éléments filtrés
    this.totalPaymentsAmount = this.filteredPayments.reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
    this.paymentPage = 1;
  }

  get paginatedPayments(): any[] {
    const startIndex = (this.paymentPage - 1) * this.paymentPageSize;
    return this.filteredPayments.slice(startIndex, startIndex + this.paymentPageSize);
  }

  get paymentLastPage(): number {
    return Math.max(1, Math.ceil(this.filteredPayments.length / this.paymentPageSize));
  }

  // Pagination pour les paiements en attente
  get paginatedPendingPayments(): any[] {
    const startIndex = (this.pendingPaymentPage - 1) * this.pendingPaymentPageSize;
    return this.pendingPayments.slice(startIndex, startIndex + this.pendingPaymentPageSize);
  }

  get pendingPaymentLastPage(): number {
    return Math.max(1, Math.ceil(this.pendingPayments.length / this.pendingPaymentPageSize));
  }

  goToPendingPaymentPage(page: number): void {
    if (page >= 1 && page <= this.pendingPaymentLastPage) {
      this.pendingPaymentPage = page;
    }
  }

  goToPaymentPage(page: number): void {
    if (page >= 1 && page <= this.paymentLastPage) {
      this.paymentPage = page;
    }
  }

  loadContacts(page = 1): void {
    const session = this.auth.getSession();
    if (!session || session.role !== 'admin') return;

    this.contactsLoading = true;
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session.token}` });
    
    let params = new HttpParams()
      .set('page', page.toString())
      .set('perPage', this.contactPageSize.toString());

    this.http.get<any>(`${environment.apiUrl}/admin/contacts`, { headers, params }).subscribe({
      next: (res) => {
        this.contacts = res.data;
        this.contactPage = res.current_page;
        this.contactTotal = res.total;
        this.contactsLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.contactsLoading = false;
        this.cdr.markForCheck();
      }
    });
  }

  get contactLastPage(): number {
    return Math.max(1, Math.ceil(this.contactTotal / this.contactPageSize));
  }
  
  goToContactPage(page: number): void {
    this.loadContacts(page);
  }

  loadLoginAudits(page = this.loginAuditPage): void {
    const session = this.auth.getSession();
    if (!session || session.role !== 'admin') {
      this.logout();
      return;
    }

    const requestedPage = Math.max(1, page);
    const requestId = ++this.loginAuditRequestId;
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session.token}` });
    const params = new HttpParams()
      .set('search', this.loginAuditSearch.trim())
      .set('status', this.loginAuditStatus)
      .set('page', String(requestedPage))
      .set('perPage', String(this.loginAuditPerPage));

    this.loginAuditsLoading = true;
    this.loginAuditsError = '';

    this.http.get<LoginAuditResponse>(`${environment.apiUrl}/admin/login-audits`, { headers, params }).subscribe({
      next: response => {
        if (requestId !== this.loginAuditRequestId) return;

        this.loginAudits = response.data || [];
        this.loginAuditPage = response.meta?.currentPage || requestedPage;
        this.loginAuditLastPage = Math.max(1, response.meta?.lastPage || 1);
        this.loginAuditTotal = response.meta?.total || 0;
        this.loginAuditsLoading = false;
        this.cdr.markForCheck();
      },
      error: error => {
        if (requestId !== this.loginAuditRequestId) return;

        this.loginAuditsLoading = false;
        this.loginAuditsError = error?.error?.message
          || 'Impossible de charger l’historique des connexions. Vérifiez votre connexion puis réessayez.';
      },
    });
  }

  applyLoginAuditFilters(): void {
    this.loginAuditPage = 1;
    this.loadLoginAudits(1);
  }

  goToLoginAuditPage(page: number): void {
    if (this.loginAuditsLoading || page < 1 || page > this.loginAuditLastPage || page === this.loginAuditPage) return;
    this.loadLoginAudits(page);
  }

  get loginAuditFirstItem(): number {
    return this.loginAuditTotal ? ((this.loginAuditPage - 1) * this.loginAuditPerPage) + 1 : 0;
  }

  get loginAuditLastItem(): number {
    return Math.min(this.loginAuditPage * this.loginAuditPerPage, this.loginAuditTotal);
  }

  browserLabel(userAgent: string | null): string {
    if (!userAgent) return 'Non renseigné';

    const browser = userAgent.includes('Edg/') ? 'Microsoft Edge'
      : userAgent.includes('OPR/') ? 'Opera'
      : userAgent.includes('Firefox/') ? 'Firefox'
      : userAgent.includes('Chrome/') ? 'Chrome'
      : userAgent.includes('Safari/') ? 'Safari'
      : 'Navigateur inconnu';
    const platform = /Android/i.test(userAgent) ? 'Android'
      : /iPhone|iPad/i.test(userAgent) ? 'iOS'
      : /Windows/i.test(userAgent) ? 'Windows'
      : /Macintosh|Mac OS/i.test(userAgent) ? 'macOS'
      : /Linux/i.test(userAgent) ? 'Linux'
      : '';

    return platform ? `${browser} · ${platform}` : browser;
  }

  loginAuditReasonLabel(reason: string | null): string {
    if (!reason) return '';

    const labels: Record<string, string> = {
      invalid_credentials: 'Identifiant ou mot de passe incorrect',
      email_verification_required: 'Adresse e-mail non vérifiée',
      account_disabled: 'Compte désactivé',
      account_not_found: 'Compte introuvable',
      too_many_attempts: 'Trop de tentatives de connexion',
    };

    return labels[reason] || reason;
  }

  get filteredPressCompanies(): PressCompanyRecord[] {
    const query = this.normalizeCatalogSearch(this.catalogSearch);
    if (!query) return this.pressCompanies;

    return this.pressCompanies.filter(company =>
      this.normalizeCatalogSearch(company.name).includes(query)
      || company.media.some(media => this.normalizeCatalogSearch(media.name).includes(query))
    );
  }

  get paginatedPressCompanies(): PressCompanyRecord[] {
    const firstIndex = (this.catalogPage - 1) * this.catalogPageSize;
    return this.filteredPressCompanies.slice(firstIndex, firstIndex + this.catalogPageSize);
  }

  get catalogTotalPages(): number {
    return Math.max(1, Math.ceil(this.filteredPressCompanies.length / this.catalogPageSize));
  }

  get catalogFirstItem(): number {
    return this.filteredPressCompanies.length ? ((this.catalogPage - 1) * this.catalogPageSize) + 1 : 0;
  }

  get catalogLastItem(): number {
    return Math.min(this.catalogPage * this.catalogPageSize, this.filteredPressCompanies.length);
  }

  onCatalogSearchChange(value: string): void {
    this.catalogSearch = value;
    this.catalogPage = 1;
  }

  goToCatalogPage(page: number): void {
    if (page < 1 || page > this.catalogTotalPages || page === this.catalogPage) return;
    this.catalogPage = page;
  }

  trackPressCompany(_index: number, company: PressCompanyRecord): number {
    return company.id;
  }

  loadPressCompanies(preserveSuccess = false): void {
    this.catalogPage = 1;
    this.catalogLoading = true;
    this.catalogError = '';
    if (!preserveSuccess) this.catalogSuccess = '';

    this.pressMediaService.getAdminCompanies().subscribe({
      next: companies => {
        this.pressCompanies = companies;
        this.catalogLoading = false;
        this.catalogAction = '';

        if (this.newMediaCompanyId === null || !companies.some(company => company.id === this.newMediaCompanyId)) {
          this.newMediaCompanyId = companies[0]?.id ?? null;
        }
        this.cdr.markForCheck();
      },
      error: error => {
        this.catalogLoading = false;
        this.catalogAction = '';
        this.catalogError = this.catalogErrorMessage(error, 'Impossible de charger les entreprises et les médias.');
      },
    });
  }

  createPressCompany(): void {
    const name = this.newCompanyName.trim();
    if (!name || this.catalogAction) return;

    this.runCatalogAction(
      'create-company',
      this.pressMediaService.createCompany({ name, isActive: this.newCompanyActive }),
      'Entreprise ajoutée avec succès.',
      () => {
        this.newCompanyName = '';
        this.newCompanyActive = true;
      },
    );
  }

  startCompanyEdit(company: PressCompanyRecord): void {
    this.editingCompanyId = company.id;
    this.editCompanyName = company.name;
    this.editCompanyActive = company.isActive;
    this.catalogError = '';
    this.catalogSuccess = '';
  }

  cancelCompanyEdit(): void {
    this.editingCompanyId = null;
    this.editCompanyName = '';
  }

  saveCompany(company: PressCompanyRecord): void {
    const name = this.editCompanyName.trim();
    if (!name || this.catalogAction) return;

    this.runCatalogAction(
      `company-${company.id}`,
      this.pressMediaService.updateCompany(company.id, { name, isActive: this.editCompanyActive }),
      'Entreprise modifiée avec succès.',
      () => this.cancelCompanyEdit(),
    );
  }

  toggleCompanyActive(company: PressCompanyRecord): void {
    if (this.catalogAction) return;

    this.runCatalogAction(
      `company-${company.id}`,
      this.pressMediaService.updateCompany(company.id, {
        name: company.name,
        isActive: !company.isActive,
      }),
      company.isActive
        ? 'Entreprise désactivée. Ses médias ne sont plus proposés aux adhérents.'
        : 'Entreprise activée avec succès.',
    );
  }

  deleteCompany(company: PressCompanyRecord): void {
    if (this.catalogAction || !confirm(`Supprimer l'entreprise « ${company.name} » ?`)) return;

    this.runCatalogAction(
      `company-${company.id}`,
      this.pressMediaService.deleteCompany(company.id),
      'Entreprise supprimée avec succès.',
      () => {
        this.expandedCompanyIds.delete(company.id);
        if (this.editingCompanyId === company.id) this.cancelCompanyEdit();
      },
    );
  }

  createPressMedia(): void {
    const companyId = this.newMediaCompanyId;
    const name = this.newMediaName.trim();
    if (companyId === null || !name || this.catalogAction) return;

    this.runCatalogAction(
      `create-media-${companyId}`,
      this.pressMediaService.createMedia(companyId, {
        name,
        type: this.newMediaType,
        isActive: this.newMediaActive,
      }),
      'Média ajouté avec succès.',
      () => {
        this.newMediaName = '';
        this.newMediaType = 'Numérique';
        this.newMediaActive = true;
        this.expandedCompanyIds.add(companyId);
      },
    );
  }

  startMediaEdit(company: PressCompanyRecord, media: ManagedPressMedia): void {
    this.editingMediaId = media.id;
    this.editMediaCompanyId = company.id;
    this.editMediaName = media.name;
    this.editMediaType = media.type;
    this.editMediaActive = media.isActive;
    this.catalogError = '';
    this.catalogSuccess = '';
  }

  cancelMediaEdit(): void {
    this.editingMediaId = null;
    this.editMediaCompanyId = null;
    this.editMediaName = '';
  }

  saveMedia(media: ManagedPressMedia): void {
    const companyId = this.editMediaCompanyId;
    const name = this.editMediaName.trim();
    if (companyId === null || !name || this.catalogAction) return;

    this.runCatalogAction(
      `media-${media.id}`,
      this.pressMediaService.updateMedia(media.id, {
        pressCompanyId: companyId,
        name,
        type: this.editMediaType,
        isActive: this.editMediaActive,
      }),
      'Média modifié avec succès.',
      () => {
        this.expandedCompanyIds.add(companyId);
        this.cancelMediaEdit();
      },
    );
  }

  toggleMediaActive(company: PressCompanyRecord, media: ManagedPressMedia): void {
    if (this.catalogAction) return;

    this.runCatalogAction(
      `media-${media.id}`,
      this.pressMediaService.updateMedia(media.id, {
        pressCompanyId: company.id,
        name: media.name,
        type: media.type,
        isActive: !media.isActive,
      }),
      media.isActive ? 'Média désactivé.' : 'Média activé avec succès.',
    );
  }

  deleteMedia(media: ManagedPressMedia): void {
    if (!confirm(`Supprimer définitivement le média "${media.name}" ?`)) return;

    this.catalogAction = 'delete-media';
    this.pressMediaService.deleteMedia(media.id).subscribe({
      next: () => {
        this.loadPressCompanies(true);
        this.catalogSuccess = 'Média supprimé avec succès.';
      },
      error: () => {
        this.catalogAction = '';
        this.catalogError = 'Impossible de supprimer le média.';
        this.cdr.markForCheck();
      }
    });
  }

  startEditingCompany(company: PressCompanyRecord, event?: Event): void {
    if (event) event.stopPropagation();
    this.editingCompanyId = company.id;
    this.editCompanyName = company.name;
    this.editCompanyActive = company.isActive;
  }

  cancelEditingCompany(): void {
    this.editingCompanyId = null;
  }

  saveCompany(): void {
    if (!this.editingCompanyId) return;
    this.catalogAction = 'edit-company';
    this.pressMediaService.updateCompany(this.editingCompanyId, {
      name: this.editCompanyName,
      isActive: this.editCompanyActive
    }).subscribe({
      next: () => {
        this.editingCompanyId = null;
        this.loadPressCompanies(true);
        this.catalogSuccess = 'Entreprise mise à jour.';
      },
      error: () => {
        this.catalogAction = '';
        this.catalogError = 'Erreur lors de la mise à jour de l\'entreprise.';
        this.cdr.markForCheck();
      }
    });
  }

  toggleCompanyStatus(company: PressCompanyRecord, event?: Event): void {
    if (event) event.stopPropagation();
    this.pressMediaService.updateCompany(company.id, { name: company.name, isActive: !company.isActive }).subscribe({
      next: () => this.loadPressCompanies(true),
      error: () => this.catalogError = 'Erreur de changement de statut.'
    });
  }

  deleteCompany(company: PressCompanyRecord, event?: Event): void {
    if (event) event.stopPropagation();
    if (!confirm(`Supprimer définitivement l'entreprise "${company.name}" et tous ses médias ?`)) return;
    
    this.catalogAction = 'delete-company';
    this.pressMediaService.deleteCompany(company.id).subscribe({
      next: () => {
        this.loadPressCompanies(true);
        this.catalogSuccess = 'Entreprise supprimée avec succès.';
      },
      error: () => {
        this.catalogAction = '';
        this.catalogError = 'Erreur lors de la suppression de l\'entreprise.';
        this.cdr.markForCheck();
      }
    });
  }

  startEditingMedia(media: ManagedPressMedia, companyId: number): void {
    this.editingMediaId = media.id;
    this.editMediaCompanyId = companyId;
    this.editMediaName = media.name;
    this.editMediaType = media.type;
    this.editMediaActive = media.isActive;
  }

  cancelEditingMedia(): void {
    this.editingMediaId = null;
  }

  saveMedia(): void {
    if (!this.editingMediaId || !this.editMediaCompanyId) return;
    this.catalogAction = 'edit-media';
    this.pressMediaService.updateMedia(this.editingMediaId, {
      pressCompanyId: this.editMediaCompanyId,
      name: this.editMediaName,
      type: this.editMediaType,
      isActive: this.editMediaActive
    }).subscribe({
      next: () => {
        this.editingMediaId = null;
        this.loadPressCompanies(true);
        this.catalogSuccess = 'Média mis à jour.';
      },
      error: () => {
        this.catalogAction = '';
        this.catalogError = 'Erreur lors de la mise à jour du média.';
        this.cdr.markForCheck();
      }
    });
  }

  toggleMediaStatus(media: ManagedPressMedia, companyId: number): void {
    this.pressMediaService.updateMedia(media.id, {
      pressCompanyId: companyId,
      name: media.name,
      type: media.type,
      isActive: !media.isActive
    }).subscribe({
      next: () => this.loadPressCompanies(true),
      error: () => this.catalogError = 'Erreur de changement de statut.'
    });
  }

  toggleCompanyDetails(companyId: number): void {
    if (this.expandedCompanyIds.has(companyId)) {
      this.expandedCompanyIds.delete(companyId);
      return;
    }
    this.expandedCompanyIds.add(companyId);
  }

  companyDetailsVisible(companyId: number): boolean {
    return !!this.catalogSearch.trim() || this.expandedCompanyIds.has(companyId);
  }

  private runCatalogAction(
    action: string,
    request: Observable<unknown>,
    successMessage: string,
    afterSuccess?: () => void,
  ): void {
    this.catalogAction = action;
    this.catalogError = '';
    this.catalogSuccess = '';

    request.subscribe({
      next: () => {
        this.catalogSuccess = successMessage;
        afterSuccess?.();
        this.loadPressCompanies(true);
      },
      error: error => {
        this.catalogAction = '';
        this.catalogError = this.catalogErrorMessage(error, 'L\'opération n\'a pas pu être effectuée.');
      },
    });
  }

  private catalogErrorMessage(error: any, fallback: string): string {
    if (error?.status === 409) {
      return 'Cette entreprise contient encore des médias. Supprimez-les ou réaffectez-les avant de supprimer l\'entreprise.';
    }

    const validationErrors = error?.error?.errors as Record<string, string[]> | undefined;
    if (validationErrors) {
      return Object.values(validationErrors).flat().join(' ');
    }

    return error?.error?.message || fallback;
  }

  private normalizeCatalogSearch(value: string): string {
    return value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }
}
