import { provideHttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { App } from './app';

@Component({ template: '' })
class EmptyPage {}

describe('App', () => {
  beforeEach(async () => {
    sessionStorage.clear();
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideHttpClient(), provideRouter([
        { path: '', component: EmptyPage },
        ...['guide-adhesion', 'application', 'renouvellement'].map(path => ({
          path, component: EmptyPage,
        })),
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

  it('opens adhesion and renewal from navigation and direct links without a closure dialog', async () => {
    const fixture = TestBed.createComponent(App);
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
    expect(page.querySelector('dialog')).toBeNull();
  });
});
