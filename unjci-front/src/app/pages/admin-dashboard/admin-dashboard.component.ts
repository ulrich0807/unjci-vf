import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { HttpClient, HttpHeaders } from '@angular/common/http';

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

  members: any[] = [];
  filteredMembers: any[] = [];
  pendingPayments: any[] = [];

  allPayments: any[] = [];
  filteredPayments: any[] = [];
  
  paymentTypeFilter = '';
  paymentStatusFilter = '';
  totalPaymentsAmount = 0;
  
  search = '';
  statusFilter = '';
  scannerVisible = false;

  ngOnInit(): void {
    this.loadAdminData();
  }

  // Charge tous les membres et les paiements en attente depuis Laravel
loadAdminData(): void {
    const session = this.auth.getSession();
    if (!session || session.role !== 'admin') {
      this.logout();
      return;
    }

    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session.token}` });

    this.http.get('http://127.0.0.1:8000/api/admin/dashboard', { headers }).subscribe({
      next: (data: any) => {
        this.members = data.members;
        this.allPayments = data.payments; 
        
        // On conserve la liste des "En attente" pour la section d'alerte en haut
        this.pendingPayments = this.allPayments.filter(p => p.status === 'pending'); 
        
        this.filterMembers();
        this.filterPayments(); // On lance le filtre des paiements
      },
      error: (err) => console.error('Erreur de chargement des données admin', err)
    });
  }

  // Filtrage local du tableau
  filterMembers(): void {
    const term = this.search.toLowerCase().trim();
    this.filteredMembers = this.members.filter(m => {
      const matchSearch = m.first_name.toLowerCase().includes(term) || 
                          m.last_name.toLowerCase().includes(term) || 
                          (m.member_number && m.member_number.toLowerCase().includes(term));
      const matchStatus = this.statusFilter ? m.status === this.statusFilter : true;
      return matchSearch && matchStatus;
    });
  }

  // Compteur pour les métriques du haut
  count(status: string): number {
    return this.members.filter(m => m.status === status).length;
  }

  // Mettre à jour le statut d'un membre (Appel API)
  setStatus(member: any, newStatus: string): void {
    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    this.http.put(`http://127.0.0.1:8000/api/admin/members/${member.id}/status`, { status: newStatus }, { headers })
      .subscribe({
        next: () => {
          member.status = newStatus;
          this.filterMembers();
        },
        error: (err) => console.error('Erreur lors de la mise à jour du statut', err)
      });
  }

  // Valider ou rejeter un paiement Wave (Appel API)
  validatePayment(paymentId: number, status: 'approved' | 'rejected'): void {
    if (!confirm(`Voulez-vous vraiment ${status === 'approved' ? 'approuver' : 'rejeter'} ce paiement ?`)) return;

    const session = this.auth.getSession();
    const headers = new HttpHeaders({ 'Authorization': `Bearer ${session?.token}` });

    this.http.put(`http://127.0.0.1:8000/api/admin/payments/${paymentId}/validate`, { status }, { headers })
      .subscribe({
        next: () => {
          // On recharge les données pour mettre à jour les tableaux
          this.loadAdminData();
        },
        error: (err) => console.error('Erreur lors de la validation du paiement', err)
      });
  }

  getPhotoUrl(path: string): string {
    return `http://127.0.0.1:8000/storage/${path}`;
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
      // Filtre sur le statut (pending, approved, rejected)
      const matchStatus = this.paymentStatusFilter ? p.status === this.paymentStatusFilter : true;
      
      // Filtre sur le type (Adhésion ou Renouvellement, qui se trouve dans la table member)
      const reqType = p.member?.request_type ? p.member.request_type.toLowerCase() : '';
      const matchType = this.paymentTypeFilter ? reqType.includes(this.paymentTypeFilter) : true;

      return matchStatus && matchType;
    });

    // Recalcul du montant total affiché à l'écran
    this.totalPaymentsAmount = this.filteredPayments.reduce((sum, p) => sum + Number(p.amount), 0);
  }
}