import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ProtectedFileDirective } from './protected-file.directive';

@Component({ standalone: true, imports: [ProtectedFileDirective], template: '<img [protectedSrc]="path">' })
class TestHost { path = '/api/storage/members/photos/test.png'; }

describe('ProtectedFileDirective', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [TestHost], providers: [provideHttpClient(), provideHttpClientTesting()] });
    sessionStorage.setItem('unjci_session', JSON.stringify({ role: 'member', token: 'test-token' }));
  });
  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
    sessionStorage.clear();
  });

  it('authenticates file downloads without exposing the token in the image URL', () => {
    const fixture = TestBed.createComponent(TestHost);
    fixture.detectChanges();
    const request = TestBed.inject(HttpTestingController).expectOne('/api/storage/members/photos/test.png');
    expect(request.request.headers.get('Authorization')).toBe('Bearer test-token');
    expect(request.request.url).not.toContain('test-token');
    expect(request.request.responseType).toBe('blob');
    request.flush(null, { status: 403, statusText: 'Forbidden' });
    expect(fixture.nativeElement.querySelector('img').getAttribute('src')).toBeNull();
    fixture.destroy();
  });

  it('never sends credentials to an external URL', () => {
    const fixture = TestBed.createComponent(TestHost);
    fixture.componentInstance.path = 'https://example.com/api/storage/test.png';
    fixture.detectChanges();
    TestBed.inject(HttpTestingController).expectNone(() => true);
    fixture.destroy();
  });
});
