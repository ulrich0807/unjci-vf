import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, UserRole } from './auth.service';

export const roleGuard = (expectedRoles: UserRole | UserRole[]): CanActivateFn => {
  return () => {
    const auth = inject(AuthService);
    const router = inject(Router);
    const session = auth.getSession();

    // 1. Si l'utilisateur n'est pas connecté du tout
    if (!session) {
      return router.createUrlTree(['/login']);
    }

    // 2. Transformer expectedRoles en tableau pour gérer tous les cas
    const rolesToCheck = Array.isArray(expectedRoles) ? expectedRoles : [expectedRoles];

    // 3. Si le rôle de l'utilisateur n'est pas dans la liste attendue par la route
    if (!rolesToCheck.includes(session.role as UserRole)) {
      return router.createUrlTree([
        session.role === 'admin' ? '/administration' : '/espace-membre'
      ]);
    }

    // 4. Tout est bon, on autorise l'accès
    return true;
  };
};