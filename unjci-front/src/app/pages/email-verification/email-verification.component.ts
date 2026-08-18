import { CommonModule } from '@angular/common';
import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { MemberService } from '../../core/member.service';

@Component({
  selector: 'app-email-verification',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './email-verification.component.html',
  styleUrl: './email-verification.component.css',
})
export class EmailVerificationComponent implements OnInit {
  sending = false;
  verifying = false;
  codeSent = false;
  verified = false;
  submitted = false;
  message = '';
  errorMessage = '';

  readonly form = new FormGroup({
    email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
    code: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.pattern(/^\d{6}$/)] }),
  });

  constructor(
    private readonly members: MemberService,
    private readonly cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    const stateEmail = history.state?.email || '';
    const email = stateEmail || sessionStorage.getItem('unjci_verification_email') || '';
    this.form.controls.email.setValue(email);
    if (email) sessionStorage.setItem('unjci_verification_email', email);
    if (email && history.state?.sendOtp) {
      history.replaceState({ ...history.state, sendOtp: false }, document.title);
      this.sendCode();
    }
  }

  sendCode(): void {
    this.errorMessage = '';
    this.message = '';
    if (this.form.controls.email.invalid) {
      this.form.controls.email.markAsTouched();
      return;
    }

    this.sending = true;
    this.members.sendEmailVerificationOtp(this.form.controls.email.value.trim()).subscribe({
      next: response => {
        this.sending = false;
        this.codeSent = true;
        this.message = response.message;
        this.cdr.markForCheck();
      },
      error: error => {
        this.sending = false;
        this.errorMessage = error.status === 429
          ? 'Trop de demandes. Patientez une minute avant de renvoyer le code.'
          : 'Le code n’a pas pu être envoyé. Vérifiez votre connexion puis réessayez.';
        this.cdr.markForCheck();
      },
    });
  }

  verify(): void {
    this.submitted = true;
    this.errorMessage = '';
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.verifying = true;
    const { email, code } = this.form.getRawValue();
    this.members.verifyEmailOtp(email.trim(), code).subscribe({
      next: response => {
        this.verifying = false;
        this.verified = true;
        this.message = response.message;
        sessionStorage.removeItem('unjci_verification_email');
        this.cdr.markForCheck();
      },
      error: error => {
        this.verifying = false;
        this.errorMessage = error.status === 429
          ? 'Trop de tentatives. Patientez une minute avant de réessayer.'
          : (error.error?.message || 'Le code n’a pas pu être vérifié.');
        this.cdr.markForCheck();
      },
    });
  }

  onCodeInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const code = input.value.replace(/\D/g, '').slice(0, 6);
    input.value = code;
    this.form.controls.code.setValue(code);
  }
}
