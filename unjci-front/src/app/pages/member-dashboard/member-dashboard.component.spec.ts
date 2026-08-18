import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { environment } from '../../../environments/environment';
import { MemberDashboard } from './member-dashboard.component';

describe('MemberDashboard membership workflow', () => {
  let fixture: ComponentFixture<MemberDashboard>;
  let component: MemberDashboard;
  let httpTesting: HttpTestingController;

  beforeEach(async () => {
    sessionStorage.setItem('unjci_session', JSON.stringify({
      login: 'journaliste@example.com',
      role: 'member',
      token: 'member-token',
    }));

    await TestBed.configureTestingModule({
      imports: [MemberDashboard],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpTesting.verify();
    sessionStorage.clear();
  });

  function loadProfile(overrides: Record<string, unknown> = {}): void {
    fixture = TestBed.createComponent(MemberDashboard);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const request = httpTesting.expectOne(`${environment.apiUrl}/member/profile`);
    expect(request.request.headers.get('Authorization')).toBe('Bearer member-token');
    request.flush({
      id: 1,
      first_name: 'Awa',
      last_name: 'KOUASSI',
      personal_email: 'journaliste@example.com',
      status: 'pending',
      membership_stage: 'awaiting_payment',
      request_type: 'adhesion',
      member_number: null,
      current_member_number: null,
      payments: [],
      ...overrides,
    });
    fixture.detectChanges();
  }

  it('offers only the first-membership payment before a number is assigned', () => {
    loadProfile();

    expect(component.paymentMode).toBe('adhesion');
    expect(component.expectedAmount).toBe(10000);
    expect(component.membershipStage).toBe('awaiting_payment');
    expect(fixture.nativeElement.textContent).toContain('Finaliser ma première adhésion');
  });

  it('offers renewal according to the request type while preserving an approved member number', () => {
    loadProfile({
      status: 'approved',
      membership_stage: 'approved',
      request_type: 'renewal',
      member_number: 'UJ26-00001',
      current_member_number: 'UJ26-00001',
      approved_at: '2026-08-11T10:00:00Z',
    });

    expect(component.paymentMode).toBe('renewal');
    expect(component.expectedAmount).toBe(5000);
    expect(component.membershipStage).toBe('approved');
    expect(fixture.nativeElement.textContent).toContain('Renouveler mon adhésion');
    expect(fixture.nativeElement.textContent).toContain('Numéro UNJCI : UJ26-00001');
  });

  it('shows a declared number as provisional without turning an adhesion into a renewal', () => {
    loadProfile({
      request_type: 'adhesion',
      current_member_number: 'UJ26-00123',
    });

    expect(component.hasMemberNumber).toBe(false);
    expect(component.proposedMemberNumber).toBe('UJ26-00123');
    expect(component.paymentMode).toBe('adhesion');
    expect(component.expectedAmount).toBe(10000);
    expect(fixture.nativeElement.textContent).toContain('Numéro déclaré : UJ26-00123 — en attente de vérification');
  });

  it('uses the renewal request type even when no official number exists yet', () => {
    loadProfile({
      request_type: 'renewal',
      member_number: null,
      current_member_number: null,
    });

    expect(component.paymentMode).toBe('renewal');
    expect(component.expectedAmount).toBe(5000);
    expect(fixture.nativeElement.textContent).toContain('Renouveler mon adhésion');
  });

  it('shows payment confirmation before the final UNJCI review stage', () => {
    loadProfile({
      membership_stage: 'payment_pending',
      payments: [{
        id: 10,
        payment_type: 'adhesion',
        amount: 10000,
        status: 'pending',
        payment_phone: '+2250700000000',
        created_at: '2026-08-11T10:00:00Z',
      }],
    });

    expect(component.membershipStage).toBe('payment_pending');
    expect(fixture.nativeElement.textContent).toContain('Paiement en cours de confirmation');
    expect(fixture.nativeElement.textContent).not.toContain('Renseigner le paiement');
  });
});
