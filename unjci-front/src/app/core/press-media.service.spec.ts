import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { PressCompanyRecord, PressMediaCatalogItem, PressMediaService } from './press-media.service';

describe('PressMediaService', () => {
  let service: PressMediaService;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    sessionStorage.setItem('unjci_session', JSON.stringify({
      login: 'admin',
      role: 'admin',
      token: 'admin-token',
    }));
    service = TestBed.inject(PressMediaService);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpTesting.verify();
    sessionStorage.clear();
  });

  it('loads and unwraps the public catalog', () => {
    const catalog = [
      { id: 1, companyId: 10, company: 'GROUPE RTI', name: 'RTI1', type: 'Numérique' as const },
    ];
    let result: PressMediaCatalogItem[] | undefined;

    service.getCatalog().subscribe(data => result = data);

    const request = httpTesting.expectOne(request => request.url.endsWith('/press-media'));
    expect(request.request.method).toBe('GET');
    expect(request.request.headers.has('Authorization')).toBe(false);
    request.flush({ data: catalog });

    expect(result).toEqual(catalog);
  });

  it('uses the authenticated admin CRUD contract and unwraps data responses', () => {
    const media = { id: 5, name: 'RTI1', type: 'Numérique' as const, isActive: true };
    const company: PressCompanyRecord = {
      id: 10,
      name: 'GROUPE RTI',
      isActive: true,
      media: [media],
    };

    service.getAdminCompanies().subscribe(result => expect(result).toEqual([company]));
    let request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-companies'));
    expect(request.request.method).toBe('GET');
    expect(request.request.headers.get('Authorization')).toBe('Bearer admin-token');
    request.flush({ data: [company] });

    service.createCompany({ name: 'NOUVEAU GROUPE', isActive: true })
      .subscribe(result => expect(result.name).toBe('NOUVEAU GROUPE'));
    request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-companies'));
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ name: 'NOUVEAU GROUPE', isActive: true });
    expect(request.request.headers.get('Authorization')).toBe('Bearer admin-token');
    request.flush({ data: { ...company, name: 'NOUVEAU GROUPE', media: [] } });

    service.updateCompany(10, { name: 'GROUPE RTI MODIFIÉ', isActive: false })
      .subscribe(result => expect(result.isActive).toBe(false));
    request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-companies/10'));
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({ name: 'GROUPE RTI MODIFIÉ', isActive: false });
    request.flush({ data: { ...company, name: 'GROUPE RTI MODIFIÉ', isActive: false } });

    service.createMedia(10, { name: 'RTI2', type: 'Numérique', isActive: true })
      .subscribe(result => expect(result.name).toBe('RTI2'));
    request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-companies/10/media'));
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ name: 'RTI2', type: 'Numérique', isActive: true });
    request.flush({ data: { ...media, id: 6, name: 'RTI2' } });

    service.updateMedia(5, {
      pressCompanyId: 10,
      name: 'RTI 1',
      type: 'Numérique',
      isActive: false,
    }).subscribe(result => expect(result.isActive).toBe(false));
    request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-media/5'));
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({
      pressCompanyId: 10,
      name: 'RTI 1',
      type: 'Numérique',
      isActive: false,
    });
    request.flush({ data: { ...media, name: 'RTI 1', isActive: false } });

    service.deleteMedia(5).subscribe(result => expect(result).toBeNull());
    request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-media/5'));
    expect(request.request.method).toBe('DELETE');
    request.flush(null, { status: 204, statusText: 'No Content' });

    service.deleteCompany(10).subscribe(result => expect(result).toBeNull());
    request = httpTesting.expectOne(request => request.url.endsWith('/admin/press-companies/10'));
    expect(request.request.method).toBe('DELETE');
    request.flush(null, { status: 204, statusText: 'No Content' });
  });
});
