import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { MemberApplication, MemberStatus } from './member.model';

@Injectable({ providedIn: 'root' })
export class MemberService {
  // 1. Injection du client HTTP pour Laravel
  private http = inject(HttpClient);
  private apiUrl = 'http://127.0.0.1:8000/api';

  private readonly key = 'unjci_members';

  // =========================================================================
  // NOUVELLE MÉTHODE LARAVEL
  // =========================================================================
  
  submitApplication(formData: FormData): Observable<any> {
    return this.http.post(`${this.apiUrl}/members/apply`, formData);
  }

  // =========================================================================
  // ANCIENNES MÉTHODES LOCALSTORAGE (Conservées pour ne rien casser ailleurs)
  // =========================================================================

  getAll(): MemberApplication[] {
    return JSON.parse(localStorage.getItem(this.key) || '[]');
  }

  getLatest(): MemberApplication | undefined {
    return this.getAll().at(-1);
  }

  getByToken(token: string): MemberApplication | undefined {
    return this.getAll().find(member => member.qrToken === token.trim());
  }

  getById(id: string): MemberApplication | undefined {
    return this.getAll().find(member => member.id === id);
  }

  getByLogin(login: string): MemberApplication | undefined {
    const normalizedLogin = login.trim().toLowerCase();
    return this.getAll().find(member => member.login?.trim().toLowerCase() === normalizedLogin);
  }

  save(application: Omit<MemberApplication, 'id' | 'memberNumber' | 'qrToken' | 'status' | 'createdAt'>): MemberApplication {
    const members = this.getAll();
    const sequence = String(members.length + 1).padStart(5, '0');
    const member: MemberApplication = {
      ...application,
      id: crypto.randomUUID(),
      memberNumber: `UNJCI-${new Date().getFullYear()}-${sequence}`,
      qrToken: crypto.randomUUID().replaceAll('-', ''),
      status: 'EN_ATTENTE',
      createdAt: new Date().toISOString()
    };
    members.push(member);
    localStorage.setItem(this.key, JSON.stringify(members));
    return member;
  }

  activate(id: string): MemberApplication | undefined {
    return this.updateStatus(id, 'ACTIVE');
  }

  updateStatus(id: string, status: MemberStatus): MemberApplication | undefined {
    const members = this.getAll();
    const member = members.find(item => item.id === id);
    if (!member) return undefined;
    member.status = status;
    localStorage.setItem(this.key, JSON.stringify(members));
    return member;
  }

  updateProfile(
    id: string,
    changes: Partial<Pick<MemberApplication, 'phone' | 'personalEmail' | 'postalAddress' | 'employers' | 'functionTitle'>>
  ): MemberApplication | undefined {
    const members = this.getAll();
    const member = members.find(item => item.id === id);
    if (!member) return undefined;
    Object.assign(member, changes);
    localStorage.setItem(this.key, JSON.stringify(members));
    return member;
  }

  updatePaymentProof(
    id: string,
    paymentPhone: string,
    transactionId: string,
  ): MemberApplication | undefined {
    const members = this.getAll();
    const member = members.find(item => item.id === id);
    if (!member) return undefined;
    member.paymentPhone = paymentPhone.trim();
    member.transactionId = transactionId.trim();
    localStorage.setItem(this.key, JSON.stringify(members));
    return member;
  }
}