import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { ApplicationComponent } from './application.component';

describe('ApplicationComponent', () => {
  let httpTesting: HttpTestingController;
  let routeQueryParams: Record<string, string>;

  beforeEach(() => {
    routeQueryParams = {};
    TestBed.configureTestingModule({
      imports: [ApplicationComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: {
                get: (key: string) => routeQueryParams[key] ?? null,
              },
            },
          },
        },
      ],
    });

    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpTesting.verify());

  it('uses adhesion mode by default and does not display a member-number field', () => {
    const fixture = TestBed.createComponent(ApplicationComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();
    httpTesting.expectOne(request => request.url.endsWith('/press-media')).flush({ data: [] });

    expect(component.applicationMode).toBe('adhesion');
    expect(component.isRenewal).toBe(false);
    expect(component.form.controls.currentMemberNumber.hasError('required')).toBe(false);
    expect(fixture.nativeElement.querySelector('[formControlName="currentMemberNumber"]')).toBeNull();
  });

  it('continues after the professional step without checking the email remotely', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    component.currentStep = 2;
    component.form.patchValue({
      professionalStatus: 'Journaliste mensualisé (CDI/CDD)',
      employers: 'GROUPE RTI',
      mediaSelection: 'Numérique|||RTI1',
      mediaName: 'RTI1',
      mediaType: 'Numérique',
      functionTitle: 'Reporter',
      pressCardNumber: '1234JP',
      pressCardExpiry: '2026-12-31',
      email: 'journaliste@example.com',
    });

    component.nextStep();

    expect(component.currentStep).toBe(3);
    httpTesting.expectNone(request => request.url.includes('/members/check-email'));
  });

  it('loads the companies and offers the GROUPE RTI media returned by the API', () => {
    const fixture = TestBed.createComponent(ApplicationComponent);
    const component = fixture.componentInstance;

    fixture.detectChanges();

    const request = httpTesting.expectOne(request =>
      request.method === 'GET' && request.url.endsWith('/press-media'),
    );
    expect(component.loadingPressMedia).toBe(true);
    request.flush({
      data: [
        { id: 1, companyId: 10, company: 'GROUPE RTI', name: 'RTI1', type: 'Numérique' },
        { id: 2, companyId: 10, company: 'GROUPE RTI', name: 'RTI2', type: 'Numérique' },
        { id: 3, companyId: 10, company: 'GROUPE RTI', name: 'RTI BOUAKE', type: 'Numérique' },
        { id: 4, companyId: 20, company: 'AUTRE GROUPE', name: 'AUTRE MÉDIA', type: 'Écrit' },
      ],
    });

    component.selectCompany('GROUPE RTI');
    const rtiMedia = component.filteredMediaOptions.map(item => item.name);

    expect(rtiMedia).toEqual(['RTI1', 'RTI2', 'RTI BOUAKE']);
    expect(component.companyOptions).toEqual(['AUTRE GROUPE', 'GROUPE RTI']);
    expect(component.loadingPressMedia).toBe(false);
    expect(component.pressMediaError).toBe('');

    component.selectMedia(component.filteredMediaOptions[0]);
    component.form.controls.employers.setValue('AUTRE GROUPE');
    component.onCompanyInput();

    expect(component.mediaSearch).toBe('');
    expect(component.form.controls.mediaSelection.value).toBe('');
    expect(component.form.controls.mediaName.value).toBe('');
    expect(component.form.controls.mediaType.value).toBe('');
  });

  it('does not leave the professional step while the catalog is unavailable', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    component.currentStep = 2;
    component.form.patchValue({
      professionalStatus: 'Journaliste mensualisé (CDI/CDD)',
      employers: 'GROUPE RTI',
      mediaSelection: 'Numérique|||RTI1',
      mediaName: 'RTI1',
      mediaType: 'Numérique',
      functionTitle: 'Reporter',
      pressCardNumber: '1234JP',
      pressCardExpiry: '2026-12-31',
      email: 'journaliste@example.com',
    });

    component.loadingPressMedia = true;
    component.nextStep();
    expect(component.currentStep).toBe(2);

    component.loadingPressMedia = false;
    component.pressMediaError = 'Catalogue indisponible';
    component.nextStep();
    expect(component.currentStep).toBe(2);
  });

  it('shows a catalog error and retries the request', () => {
    const fixture = TestBed.createComponent(ApplicationComponent);
    const component = fixture.componentInstance;

    fixture.detectChanges();

    httpTesting.expectOne(request => request.url.endsWith('/press-media')).flush(
      { message: 'Server error' },
      { status: 500, statusText: 'Server Error' },
    );

    expect(component.loadingPressMedia).toBe(false);
    expect(component.pressMediaError).toContain('Impossible de charger');
    expect(component.pressMedia).toEqual([]);
    expect(component.companyOptions).toEqual([]);

    component.loadPressMedia();

    expect(component.loadingPressMedia).toBe(true);
    const retryRequest = httpTesting.expectOne(request => request.url.endsWith('/press-media'));
    retryRequest.flush({
      data: [{ id: 1, companyId: 10, company: 'GROUPE RTI', name: 'RTI1', type: 'Numérique' }],
    });

    expect(component.loadingPressMedia).toBe(false);
    expect(component.pressMediaError).toBe('');
    expect(component.companyOptions).toEqual(['GROUPE RTI']);
  });

  it('explains when the database catalog is empty', () => {
    const fixture = TestBed.createComponent(ApplicationComponent);
    const component = fixture.componentInstance;

    fixture.detectChanges();

    httpTesting.expectOne(request => request.url.endsWith('/press-media')).flush({ data: [] });

    expect(component.loadingPressMedia).toBe(false);
    expect(component.pressMediaError).toContain('Aucune entreprise');
    expect(component.pressMedia).toEqual([]);
    expect(component.companyOptions).toEqual([]);
  });

  it('keeps the member number optional and performs no lookup in renewal mode', () => {
    routeQueryParams['type'] = 'renewal';
    const fixture = TestBed.createComponent(ApplicationComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();
    httpTesting.expectOne(request => request.url.endsWith('/press-media')).flush({ data: [] });

    component.form.patchValue({
      currentMemberNumber: '',
      firstName: 'Awa',
      lastName: 'KOUASSI',
      birthDate: '01/01/1990',
      birthPlace: 'Abidjan',
      phone: '0102030405',
    }, { emitEvent: false });

    expect(component.form.controls.currentMemberNumber.hasError('required')).toBe(false);
    expect(fixture.nativeElement.textContent).toContain('(facultatif)');
    component.nextStep();
    expect(component.currentStep).toBe(2);
    httpTesting.expectNone(request => request.url.includes('/members/by-card/'));
  });

  it('validates the optional member number format without performing a lookup', () => {
    routeQueryParams['type'] = 'renewal';
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    component.form.patchValue({
      currentMemberNumber: 'INVALIDE',
      firstName: 'Awa',
      lastName: 'KOUASSI',
      birthDate: '01/01/1990',
      birthPlace: 'Abidjan',
      phone: '0102030405',
    }, { emitEvent: false });

    component.nextStep();

    expect(component.currentStep).toBe(1);
    expect(component.form.controls.currentMemberNumber.hasError('pattern')).toBe(true);
    httpTesting.expectNone(request => request.url.includes('/members/by-card/'));
  });

  it('blocks the identity step for whitespace, one-letter names, impossible dates and invalid phones', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    component.form.patchValue({
      currentMemberNumber: '',
      firstName: '   ',
      lastName: 'A\u0301',
      alias: 'X',
      birthDate: '31/02/2024',
      birthPlace: '123-45',
      phone: '+225 téléphone',
    }, { emitEvent: false });

    component.nextStep();

    expect(component.currentStep).toBe(1);
    expect(component.form.controls.firstName.hasError('required')).toBe(true);
    expect(component.form.controls.lastName.hasError('minimumLetters')).toBe(true);
    expect(component.form.controls.alias.hasError('minlength')).toBe(true);
    expect(component.form.controls.birthDate.hasError('invalidDate')).toBe(true);
    expect(component.form.controls.birthPlace.hasError('minimumLetters')).toBe(true);
    expect(component.form.controls.phone.hasError('phoneFormat')).toBe(true);
    expect(component.form.controls.alias.touched).toBe(true);
  });

  it('accepts and trims reasonable Unicode identity values', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    component.form.patchValue({
      currentMemberNumber: '',
      firstName: '  Élise-Marie  ',
      lastName: '  N’GUESSAN  ',
      alias: '  La Plume  ',
      birthDate: '  29/02/2000  ',
      birthPlace: '  Abidjan   2  ',
      phone: '  +225 (07) 08-09-10-11  ',
    }, { emitEvent: false });

    component.nextStep();

    expect(component.currentStep).toBe(2);
    expect(component.form.controls.firstName.value).toBe('Élise-Marie');
    expect(component.form.controls.lastName.value).toBe('N’GUESSAN');
    expect(component.form.controls.alias.value).toBe('La Plume');
    expect(component.form.controls.birthDate.value).toBe('29/02/2000');
    expect(component.form.controls.birthPlace.value).toBe('Abidjan 2');
    expect(component.form.controls.phone.value).toBe('+225 (07) 08-09-10-11');
  });

  it('validates calendar dates, future dates and phone digit boundaries synchronously', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    const birthDate = component.form.controls.birthDate;
    const phone = component.form.controls.phone;

    birthDate.setValue('29/02/2023');
    expect(birthDate.hasError('invalidDate')).toBe(true);

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const futureDate = [
      String(tomorrow.getDate()).padStart(2, '0'),
      String(tomorrow.getMonth() + 1).padStart(2, '0'),
      String(tomorrow.getFullYear()).padStart(4, '0'),
    ].join('/');
    birthDate.setValue(futureDate);
    expect(birthDate.hasError('futureDate')).toBe(true);

    birthDate.setValue('29/02/2024');
    expect(birthDate.valid).toBe(true);

    phone.setValue('01 23 45 67');
    expect(phone.valid).toBe(true);
    phone.setValue('+225 (01) 23-45-67-89');
    expect(phone.valid).toBe(true);
    phone.setValue('1234567');
    expect(phone.hasError('phoneDigitCount')).toBe(true);
    phone.setValue('1234567890123456');
    expect(phone.hasError('phoneDigitCount')).toBe(true);
    phone.setValue('+225 (01 23-45-67-89');
    expect(phone.hasError('phoneFormat')).toBe(true);
  });

  it('formats the birth date and requires a complete email domain', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    const input = document.createElement('input');
    input.value = '01011990';

    component.onBirthDateInput({ target: input } as unknown as Event);

    expect(input.value).toBe('01/01/1990');
    expect(component.form.controls.birthDate.value).toBe('01/01/1990');

    component.form.controls.email.setValue('test@tes');
    expect(component.form.controls.email.hasError('pattern')).toBe(true);

    component.form.controls.email.setValue('test@tes.com');
    expect(component.form.controls.email.valid).toBe(true);
  });

  it('exposes mobile-friendly identity attributes and precise validation messages', () => {
    const fixture = TestBed.createComponent(ApplicationComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();
    httpTesting.expectOne(request => request.url.endsWith('/press-media')).flush({ data: [] });

    const element = fixture.nativeElement as HTMLElement;
    const firstName = element.querySelector<HTMLInputElement>('[formControlName="firstName"]');
    const birthDate = element.querySelector<HTMLInputElement>('[formControlName="birthDate"]');
    const phone = element.querySelector<HTMLInputElement>('[formControlName="phone"]');

    expect(firstName?.maxLength).toBe(100);
    expect(firstName?.autocomplete).toBe('given-name');
    expect(birthDate?.maxLength).toBe(10);
    expect(birthDate?.getAttribute('inputmode')).toBe('numeric');
    expect(birthDate?.placeholder).toBe('00/00/0000');
    expect(phone?.type).toBe('tel');
    expect(phone?.getAttribute('inputmode')).toBe('tel');
    expect(phone?.autocomplete).toBe('tel');

    component.form.patchValue({
      firstName: 'A1B',
      lastName: 'A\u0301',
      alias: 'X',
      birthDate: '31/02/2024',
      birthPlace: '1234',
      phone: 'abc',
    });
    component.nextStep();
    fixture.detectChanges();

    expect(element.textContent).toContain('Les prénoms ne peuvent contenir que des lettres');
    expect(element.textContent).toContain('Le nom doit contenir au moins 2 vraies lettres');
    expect(element.textContent).toContain('Saisissez une date de naissance réelle');
    expect(element.textContent).toContain('Le numéro de téléphone');
  });

  it('maps backend identity validation errors to their controls and returns to step one', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;

    Object.values(component.form.controls).forEach(control => {
      control.clearValidators();
      control.updateValueAndValidity({ emitEvent: false });
    });
    component.form.clearValidators();
    component.form.updateValueAndValidity({ emitEvent: false });
    component.currentStep = 5;

    component.submit();

    const submitRequest = httpTesting.expectOne(request =>
      request.method === 'POST' && request.url.endsWith('/members/apply'),
    );
    submitRequest.flush(
      {
        errors: {
          firstName: ['Le prénom contient des caractères non autorisés.'],
          phone: ['Le numéro doit contenir entre 8 et 15 chiffres.'],
        },
      },
      { status: 422, statusText: 'Unprocessable Content' },
    );

    expect(component.currentStep).toBe(1);
    expect(component.form.controls.firstName.getError('server')).toContain('caractères non autorisés');
    expect(component.form.controls.phone.getError('server')).toContain('entre 8 et 15 chiffres');
    expect(component.form.controls.firstName.touched).toBe(true);
    expect(component.form.controls.phone.touched).toBe(true);
    expect(component.submissionError).toContain('Le prénom contient');
    expect(component.submissionError).toContain('Le numéro doit contenir');
  });

  it('submits an adhesion without a member number and redirects to an email-prefilled login', () => {
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    const router = TestBed.inject(Router);
    const navigate = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    Object.values(component.form.controls).forEach(control => {
      control.clearValidators();
      control.updateValueAndValidity({ emitEvent: false });
    });
    component.form.clearValidators();
    component.form.controls.email.setValue('journaliste@example.com');
    component.form.controls.currentMemberNumber.setValue('UJ25-00122', { emitEvent: false });
    component.form.updateValueAndValidity({ emitEvent: false });
    component.currentStep = 5;

    component.submit();

    const submitRequest = httpTesting.expectOne(request =>
      request.method === 'POST' && request.url.endsWith('/members/apply'),
    );
    const body = submitRequest.request.body as FormData;
    expect(body.get('requestType')).toBe('adhesion');
    expect(body.has('currentMemberNumber')).toBe(false);
    submitRequest.flush({ success: true });

    expect(navigate).toHaveBeenCalledWith(['/login'], {
      queryParams: {
        inscription: 'reussie',
        email: 'journaliste@example.com',
      },
    });
  });

  it('submits a renewal with an empty optional member number and performs no lookup', () => {
    routeQueryParams['type'] = 'renewal';
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;
    const router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);

    Object.values(component.form.controls).forEach(control => {
      control.clearValidators();
      control.updateValueAndValidity({ emitEvent: false });
    });
    component.form.clearValidators();
    component.form.controls.email.setValue('journaliste@example.com');
    component.form.controls.currentMemberNumber.setValue('', { emitEvent: false });
    component.form.updateValueAndValidity({ emitEvent: false });
    component.currentStep = 5;

    component.submit();

    const submitRequest = httpTesting.expectOne(request =>
      request.method === 'POST' && request.url.endsWith('/members/apply'),
    );
    const body = submitRequest.request.body as FormData;
    expect(body.get('requestType')).toBe('renewal');
    expect(body.has('currentMemberNumber')).toBe(false);
    httpTesting.expectNone(request => request.url.includes('/members/by-card/'));
    submitRequest.flush({ success: true });
  });

  it('submits the requested number and maps a 422 conflict back to its field', () => {
    routeQueryParams['type'] = 'renewal';
    const component = TestBed.createComponent(ApplicationComponent).componentInstance;

    Object.values(component.form.controls).forEach(control => {
      control.clearValidators();
      control.updateValueAndValidity({ emitEvent: false });
    });
    component.form.clearValidators();
    component.form.controls.currentMemberNumber.setValue('UJ25-00122', { emitEvent: false });
    component.form.updateValueAndValidity({ emitEvent: false });
    component.currentStep = 5;

    component.submit();

    const submitRequest = httpTesting.expectOne(request =>
      request.method === 'POST' && request.url.endsWith('/members/apply'),
    );
    expect(submitRequest.request.body).toBeInstanceOf(FormData);
    expect((submitRequest.request.body as FormData).get('currentMemberNumber')).toBe('UJ25-00122');
    expect((submitRequest.request.body as FormData).get('requestType')).toBe('renewal');

    submitRequest.flush(
      { errors: { currentMemberNumber: ['Ce numéro est déjà utilisé.'] } },
      { status: 422, statusText: 'Unprocessable Content' },
    );

    expect(component.currentStep).toBe(1);
    expect(component.form.controls.currentMemberNumber.hasError('server')).toBe(true);
    expect(component.memberNumberError).toBe('Ce numéro est déjà utilisé.');
    expect(component.submissionError).toBe(component.memberNumberError);
  });
});
