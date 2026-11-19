import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { VerifyComponent } from './verify.component';

describe('Public card verification', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({
      imports: [VerifyComponent],
      providers: [
        provideHttpClient(), provideHttpClientTesting(), provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ token: 'public-qr' }) } } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('shows a valid card and its photo without a session or login redirect', () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate');
    const fixture = TestBed.createComponent(VerifyComponent);
    fixture.detectChanges();
    const request = http.expectOne(`${environment.apiUrl}/verify-card/public-qr`);
    expect(request.request.headers.has('Authorization')).toBe(false);
    request.flush({ success: true, member: {
      first_name: 'Awa', last_name: 'Kouassi', member_number: 'UJ26-00001',
      membership_expires_at: '2030-12-31', has_photo: true,
    } });
    fixture.detectChanges();
    expect(navigate).not.toHaveBeenCalled();
    expect(fixture.nativeElement.textContent).toContain('CARTE AUTHENTIQUE');
    expect(fixture.nativeElement.textContent).toContain('UJ26-00001');
    const photo = fixture.nativeElement.querySelector('img');
    expect(photo.getAttribute('src')).toBe(`${environment.apiUrl}/verify-card/public-qr/photo`);
    photo.dispatchEvent(new Event('error'));
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('img')).toBeNull();
    expect(fixture.nativeElement.querySelector('.avatar').textContent).toContain('AK');
  });

  it.each([404, 422])('shows an invalid card for HTTP %s without requiring login', status => {
    const fixture = TestBed.createComponent(VerifyComponent);
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/verify-card/public-qr`)
      .flush({}, { status, statusText: 'Invalid card' });
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Vérification échouée');
    expect(fixture.nativeElement.textContent).not.toContain('CARTE AUTHENTIQUE');
    expect(fixture.nativeElement.textContent).not.toContain('Reconnectez-vous');
  });
});
