import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { environment } from '../../../environments/environment';
import { PressCompanyRecord } from '../../core/press-media.service';
import { AdminDashboard, LoginAuditRecord } from './admin-dashboard.component';

describe('AdminDashboard', () => {
  let fixture: ComponentFixture<AdminDashboard>;
  let component: AdminDashboard;
  let httpTesting: HttpTestingController;

  const initialCompanies: PressCompanyRecord[] = [
    {
      id: 1,
      name: 'GROUPE RTI',
      isActive: true,
      media: [{ id: 11, name: 'RTI1', type: 'Numérique', isActive: true }],
    },
    ...Array.from({ length: 11 }, (_, index) => ({
      id: index + 2,
      name: `ENTREPRISE ${String(index + 2).padStart(2, '0')}`,
      isActive: true,
      media: [],
    })),
  ];

  beforeEach(async () => {
    sessionStorage.setItem('unjci_session', JSON.stringify({
      login: 'admin',
      role: 'admin',
      token: 'admin-token',
    }));

    await TestBed.configureTestingModule({
      imports: [AdminDashboard],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    httpTesting = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(AdminDashboard);
    component = fixture.componentInstance;
    fixture.detectChanges();

    httpTesting.expectOne(`${environment.apiUrl}/admin/dashboard`).flush({ members: [], payments: [] });
    httpTesting.expectOne(`${environment.apiUrl}/admin/press-companies`).flush({ data: initialCompanies });
    const loginAuditRequest = httpTesting.expectOne(request => request.url === `${environment.apiUrl}/admin/login-audits`);
    expect(loginAuditRequest.request.headers.get('Authorization')).toBe('Bearer admin-token');
    expect(loginAuditRequest.request.params.get('search')).toBe('');
    expect(loginAuditRequest.request.params.get('status')).toBe('all');
    expect(loginAuditRequest.request.params.get('page')).toBe('1');
    expect(loginAuditRequest.request.params.get('perPage')).toBe('25');
    loginAuditRequest.flush({
      data: [],
      meta: { currentPage: 1, lastPage: 1, perPage: 25, total: 0 },
    });
    await fixture.whenStable();
    fixture.detectChanges();
  });

  afterEach(() => {
    httpTesting.verify();
    sessionStorage.clear();
    vi.restoreAllMocks();
  });

  it('loads and displays the companies managed from the database', () => {
    expect(component.pressCompanies).toEqual(initialCompanies);
    expect(fixture.nativeElement.textContent).toContain('GROUPE RTI');
    expect(fixture.nativeElement.textContent).toContain('1 média');
  });

  it('prefills, edits and submits the verified number for a renewal without an official number', () => {
    const member = {
      id: 17,
      first_name: 'Awa',
      last_name: 'KOUASSI',
      personal_email: 'awa@example.ci',
      request_type: 'renewal',
      membership_stage: 'under_review',
      status: 'pending',
      member_number: null,
      current_member_number: 'UJ25-00122',
      created_at: '2026-08-12T10:00:00Z',
    };
    component.members = [member];
    component.memberNumberDrafts[member.id] = member.current_member_number;
    component.search = 'uj25-00122';
    component.filterMembers();
    expect(component.filteredMembers).toEqual([member]);
    fixture.detectChanges();

    const numberInput = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>(
      'input[aria-label="Numéro UNJCI vérifié pour Awa KOUASSI"]',
    );
    expect(numberInput?.value).toBe('UJ25-00122');
    expect(fixture.nativeElement.textContent).toContain('Déclaré — à vérifier');

    component.onVerifiedMemberNumberInput(member, 'uj26-00456');
    component.setStatus(member, 'approved');

    const request = httpTesting.expectOne(`${environment.apiUrl}/admin/members/17/status`);
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({
      status: 'approved',
      verifiedMemberNumber: 'UJ26-00456',
    });
    request.flush({
      member: {
        ...member,
        status: 'approved',
        membership_stage: 'approved',
        member_number: 'UJ26-00456',
        current_member_number: 'UJ26-00456',
      },
    });

    expect(member.member_number).toBe('UJ26-00456');
    expect(component.memberActionInProgress[member.id]).toBeUndefined();
  });

  it('requires a valid verified number and displays API validation errors on the member row', () => {
    const member = {
      id: 18,
      first_name: 'Jean',
      last_name: 'KOFFI',
      personal_email: 'jean@example.ci',
      request_type: 'renewal',
      membership_stage: 'under_review',
      status: 'pending',
      member_number: null,
      current_member_number: null,
      created_at: '2026-08-12T10:00:00Z',
    };
    component.members = [member];
    component.memberNumberDrafts[member.id] = '';
    component.filterMembers();

    component.setStatus(member, 'approved');
    expect(component.memberActionErrors[member.id]).toContain('requis');
    httpTesting.expectNone(`${environment.apiUrl}/admin/members/18/status`);

    component.onVerifiedMemberNumberInput(member, 'UJ26-00018');
    component.setStatus(member, 'approved');
    const request = httpTesting.expectOne(`${environment.apiUrl}/admin/members/18/status`);
    request.flush(
      { errors: { verifiedMemberNumber: ['Ce numéro est déjà utilisé par un autre membre.'] } },
      { status: 422, statusText: 'Unprocessable Content' },
    );
    fixture.detectChanges();

    expect(component.memberActionErrors[member.id]).toBe('Ce numéro est déjà utilisé par un autre membre.');
    expect(fixture.nativeElement.textContent).toContain('Ce numéro est déjà utilisé par un autre membre.');
  });

  it('creates a company and reloads only the catalog', () => {
    component.goToCatalogPage(2);
    expect(component.catalogPage).toBe(2);
    component.newCompanyName = 'Nouvelle entreprise';
    component.createPressCompany();

    const createRequest = httpTesting.expectOne(`${environment.apiUrl}/admin/press-companies`);
    expect(createRequest.request.method).toBe('POST');
    expect(createRequest.request.body).toEqual({ name: 'Nouvelle entreprise', isActive: true });
    createRequest.flush({ data: { id: 99, name: 'Nouvelle entreprise', isActive: true, media: [] } });

    const refreshRequest = httpTesting.expectOne(`${environment.apiUrl}/admin/press-companies`);
    expect(refreshRequest.request.method).toBe('GET');
    expect(component.catalogPage).toBe(1);
    refreshRequest.flush({
      data: [...initialCompanies, { id: 99, name: 'Nouvelle entreprise', isActive: true, media: [] }],
    });

    httpTesting.expectNone(`${environment.apiUrl}/admin/dashboard`);
    expect(component.catalogSuccess).toContain('Entreprise ajoutée');
    expect(component.newCompanyName).toBe('');
  });

  it('explains a conflict when deleting a company that still has media', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.deleteCompany(initialCompanies[0]);

    const deleteRequest = httpTesting.expectOne(`${environment.apiUrl}/admin/press-companies/1`);
    expect(deleteRequest.request.method).toBe('DELETE');
    deleteRequest.flush(
      { message: 'Cette entreprise contient encore des médias.' },
      { status: 409, statusText: 'Conflict' },
    );

    expect(component.catalogError).toContain('réaffectez-les');
    httpTesting.expectNone(`${environment.apiUrl}/admin/press-companies`);
  });

  it('renders the companies and media section after the payment history', () => {
    const cards = Array.from(fixture.nativeElement.querySelectorAll('.members-card')) as HTMLElement[];
    const paymentHistory = fixture.nativeElement.querySelector('.payment-history-card') as HTMLElement;
    const catalog = fixture.nativeElement.querySelector('.catalog-card') as HTMLElement;

    expect(paymentHistory).toBeTruthy();
    expect(catalog).toBeTruthy();
    expect(cards.indexOf(catalog)).toBeGreaterThan(cards.indexOf(paymentHistory));
  });

  it('shows ten companies on the first page and the remainder on the next page', () => {
    expect(component.catalogPageSize).toBe(10);
    expect(component.catalogTotalPages).toBe(2);
    expect(component.paginatedPressCompanies).toHaveLength(10);
    expect(component.paginatedPressCompanies[0].name).toBe('GROUPE RTI');
    expect(component.paginatedPressCompanies.some(company => company.name === 'ENTREPRISE 11')).toBe(false);

    component.goToCatalogPage(2);
    fixture.detectChanges();

    expect(component.catalogPage).toBe(2);
    expect(component.paginatedPressCompanies.map(company => company.name)).toEqual([
      'ENTREPRISE 11',
      'ENTREPRISE 12',
    ]);
    expect(component.catalogFirstItem).toBe(11);
    expect(component.catalogLastItem).toBe(12);
    expect(fixture.nativeElement.querySelector('.catalog-card').textContent).toContain('ENTREPRISE 12');
    httpTesting.expectNone(`${environment.apiUrl}/admin/press-companies`);
  });

  it('returns to the first catalog page when the search changes', () => {
    component.goToCatalogPage(2);
    expect(component.catalogPage).toBe(2);

    component.onCatalogSearchChange('ENTREPRISE 12');
    fixture.detectChanges();

    expect(component.catalogPage).toBe(1);
    expect(component.filteredPressCompanies.map(company => company.name)).toEqual(['ENTREPRISE 12']);
    expect(component.paginatedPressCompanies.map(company => company.name)).toEqual(['ENTREPRISE 12']);
    expect(fixture.nativeElement.querySelector('.catalog-card').textContent).toContain('1–1 sur 1 entreprise');
    httpTesting.expectNone(`${environment.apiUrl}/admin/press-companies`);
  });

  it('filters the login history and requests the next page with the same filters', () => {
    const failedAudit: LoginAuditRecord = {
      id: 51,
      login: 'awa@example.ci',
      userId: null,
      userName: null,
      userEmail: null,
      role: null,
      success: false,
      reason: 'invalid_credentials',
      ipAddress: '192.0.2.10',
      userAgent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/140.0.0.0 Safari/537.36',
      createdAt: '2026-08-11T16:20:00Z',
    };

    component.loginAuditSearch = '  awa@example.ci  ';
    component.loginAuditStatus = 'failure';
    component.applyLoginAuditFilters();

    const filteredRequest = httpTesting.expectOne(request => request.url === `${environment.apiUrl}/admin/login-audits`);
    expect(filteredRequest.request.method).toBe('GET');
    expect(filteredRequest.request.params.get('search')).toBe('awa@example.ci');
    expect(filteredRequest.request.params.get('status')).toBe('failure');
    expect(filteredRequest.request.params.get('page')).toBe('1');
    expect(filteredRequest.request.params.get('perPage')).toBe('25');
    filteredRequest.flush({
      data: [failedAudit],
      meta: { currentPage: 1, lastPage: 2, perPage: 25, total: 26 },
    });

    expect(component.loginAudits).toEqual([failedAudit]);
    expect(component.loginAuditFirstItem).toBe(1);
    expect(component.loginAuditLastItem).toBe(25);

    component.goToLoginAuditPage(2);

    const nextPageRequest = httpTesting.expectOne(request => request.url === `${environment.apiUrl}/admin/login-audits`);
    expect(nextPageRequest.request.params.get('search')).toBe('awa@example.ci');
    expect(nextPageRequest.request.params.get('status')).toBe('failure');
    expect(nextPageRequest.request.params.get('page')).toBe('2');
    nextPageRequest.flush({
      data: [failedAudit],
      meta: { currentPage: 2, lastPage: 2, perPage: 25, total: 26 },
    });

    fixture.detectChanges();

    expect(component.loginAuditPage).toBe(2);
    expect(component.loginAuditFirstItem).toBe(26);
    expect(component.loginAuditLastItem).toBe(26);
    expect(fixture.nativeElement.textContent).toContain('awa@example.ci');
    expect(fixture.nativeElement.textContent).toContain('Échouée');
    expect(fixture.nativeElement.textContent).toContain('Identifiant ou mot de passe incorrect');
    expect(fixture.nativeElement.textContent).toContain('Chrome · Windows');
  });

  it('ignores an older response when filters change quickly', () => {
    component.loginAuditStatus = 'success';
    component.applyLoginAuditFilters();
    const olderRequest = httpTesting.expectOne(request =>
      request.url === `${environment.apiUrl}/admin/login-audits`
      && request.params.get('status') === 'success'
    );

    component.loginAuditStatus = 'failure';
    component.applyLoginAuditFilters();
    const latestRequest = httpTesting.expectOne(request =>
      request.url === `${environment.apiUrl}/admin/login-audits`
      && request.params.get('status') === 'failure'
    );

    const latestAudit: LoginAuditRecord = {
      id: 62,
      login: 'latest-attempt',
      userId: null,
      userName: null,
      userEmail: null,
      role: null,
      success: false,
      reason: 'invalid_credentials',
      ipAddress: null,
      userAgent: null,
      createdAt: '2026-08-11T18:00:00Z',
    };
    latestRequest.flush({
      data: [latestAudit],
      meta: { currentPage: 1, lastPage: 1, perPage: 25, total: 1 },
    });

    olderRequest.flush({
      data: [{ ...latestAudit, id: 61, login: 'stale-attempt', success: true, reason: null }],
      meta: { currentPage: 1, lastPage: 1, perPage: 25, total: 1 },
    });

    expect(component.loginAudits).toEqual([latestAudit]);
    expect(component.loginAuditsLoading).toBe(false);
  });
});
