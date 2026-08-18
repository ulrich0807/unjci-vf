import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './forgot-password.component.html',
  styleUrl: './forgot-password.component.css',
})
export class ForgotPasswordComponent {
  submitted = false;
  sending = false;
  successMessage = '';
  errorMessage = '';

  readonly form = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
  });

  constructor(private readonly auth: AuthService) {}

  submit(): void {
    if (this.sending) return;

    this.submitted = true;
    this.successMessage = '';
    this.errorMessage = '';
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.sending = true;
    this.auth.forgotPassword(this.form.controls.email.value).subscribe({
      next: response => {
        this.sending = false;
        this.successMessage = response.message
          || 'Un mot de passe temporaire a été envoyé à cette adresse. Consultez votre boîte de réception et vos courriers indésirables.';
      },
      error: (error: HttpErrorResponse) => {
        this.sending = false;
        this.errorMessage = this.getErrorMessage(error);
      },
    });
  }

  private getErrorMessage(error: HttpErrorResponse): string {
    if (error.error?.code === 'email_not_found') {
      return 'Aucun compte n’est associé à cette adresse e-mail. Vérifiez l’adresse saisie ou contactez l’UNJCI.';
    }

    if (error.status === 422) {
      return 'Saisissez une adresse e-mail valide.';
    }

    if (error.status === 429) {
      return 'Vous avez effectué plusieurs demandes. Patientez une minute avant de réessayer.';
    }

    if (error.status === 0) {
      return 'La demande n’a pas pu être envoyée. Vérifiez votre connexion internet, puis réessayez.';
    }

    return 'Nous ne pouvons pas envoyer le mot de passe temporaire pour le moment. Réessayez dans quelques minutes.';
  }
}
