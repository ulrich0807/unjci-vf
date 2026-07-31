import { Routes } from '@angular/router';
import { ApplicationComponent } from './pages/application/application.component';
import { CardComponent } from './pages/card/card.component';
import { VerifyComponent } from './pages/verify/verify.component';
import { HomeComponent } from './pages/home/home.component';
import { LoginComponent } from './pages/login/login.component';
import { MemberDashboard } from './pages/member-dashboard/member-dashboard.component';
import { AdminDashboard } from './pages/admin-dashboard/admin-dashboard.component';
import { ContactComponent } from './pages/contact/contact.component';
import { roleGuard } from './core/auth.guard';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'application', component: ApplicationComponent },
  { path: 'login', component: LoginComponent },
  { path: 'espace-membre', component: MemberDashboard, canActivate: [roleGuard('member')] },
  { path: 'carte', component: CardComponent },
  { path: 'verification', component: VerifyComponent, canActivate: [roleGuard('admin')] },
  { path: 'verification/:token', component: VerifyComponent, canActivate: [roleGuard('admin')] },
  { path: 'nous-contacter', component: ContactComponent },
  {
    path: 'administration',
    loadComponent: () => import('./pages/admin-dashboard/admin-dashboard.component').then(m => m.AdminDashboard),
    canActivate: [roleGuard('admin')] 
  },
  // La route '**' DOIT impérativement être la dernière !
  { path: '**', redirectTo: '' } 
];