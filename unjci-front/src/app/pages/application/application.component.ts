import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { MemberService } from '../../core/member.service';

@Component({
  selector: 'app-application',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './application.component.html',
  styleUrl: './application.component.css'
})
export class ApplicationComponent {
  private fb = inject(FormBuilder);
  private memberService = inject(MemberService);
  private router = inject(Router);

  submitted = false;
  saving = false;
  selectedFiles: { [key: string]: File } = {};

  submissionError = '';
  photoError = '';
  passwordVisible = false;
  confirmPasswordVisible = false;
  
  readonly statuses = ['Journaliste mensualisé (CDI/CDD)', 'Pigiste', 'Indépendant / Freelance', 'Photojournaliste', 'Journaliste honoraire / Retraité'];
  readonly functions = ['Rédacteur', 'Reporter', 'Présentateur', 'Secrétaire de rédaction', 'Rédacteur en chef', 'Photojournaliste', 'Autre'];
  
  // On garde les moyens de paiement à titre informatif pour l'intention de l'utilisateur
  readonly payments = ['Wave', 'MTN MoMo', 'Orange Money', 'Moov Money'];

  currentStep: number = 1;
  totalSteps: number = 5;

  form = this.fb.group({
    firstName: ['', Validators.required], lastName: ['', Validators.required],
    birthDate: ['', Validators.required], birthPlace: ['', Validators.required],
    postalAddress: ['', Validators.required], phone: ['', Validators.required],
    personalEmail: ['', [Validators.required, Validators.email]],
    professionalStatus: ['', Validators.required], employers: ['', Validators.required],
    functionTitle: ['', Validators.required], pressCardNumber: ['', Validators.required],
    pressCardExpiry: ['', Validators.required], professionalEmail: ['', Validators.email], professionalPhone: [''],
    requestType: ['Première adhésion', Validators.required], currentMemberNumber: [''],
    pressCardFile: ['', Validators.required], cvFile: [''], photoFile: ['', Validators.required], photoDataUrl: [''],
    declarationAccepted: [false, Validators.requiredTrue], signatureName: ['', Validators.required],
    signatureDate: [new Date().toISOString().slice(0,10), Validators.required],
    contributionAmount: [10000, Validators.required], paymentMethod: ['Wave', Validators.required],
    directoryConsent: [false], privacyAccepted: [false, Validators.requiredTrue],
    login: ['', [Validators.required, Validators.minLength(4), Validators.pattern(/^[a-zA-Z0-9._-]+$/)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    confirmPassword: ['', Validators.required]
  }, { validators: [this.passwordsMatch] });

  constructor() {
    this.form.controls.requestType.valueChanges.subscribe(type => {
      this.form.controls.contributionAmount.setValue(type === 'Renouvellement' ? 5000 : 10000);
      if (type === 'Renouvellement') this.form.controls.currentMemberNumber.addValidators(Validators.required);
      else this.form.controls.currentMemberNumber.clearValidators();
      this.form.controls.currentMemberNumber.updateValueAndValidity();
    });
  }

  fileSelected(event: Event, control: 'pressCardFile'|'cvFile'|'photoFile') {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    if (control === 'photoFile') {
      this.photoError = '';
      const maximumPhotoSize = 2 * 1024 * 1024;
      if (file.size > maximumPhotoSize) {
        this.form.controls.photoFile.setValue('');
        this.form.controls.photoDataUrl.setValue('');
        this.form.controls.photoFile.markAsTouched();
        this.photoError = 'La photo dépasse 2 Mo. Choisissez une image plus légère.';
        (event.target as HTMLInputElement).value = '';
        return;
      }
    }

    this.selectedFiles[control] = file;
    this.form.controls[control].setValue(file.name);

    if (control === 'photoFile') {
      const reader = new FileReader();
      reader.onload = () => this.form.controls.photoDataUrl.setValue(String(reader.result));
      reader.readAsDataURL(file);
    }
  }

  nextStep() {
    if (this.currentStep === 1 && this.isStepInvalid(['firstName', 'lastName', 'birthDate', 'birthPlace', 'postalAddress', 'phone'])) return;
    if (this.currentStep === 2 && this.isStepInvalid(['professionalStatus', 'employers', 'functionTitle', 'pressCardNumber', 'pressCardExpiry', 'personalEmail'])) return;
    if (this.currentStep === 3 && this.isStepInvalid(['pressCardFile', 'photoFile'])) return;
    if (this.currentStep === 4 && this.isStepInvalid(['login', 'password', 'confirmPassword'])) return;

    if (this.currentStep < this.totalSteps) {
      this.currentStep++;
      this.scrollToTop();
    }
  }

  prevStep() {
    if (this.currentStep > 1) {
      this.currentStep--;
      this.scrollToTop();
    }
  }

  scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  private isStepInvalid(fields: string[]): boolean {
    let invalid = false;
    for (const field of fields) {
      const control = this.form.get(field);
      if (control && control.invalid) {
        control.markAsTouched();
        invalid = true;
      }
    }
    return invalid;
  }

  submit(): void {
    this.submitted = true;
    this.submissionError = '';

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      setTimeout(() => {
        const firstInvalidField = document.querySelector<HTMLElement>(
          '.form-shell input.ng-invalid, .form-shell select.ng-invalid, .form-shell textarea.ng-invalid',
        );
        firstInvalidField?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalidField?.focus();
      });
      return;
    }

    this.saving = true;

    const formData = new FormData();
    const rawValue = this.form.getRawValue();

    Object.keys(rawValue).forEach(key => {
      if (key !== 'pressCardFile' && key !== 'cvFile' && key !== 'photoFile' && key !== 'confirmPassword') {
        let value = (rawValue as any)[key];
        if (value === true) value = 1;
        if (value === false) value = 0;
        formData.append(key, value);
      }
    });

    if (this.selectedFiles['pressCardFile']) formData.append('pressCardFile', this.selectedFiles['pressCardFile']);
    if (this.selectedFiles['cvFile']) formData.append('cvFile', this.selectedFiles['cvFile']);
    if (this.selectedFiles['photoFile']) formData.append('photoFile', this.selectedFiles['photoFile']);

    this.memberService.submitApplication(formData).subscribe({
      next: (response) => {
        this.saving = false;
        // Redirection directe vers le login avec le message de succès
        this.finishApplication();
      },
      error: (error) => {
        this.saving = false;
        console.error(error);
        if (error.error?.errors?.login) {
          this.form.controls.login.setErrors({ loginTaken: true });
          this.submissionError = 'Ce login est déjà utilisé.';
        } else if (error.error?.errors?.personalEmail) {
          this.form.controls.personalEmail.setErrors({ emailTaken: true });
          this.submissionError = 'Cet e-mail est déjà utilisé.';
        } else {
          this.submissionError = 'La demande n’a pas pu être enregistrée sur le serveur.';
        }
        setTimeout(() => {
          document.getElementById('submission-error')?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
          });
        });
      }
    });
  }

  private finishApplication(): void {
    this.router.navigate(['/login'], {
      queryParams: { inscription: 'reussie' },
    });
  }

  private passwordsMatch(control: AbstractControl): ValidationErrors | null {
    const password = control.get('password')?.value;
    const confirmation = control.get('confirmPassword')?.value;
    return password && confirmation && password !== confirmation ? { passwordsMismatch: true } : null;
  }
}