import { provideHttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { App } from './app';
import { membershipClosedGuard } from './core/membership-closure';

@Component({ template: '' })
class EmptyPage {}

describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideHttpClient(), provideRouter([
        { path: '', component: EmptyPage },
        { path: 'application', component: EmptyPage, canActivate: [membershipClosedGuard] },
      ])],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(App);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should render the navigation', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('nav')).toBeTruthy();
  });

  it('opens the closure dialog from a greyed action and from a direct renewal link', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();
    const page = fixture.nativeElement as HTMLElement;
    const dialog = page.querySelector('dialog')!;
    const showModal = vi.fn(() => { dialog.open = true; });
    Object.defineProperty(dialog, 'showModal', { value: showModal });

    page.querySelector<HTMLButtonElement>('nav button.membership-closed-action')!.click();
    await fixture.whenStable();
    expect(showModal).toHaveBeenCalledTimes(1);
    expect(dialog.textContent).toContain('FIN DES DEMANDES D’ADHÉSION ET DE RENOUVELLEMENT DES CARTES UNJCI SESSION 2026');

    dialog.open = false;
    dialog.dispatchEvent(new Event('close'));
    const router = TestBed.inject(Router);
    await router.navigateByUrl('/application?type=renewal');
    await fixture.whenStable();
    expect(router.url).toBe('/');
    expect(showModal).toHaveBeenCalledTimes(2);
  });
});
