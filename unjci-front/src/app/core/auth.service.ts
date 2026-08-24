import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { tap } from 'rxjs/operators'; // Permet d'exécuter une action (sauvegarder la session) lors de la réponse

export type UserRole = 'member' | 'admin' | 'scanner' | 'media_admin';

export interface UserSession {
  login: string;
  role: UserRole;
  memberId?: string;
  token?: string; // Prévision pour le jeton de sécurité de Laravel
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  // Injections pour communiquer avec l'API
  private http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;
  
  private readonly key = 'unjci_session';

  // Nouvelle méthode de connexion avec Laravel
  login(credentials: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/login`, credentials).pipe(
      // 'tap' permet de lire la réponse de Laravel et de stocker la session avant de l'envoyer au composant
      tap((response: any) => {
        const session: UserSession = {
          login: String(credentials.login || response.user.login),
          role: response.user.role,
          token: response.token 
        };
        sessionStorage.setItem(this.key, JSON.stringify(session));
      })
    );
  }

  forgotPassword(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/forgot-password`, { email });
  }

  changePassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Observable<{ message: string }> {
    const token = this.getSession()?.token;
    const headers = new HttpHeaders({ Authorization: `Bearer ${token}` });
    return this.http.put<{ message: string }>(`${this.apiUrl}/member/password`, payload, { headers });
  }

  getSession(): UserSession | null {
    try {
      return JSON.parse(sessionStorage.getItem(this.key) || 'null');
    } catch {
      return null;
    }
  }

  logout(): void {
    sessionStorage.removeItem(this.key);
  }
}
