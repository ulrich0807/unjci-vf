import { Directive, Injectable, inject, signal } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

@Injectable({ providedIn: 'root' })
export class MembershipClosure {
  readonly visible = signal(false);

  show(): void {
    this.visible.set(true);
  }
}

@Directive({
  selector: 'button[appMembershipClosed]',
  host: {
    class: 'membership-closed-action',
    '(click)': 'closure.show()',
  },
})
export class MembershipClosedDirective {
  protected readonly closure = inject(MembershipClosure);
}

export const membershipClosedGuard: CanActivateFn = () => {
  inject(MembershipClosure).show();
  return inject(Router).createUrlTree(['/']);
};
