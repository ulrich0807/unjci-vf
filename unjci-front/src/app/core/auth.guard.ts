import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, UserRole } from './auth.service';

export const roleGuard = (expectedRole: UserRole): CanActivateFn => {
  return () => {
    const auth = inject(AuthService);
    const router = inject(Router);
    const session = auth.getSession();

    // 1. Si l'utilisateur n'est pas connecté du tout
    if (!session) {
      return router.createUrlTree(['/login']);
    }

    // 2. Si le rôle de l'utilisateur ne correspond pas au rôle attendu par la route
    if (session.role !== expectedRole) {
      return router.createUrlTree([
        session.role === 'admin' ? '/administration' : '/espace-membre'
      ]);
    }

    // 3. Tout est bon, on autorise l'accès
    return true;
  };
};