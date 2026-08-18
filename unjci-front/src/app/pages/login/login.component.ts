import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.css',
})
export class LoginComponent {
  passwordVisible = false;
  submitted = false;
  authenticationError = false;
  authenticationErrorMessage = 'Adresse e-mail, numéro UNJCI ou mot de passe incorrect.';
  readonly registrationComplete: boolean;

  readonly form = new FormGroup({
    login: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    password: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(6)],
    }),
    rememberMe: new FormControl(false, { nonNullable: true }),
  });

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly route: ActivatedRoute,
  ) {
    this.registrationComplete = this.route.snapshot.queryParamMap.get('inscription') === 'reussie';
    const registrationEmail = this.route.snapshot.queryParamMap.get('email')?.trim() || '';
    if (registrationEmail) {
      this.form.controls.login.setValue(registrationEmail);
    }
  }

  togglePasswordVisibility(): void {
    this.passwordVisible = !this.passwordVisible;
  }

  submit(): void {
    this.submitted = true;
    this.authenticationError = false;
    this.authenticationErrorMessage = 'Adresse e-mail, numéro UNJCI ou mot de passe incorrect.';

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.authenticationError = true;
      this.authenticationErrorMessage = 'Veuillez remplir tous les champs obligatoires (en rouge).';
      return;
    }

    this.auth.login(this.form.getRawValue()).subscribe({
      next: (response: any) => {
        const userRole = response.user.role; 

        // Si l'utilisateur a été redirigé ici depuis un scan de QR Code
        const returnUrl = this.route.snapshot.queryParams['returnUrl'];
        if (returnUrl) {
            this.router.navigateByUrl(returnUrl);
            return;
        }

        // Redirections standards selon le rôle
       if (userRole === 'admin') {
          this.router.navigate(['/administration']);
        } else if (userRole === 'scanner') {
          this.router.navigate(['/scanner']);
        } else {
          this.router.navigate(['/espace-membre']);
        }
      },
      error: (error) => {
        if (error.status === 403 && error.error?.needs_verification) {
          this.router.navigate(['/verifier-email'], {
            state: {
              email: error.error.email,
              sendOtp: true,
            }
          });
          return;
        } else if (error.status === 429) {
          this.authenticationErrorMessage = 'Trop de tentatives de connexion. Réessayez dans une minute.';
        } else if (error.error?.message) {
          this.authenticationErrorMessage = error.error.message;
        }
        // Affichage de l'erreur en cas de mauvais identifiants
        this.authenticationError = true;
      }
    });
  }
}
