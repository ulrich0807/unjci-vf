import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { RenewalEntryComponent } from './renewal-entry.component';

describe('RenewalEntryComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RenewalEntryComponent],
      providers: [
        provideRouter([]),
        {
          provide: AuthService,
          useValue: { getSession: () => null },
        },
      ],
    }).compileComponents();
  });

  it('explains both renewal paths and links to the expected pages', () => {
    const fixture = TestBed.createComponent(RenewalEntryComponent);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Je n’ai pas encore de compte en ligne');
    expect(element.textContent).toContain('J’ai déjà rempli le formulaire en ligne');

    const links = Array.from(element.querySelectorAll<HTMLAnchorElement>('a'));
    const registrationLink = links.find(link => link.textContent?.includes('M’inscrire pour le renouvellement'));
    const loginLink = links.find(link => link.textContent?.includes('Me connecter'));

    expect(registrationLink?.getAttribute('href')).toBe('/application?type=renewal');
    expect(loginLink?.getAttribute('href')).toBe('/login');
  });
});
