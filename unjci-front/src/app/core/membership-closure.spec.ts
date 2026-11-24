import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, provideRouter, Router, RouterStateSnapshot, UrlTree } from '@angular/router';
import { firstValueFrom, Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { MembershipClosure, membershipClosedGuard, membershipClosureInterceptor } from './membership-closure';

describe('Membership availability', () => {
  let http: HttpTestingController;
  let closure: MembershipClosure;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [
      provideRouter([]), provideHttpClient(withInterceptors([membershipClosureInterceptor])), provideHttpClientTesting(),
    ] });
    http = TestBed.inject(HttpTestingController);
    closure = TestBed.inject(MembershipClosure);
  });
  afterEach(() => http.verify());

  it.each([false, true])('checks the current server status before allowing a direct link (closed: %s)', async closed => {
    closure.apply({ requests_closed: !closed, closure_message: 'Ancien état' });
    const result = TestBed.runInInjectionContext(() => membershipClosedGuard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot));
    const outcome = firstValueFrom(result as Observable<boolean | UrlTree>);
    http.expectOne(`${environment.apiUrl}/membership/availability`)
      .flush({ requests_closed: closed, closure_message: 'Demandes fermées' });
    const value = await outcome;
    expect(closure.closed()).toBe(closed);
    if (closed) {
      expect(TestBed.inject(Router).serializeUrl(value as UrlTree)).toBe('/');
      expect(closure.visible()).toBe(true);
    } else {
      expect(value).toBe(true);
      expect(closure.visible()).toBe(false);
    }
  });

  it('does not allow new requests when the availability check is unavailable', async () => {
    const result = firstValueFrom(closure.refresh());
    http.expectOne(`${environment.apiUrl}/membership/availability`).flush({}, { status: 503, statusText: 'Unavailable' });
    expect((await result).requests_closed).toBe(true);
    expect(closure.message()).toContain('Impossible de vérifier');
  });

  it('updates an already open page when the server rejects a new request after closure', () => {
    closure.apply({ requests_closed: false, closure_message: 'Demandes fermées' });
    TestBed.inject(HttpClient).post(`${environment.apiUrl}/members/apply`, {}).subscribe({ error: () => {} });
    http.expectOne(`${environment.apiUrl}/members/apply`).flush(
      { code: 'membership_closed', message: 'Demandes fermées' }, { status: 403, statusText: 'Closed' },
    );
    expect(closure.closed()).toBe(true);
    expect(closure.visible()).toBe(true);
    expect(closure.message()).toBe('Demandes fermées');
  });
});
