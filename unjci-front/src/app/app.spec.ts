import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { App } from './app';
import { environment } from '../environments/environment';

@Component({ template: '' })
class EmptyPage {}

describe('App', () => {
  let http: HttpTestingController;
  beforeEach(async () => {
    sessionStorage.clear();
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([
        { path: '', component: EmptyPage },
        ...['guide-adhesion', 'application', 'renouvellement'].map(path => ({
          path, component: EmptyPage,
        })),
      ])],
    }).compileComponents();
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  function createApp(closed = false) {
    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();
    http.expectOne(`${environment.apiUrl}/membership/availability`)
      .flush({ requests_closed: closed, closure_message: 'FIN DES DEMANDES' });
    fixture.detectChanges();
    return fixture;
  }

  it('should create the app', () => {
    const fixture = createApp();
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should render the navigation', async () => {
    const fixture = createApp();
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('nav')).toBeTruthy();
  });

  it('opens adhesion and renewal from navigation and direct links without a closure dialog', async () => {
    const fixture = createApp();
    fixture.detectChanges();
    const page = fixture.nativeElement as HTMLElement;
    const router = TestBed.inject(Router);
    page.querySelector<HTMLAnchorElement>('nav a[href="/guide-adhesion"]')!.click();
    await fixture.whenStable();
    expect(router.url).toBe('/guide-adhesion');
    page.querySelector<HTMLAnchorElement>('nav a[href="/renouvellement"]')!.click();
    await fixture.whenStable();
    expect(router.url).toBe('/renouvellement');
    await router.navigateByUrl('/application?type=renewal');
    await fixture.whenStable();
    expect(router.url).toBe('/application?type=renewal');
    expect(page.querySelector('.membership-closed-action')).toBeNull();
    expect(page.querySelector('dialog')!.open).toBe(false);
  });

  it('greys the closed actions and shows the closure popup', async () => {
    const fixture = createApp(true);
    const page = fixture.nativeElement as HTMLElement;
    const dialog = page.querySelector('dialog')!;
    const showModal = vi.fn(() => { dialog.open = true; });
    Object.defineProperty(dialog, 'showModal', { value: showModal });
    page.querySelector<HTMLAnchorElement>('nav a[href="/guide-adhesion"]')!.click();
    http.expectOne(`${environment.apiUrl}/membership/availability`)
      .flush({ requests_closed: true, closure_message: 'FIN DES DEMANDES' });
    await fixture.whenStable();
    expect(showModal).toHaveBeenCalledOnce();
    expect(dialog.textContent).toContain('FIN DES DEMANDES');
    expect(page.querySelector('nav a[href="/renouvellement"]')!.classList.contains('membership-closed-action')).toBe(true);
  });

  it('lets a visitor proceed after an admin reopens requests without reloading the page', async () => {
    const fixture = createApp(true);
    fixture.nativeElement.querySelector('nav a[href="/guide-adhesion"]').click();
    http.expectOne(`${environment.apiUrl}/membership/availability`)
      .flush({ requests_closed: false, closure_message: 'FIN DES DEMANDES' });
    await fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe('/guide-adhesion');
  });
});
