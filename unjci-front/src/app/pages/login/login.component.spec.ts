import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { environment } from '../../../environments/environment';
import { LoginComponent } from './login.component';

describe('LoginComponent', () => {
  let fixture: ComponentFixture<LoginComponent>;
  let component: LoginComponent;
  let httpTesting: HttpTestingController;
  let routeQueryParams: Record<string, string>;

  beforeEach(async () => {
    routeQueryParams = {};
    await TestBed.configureTestingModule({
      imports: [LoginComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: ActivatedRoute,
          useFactory: () => ({
            snapshot: {
              queryParamMap: convertToParamMap(routeQueryParams),
              queryParams: routeQueryParams,
            },
          }),
        },
      ],
    }).compileComponents();

    httpTesting = TestBed.inject(HttpTestingController);
  });

  function createComponent(): void {
    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }

  afterEach(() => {
    httpTesting.verify();
    sessionStorage.clear();
  });

  it('explains when too many login attempts have been made', () => {
    createComponent();
    component.form.setValue({
      login: 'UJ26-00001',
      password: 'password123',
      rememberMe: false,
    });

    component.submit();

    const request = httpTesting.expectOne(`${environment.apiUrl}/login`);
    expect(request.request.method).toBe('POST');
    request.flush(
      { message: 'Too Many Attempts.' },
      { status: 429, statusText: 'Too Many Requests' },
    );
    fixture.detectChanges();

    expect(component.authenticationError).toBe(true);
    expect(component.authenticationErrorMessage).toContain('Réessayez dans une minute');
    expect(fixture.nativeElement.textContent).toContain('Trop de tentatives de connexion');
  });

  it('prefills the registration email and labels the identifier for both login phases', () => {
    routeQueryParams = {
      inscription: 'reussie',
      email: 'journaliste@example.com',
    };
    createComponent();

    expect(component.registrationComplete).toBe(true);
    expect(component.form.controls.login.value).toBe('journaliste@example.com');
    expect(fixture.nativeElement.textContent).toContain('Adresse e-mail ou numéro UNJCI');
    expect(fixture.nativeElement.textContent).toContain('procéder au paiement');
  });
});
