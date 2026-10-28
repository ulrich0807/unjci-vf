import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class MemberService {
  // 1. Injection du client HTTP pour Laravel
  private http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;



  // =========================================================================
  // NOUVELLE MÉTHODE LARAVEL
  // =========================================================================
  
  submitApplication(formData: FormData): Observable<any> {
    return this.http.post(`${this.apiUrl}/members/apply`, formData);
  }

  getMemberByCardNumber(cardNumber: string): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/members/by-card/${encodeURIComponent(cardNumber)}`);
  }

  sendEmailVerificationOtp(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/members/email-verification/send`, { email });
  }

  verifyEmailOtp(email: string, code: string): Observable<{ success: boolean; message: string; member_id: number | null }> {
    return this.http.post<{ success: boolean; message: string; member_id: number | null }>(
      `${this.apiUrl}/members/email-verification/verify`,
      { email, code },
    );
  }

}
