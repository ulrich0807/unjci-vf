import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators'; // Permet d'exécuter une action (sauvegarder la session) lors de la réponse

export type UserRole = 'member' | 'admin';

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
  private apiUrl = 'http://127.0.0.1:8000/api';
  
  private readonly key = 'unjci_session';

  // Nouvelle méthode de connexion avec Laravel
  login(credentials: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/login`, credentials).pipe(
      // 'tap' permet de lire la réponse de Laravel et de stocker la session avant de l'envoyer au composant
      tap((response: any) => {
        const session: UserSession = {
          login: response.user.login,
          role: response.user.role,
          token: response.token 
        };
        sessionStorage.setItem(this.key, JSON.stringify(session));
      })
    );
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