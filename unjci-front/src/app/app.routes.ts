import { Routes } from '@angular/router';
import { ApplicationComponent } from './pages/application/application.component';
import { AdhesionGuideComponent } from './pages/adhesion-guide/adhesion-guide.component';
import { CardComponent } from './pages/card/card.component';
import { VerifyComponent } from './pages/verify/verify.component';
import { HomeComponent } from './pages/home/home.component';
import { LoginComponent } from './pages/login/login.component';
import { ForgotPasswordComponent } from './pages/forgot-password/forgot-password.component';
import { EmailVerificationComponent } from './pages/email-verification/email-verification.component';
import { RenewalEntryComponent } from './pages/renewal-entry/renewal-entry.component';
import { MemberDashboard } from './pages/member-dashboard/member-dashboard.component';
import { AdminDashboard } from './pages/admin-dashboard/admin-dashboard.component';
import { ContactComponent } from './pages/contact/contact.component';
import { Scanner } from './pages/scanner/scanner';
import { roleGuard } from './core/auth.guard';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'application', component: ApplicationComponent },
  { path: 'guide-adhesion', component: AdhesionGuideComponent },
  { path: 'login', component: LoginComponent },
  { path: 'mot-de-passe-oublie', component: ForgotPasswordComponent },
  { path: 'verifier-email', component: EmailVerificationComponent },
  { path: 'renouvellement', component: RenewalEntryComponent },
  { path: 'espace-membre', component: MemberDashboard, canActivate: [roleGuard('member')] },
  { path: 'ma-carte', component: CardComponent },
  { path: 'nous-contacter', component: ContactComponent },
  {
    path: 'administration',
    loadComponent: () => import('./pages/admin-dashboard/admin-dashboard.component').then(m => m.AdminDashboard),
    canActivate: [roleGuard('admin')] 
  },

{ path: 'scanner', component: Scanner, canActivate: [roleGuard(['admin', 'scanner'])] },
  { path: 'verification/:token', component: VerifyComponent }, // <-- Placée AVANT le filet de sécurité

  // La route '**' DOIT impérativement être la dernière !
  { path: '**', redirectTo: '' } 
];