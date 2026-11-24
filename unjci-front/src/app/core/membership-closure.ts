import { Directive, ElementRef, Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpInterceptorFn } from '@angular/common/http';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of, tap, throwError, timeout } from 'rxjs';
import { environment } from '../../environments/environment';

export interface MembershipAvailability {
  requests_closed: boolean;
  closure_message: string;
}

@Injectable({ providedIn: 'root' })
export class MembershipClosure {
  private readonly http = inject(HttpClient);
  readonly visible = signal(false);
  readonly closed = signal(true);
  readonly message = signal('FIN DES DEMANDES D’ADHÉSION ET DE RENOUVELLEMENT DES CARTES UNJCI SESSION 2026');

  apply(status: MembershipAvailability): void {
    this.closed.set(status.requests_closed);
    this.message.set(status.closure_message);
    if (!status.requests_closed) this.visible.set(false);
  }

  refresh() {
    return this.http.get<MembershipAvailability>(`${environment.apiUrl}/membership/availability`).pipe(
      timeout(10000),
      tap(status => this.apply(status)),
      catchError(() => {
        const status = { requests_closed: true, closure_message: 'Impossible de vérifier l’ouverture des demandes. Réessayez dans quelques instants.' };
        this.apply(status);
        return of(status);
      }),
    );
  }

  show(): void { this.visible.set(true); }
}

@Directive({
  selector: 'a[appMembershipAction], button[appMembershipAction]',
  host: {
    '[class.membership-closed-action]': 'closure.closed()',
    '(click)': 'onClick($event)',
  },
})
export class MembershipActionDirective {
  protected readonly closure = inject(MembershipClosure);
  private readonly element = inject<ElementRef<HTMLElement>>(ElementRef);
  private checking = false;

  onClick(event: Event): void {
    if (!this.closure.closed()) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (this.checking) return;
    this.checking = true;
    this.closure.refresh().subscribe(status => {
      this.checking = false;
      if (status.requests_closed) this.closure.show();
      else this.element.nativeElement.click();
    });
  }
}

export const membershipClosedGuard: CanActivateFn = () => {
  const closure = inject(MembershipClosure);
  const router = inject(Router);
  return closure.refresh().pipe(map(status => {
    if (!status.requests_closed) return true;
    closure.show();
    return router.createUrlTree(['/']);
  }));
};

export const membershipClosureInterceptor: HttpInterceptorFn = (request, next) => {
  const closure = inject(MembershipClosure);
  return next(request).pipe(catchError(error => {
    if (error.status === 403 && error.error?.code === 'membership_closed') {
      closure.apply({ requests_closed: true, closure_message: error.error.message });
      closure.show();
    }
    return throwError(() => error);
  }));
};
