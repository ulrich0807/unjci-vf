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
  readonly registrationComplete: boolean;

  // Le champ 'role' a été retiré de la validation
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
    route: ActivatedRoute,
  ) {
    this.registrationComplete = route.snapshot.queryParamMap.get('inscription') === 'reussie';
  }

  togglePasswordVisibility(): void {
    this.passwordVisible = !this.passwordVisible;
  }

  submit(): void {
    this.submitted = true;
    this.authenticationError = false; // On réinitialise l'erreur à chaque nouvelle tentative

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    // On envoie les identifiants au service d'authentification
    this.auth.login(this.form.getRawValue()).subscribe({
      next: (response: any) => {
        const userRole = response.user.role; 

        if (userRole === 'admin') {
          this.router.navigate(['/administration']);
        } else {
          this.router.navigate(['/espace-membre']);
        }
      },
      error: (error) => {
        // On affiche le message d'erreur rouge
        this.authenticationError = true;
        
        // UX : On vide uniquement le champ mot de passe pour forcer l'utilisateur à le retaper
        this.form.controls.password.reset();
        
        // On repasse submitted à false pour ne pas afficher l'erreur "Mot de passe requis" 
        // tout de suite après l'avoir vidé
        this.submitted = false; 
        
        console.error('Erreur de connexion:', error);
      }
    });
  }
}