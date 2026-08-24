import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { MemberService } from '../../core/member.service';
import { finalize } from 'rxjs';
import { PressMediaService } from '../../core/press-media.service';
import type { PressMediaCatalogItem } from '../../core/press-media.service';

const NAME_PATTERN = /^[\p{L}\p{M}](?:[\p{L}\p{M} '’-]*[\p{L}\p{M}])?$/u;
const BIRTH_PLACE_PATTERN = /^[\p{L}\p{M}0-9](?:[\p{L}\p{M}0-9 '’-]*[\p{L}\p{M}0-9])?$/u;
const EMAIL_PATTERN = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\.[A-Za-z]{2,63}$/;
const IDENTITY_FIELDS = ['firstName', 'lastName', 'alias', 'birthDate', 'birthPlace', 'phone'] as const;

function codePointLength(value: string): number {
  return Array.from(value).length;
}

function unicodeLetterCount(value: string): number {
  return value.match(/\p{L}/gu)?.length ?? 0;
}

function nameValidator(optional = false): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const value = String(control.value ?? '').trim();

    if (!value) return optional ? null : { required: true };
    if (codePointLength(value) < 2) return { minlength: { requiredLength: 2, actualLength: codePointLength(value) } };
    if (codePointLength(value) > 100) return { maxlength: { requiredLength: 100, actualLength: codePointLength(value) } };
    if (unicodeLetterCount(value) < 2) return { minimumLetters: true };
    if (!NAME_PATTERN.test(value)) return { nameFormat: true };

    return null;
  };
}

function birthPlaceValidator(control: AbstractControl): ValidationErrors | null {
  const value = String(control.value ?? '').trim();

  if (!value) return { required: true };
  if (codePointLength(value) < 2) return { minlength: { requiredLength: 2, actualLength: codePointLength(value) } };
  if (codePointLength(value) > 100) return { maxlength: { requiredLength: 100, actualLength: codePointLength(value) } };
  if (unicodeLetterCount(value) < 2) return { minimumLetters: true };
  if (!BIRTH_PLACE_PATTERN.test(value)) return { birthPlaceFormat: true };

  return null;
}

function frenchBirthDateValidator(control: AbstractControl): ValidationErrors | null {
  const value = String(control.value ?? '').trim();
  if (!value) return { required: true };

  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value);
  if (!match) return { dateFormat: true };

  const day = Number(match[1]);
  const month = Number(match[2]);
  const year = Number(match[3]);
  if (year < 1 || month < 1 || month > 12 || day < 1 || day > 31) return { invalidDate: true };

  const candidate = new Date(Date.UTC(2000, month - 1, day));
  candidate.setUTCFullYear(year);
  if (
    candidate.getUTCFullYear() !== year
    || candidate.getUTCMonth() !== month - 1
    || candidate.getUTCDate() !== day
  ) {
    return { invalidDate: true };
  }

  const today = new Date();
  const todayUtc = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  return candidate.getTime() > todayUtc ? { futureDate: true } : null;
}

function hasBalancedParentheses(value: string): boolean {
  let balance = 0;
  for (const character of value) {
    if (character === '(') {
      balance++;
    } else if (character === ')' && --balance < 0) {
      return false;
    }
  }
  return balance === 0;
}

function phoneValidator(control: AbstractControl): ValidationErrors | null {
  const value = String(control.value ?? '').trim();
  if (!value) return { required: true };
  
  const digits = value.replace(/\D/g, '');
  if (digits.length !== 10) {
    return { phoneDigitCount: { required: 10, actual: digits.length } };
  }
  return null;
}


@Component({
  selector: 'app-application',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule,RouterModule],
  templateUrl: './application.component.html',
  styleUrl: './application.component.css'
})
export class ApplicationComponent implements OnInit {
  private fb = inject(FormBuilder);
  private memberService = inject(MemberService);
  private pressMediaService = inject(PressMediaService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private cdr = inject(ChangeDetectorRef);

  readonly applicationMode: 'adhesion' | 'renewal' =
    this.route.snapshot.queryParamMap.get('type') === 'renewal' ? 'renewal' : 'adhesion';
  readonly isRenewal = this.applicationMode === 'renewal';

  isSubmittedSuccessfully = false;
  
  submitted = false;
  saving = false;
  selectedFiles: { [key: string]: File } = {};

  submissionError = '';
  photoError = '';
  passwordVisible = false;
  confirmPasswordVisible = false;
  
  readonly statuses = ['Journaliste mensualisé (CDI/CDD)', 'Pigiste', 'Indépendant / Freelance', 'Photojournaliste', 'Journaliste honoraire / Retraité'];
  readonly functions = ['Rédacteur', 'Reporter', 'Directeur de Publication', 'Secrétaire de rédaction', 'Rédacteur en chef', 'Photojournaliste','Chef de service'];
  readonly payments = ['Wave', 'MTN MoMo', 'Orange Money', 'Moov Money'];
  readonly currentYearShort = String(new Date().getFullYear()).slice(-2);

  currentStep: number = 1;
  totalSteps: number = 5;

  memberNumberError = '';
  mediaSearch = '';
  companySuggestionsOpen = false;
  mediaSuggestionsOpen = false;
  pressMedia: readonly PressMediaCatalogItem[] = [];
  companyOptions: readonly string[] = [];
  loadingPressMedia = false;
  pressMediaError = '';

  form = this.fb.group({
    firstName: ['', nameValidator()],
    alias: ['', nameValidator(true)],
    lastName: ['', nameValidator()],
    birthDate: ['', frenchBirthDateValidator],
    birthPlace: ['', birthPlaceValidator],
    phone: ['', phoneValidator],
    postalAddress: [''],
    email: ['', [Validators.required, Validators.email, Validators.pattern(EMAIL_PATTERN)]], 
    professionalStatus: ['', Validators.required], 
    employers: ['', Validators.required],
    mediaSelection: ['', Validators.required],
    mediaName: ['', Validators.required],
    mediaType: ['', Validators.required],
    professionalStatus: ['', Validators.required], 
    employers: ['', Validators.required],
    mediaSelection: ['', Validators.required],
    mediaName: ['', Validators.required],
    mediaType: ['', Validators.required],
    functionTitle: ['', Validators.required], 
    pressCardNumber: ['', [Validators.required, Validators.pattern(/^\d{4}JP$/)]],
    pressCardExpiry: ['', Validators.required], 
    currentMemberNumber: [''],
    professionalPhone: [''],
    
    pressCardRectoFile: ['', Validators.required],
    pressCardVersoFile: ['', Validators.required],
    oldCardRectoFile: [''],
    oldCardVersoFile: [''],
    photoFile: ['', Validators.required], 
    photoDataUrl: [''],
    
    declarationAccepted: [false, Validators.requiredTrue], 
    
    contributionAmount: [10000, Validators.required],
    paymentMethod: ['Wave', Validators.required],
    directoryConsent: [false], 
    privacyAccepted: [false, Validators.requiredTrue],
    password: ['', [Validators.required, Validators.minLength(8)]],
    confirmPassword: ['', Validators.required]
  }, { validators: [this.passwordsMatch] });


  fileSelected(event: Event, control: 'pressCardRectoFile' | 'pressCardVersoFile' | 'photoFile' | 'oldCardRectoFile' | 'oldCardVersoFile') {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    if (control === 'photoFile' || control === 'pressCardRectoFile' || control === 'pressCardVersoFile' || control === 'oldCardRectoFile' || control === 'oldCardVersoFile') {
      this.photoError = '';
      const maximumFileSize = 2 * 1024 * 1024;
      if (file.size > maximumFileSize) {
        this.form.controls[control].setValue('');
        if (control === 'photoFile') this.form.controls.photoDataUrl.setValue('');
        this.form.controls[control].markAsTouched();
        this.photoError = 'Un ou plusieurs fichiers dépassent 2 Mo. Veuillez choisir des fichiers plus légers.';
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

  onMemberNumberInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const value = input.value.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 10);
    input.value = value;
    this.memberNumberError = '';
    this.form.controls.currentMemberNumber.setValue(value);
  }

  onPhoneInput(event: Event, controlName: 'phone' | 'professionalPhone'): void {
    const input = event.target as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '').slice(0, 10);
    input.value = digits;
    this.form.controls[controlName].setValue(digits);
  }

  onBirthDateInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '').slice(0, 8);
    const value = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)]
      .filter(Boolean)
      .join('/');

    input.value = value;
    this.form.controls.birthDate.setValue(value);
  }

  onPressCardInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const digits = input.value.toUpperCase().replace(/JP$/i, '').replace(/\D/g, '').slice(0, 4);
    const value = digits.length === 4 ? `${digits}JP` : digits;
    input.value = value;
    this.form.controls.pressCardNumber.setValue(value);
  }

  nextStep() {
    this.submissionError = '';

    if (this.currentStep === 2 && (this.loadingPressMedia || this.pressMediaError)) return;

    // Si l'étape est invalide, on force l'affichage de toutes les erreurs
    if (this.currentStep === 1) {
      this.normalizeIdentityFields();
      const identityFields = this.isRenewal
        ? ['currentMemberNumber', ...IDENTITY_FIELDS]
        : [...IDENTITY_FIELDS];
      if (this.isStepInvalid(identityFields)) {
        this.submissionError = 'Veuillez remplir correctement tous les champs en rouge avant de continuer.';
        return;
      }
    }
    if (this.currentStep === 2 && this.isStepInvalid(['professionalStatus', 'employers', 'mediaSelection', 'functionTitle', 'pressCardNumber', 'pressCardExpiry', 'email'])) {
      this.submissionError = 'Veuillez remplir correctement tous les champs en rouge avant de continuer.';
      return;
    }
    if (this.currentStep === 3) {
      const requiredFiles = ['pressCardRectoFile', 'pressCardVersoFile', 'photoFile'];
      if (this.isRenewal) {
        requiredFiles.push('oldCardRectoFile', 'oldCardVersoFile');
      }
      if (this.isStepInvalid(requiredFiles)) {
        this.submissionError = 'Veuillez fournir toutes les pièces justificatives requises (en rouge).';
        return;
      }
    }
    
    // Pour l'étape 4, on vérifie aussi la validation croisée des mots de passe
    if (this.currentStep === 4) {
      const isInvalid = this.isStepInvalid(['password', 'confirmPassword']);
      if (isInvalid || this.form.hasError('passwordsMismatch')) {
        this.form.get('confirmPassword')?.markAsTouched();
        this.submissionError = 'Veuillez vérifier les mots de passe (en rouge) avant de continuer.';
        return;
      }
    }
    
    this.advanceStep();
  }

  private advanceStep(): void {
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

  private normalizeIdentityFields(): void {
    for (const field of IDENTITY_FIELDS) {
      const control = this.form.controls[field];
      const rawValue = String(control.value ?? '');
      const normalizedValue = field === 'birthDate'
        ? rawValue.trim()
        : rawValue.replace(/\s+/gu, ' ').trim();

      if (rawValue !== normalizedValue) {
        control.setValue(normalizedValue, { emitEvent: false });
      }
    }
  }

  private setFileValidatorsBasedOnMemberState(): void {
    const fileControls = ['pressCardRectoFile', 'pressCardVersoFile', 'photoFile'] as const;
    for (const controlName of fileControls) {
      const control = this.form.get(controlName);
      if (!control) continue;
      control.setValidators(Validators.required);
      control.updateValueAndValidity();
    }

    if (this.isRenewal) {
      const oldCardControls = ['oldCardRectoFile', 'oldCardVersoFile'] as const;
      for (const controlName of oldCardControls) {
        const control = this.form.get(controlName);
        if (control) {
          control.setValidators(Validators.required);
          control.updateValueAndValidity();
        }
      }
    }

    const passwordControl = this.form.get('password');
    const confirmPasswordControl = this.form.get('confirmPassword');
    passwordControl?.setValidators([Validators.required, Validators.minLength(8)]);
    confirmPasswordControl?.setValidators(Validators.required);
    passwordControl?.updateValueAndValidity();
    confirmPasswordControl?.updateValueAndValidity();
  }

  constructor() {
    if (this.isRenewal) {
      this.form.controls.currentMemberNumber.setValidators([
        Validators.pattern(/^UJ\d{2}-\d{5}$/),
      ]);
    } else {
      this.form.controls.currentMemberNumber.clearValidators();
      this.form.controls.currentMemberNumber.setValue('', { emitEvent: false });
    }
    this.form.controls.currentMemberNumber.updateValueAndValidity({ emitEvent: false });
    this.setFileValidatorsBasedOnMemberState();
  }

  ngOnInit(): void {
    this.loadPressMedia();
  }

  loadPressMedia(): void {
    if (this.loadingPressMedia) return;

    this.loadingPressMedia = true;
    this.pressMediaError = '';

    this.pressMediaService.getCatalog()
      .pipe(finalize(() => this.loadingPressMedia = false))
      .subscribe({
        next: pressMedia => {
          if (!pressMedia.length) {
            this.pressMedia = [];
            this.companyOptions = [];
            this.companySuggestionsOpen = false;
            this.mediaSuggestionsOpen = false;
            this.pressMediaError = 'Aucune entreprise ni aucun média ne sont disponibles pour le moment. Réessayez plus tard.';
            return;
          }

          this.pressMedia = pressMedia;
          this.companyOptions = [...new Set(pressMedia.map(item => item.company))]
            .sort((left, right) => left.localeCompare(right, 'fr'));
          this.cdr.markForCheck();
        },
        error: () => {
          this.pressMedia = [];
          this.companyOptions = [];
          this.companySuggestionsOpen = false;
          this.mediaSuggestionsOpen = false;
          this.pressMediaError = 'Impossible de charger les entreprises et les médias. Vérifiez votre connexion puis réessayez.';
          this.cdr.markForCheck();
        },
      });
  }

  get filteredMediaOptions(): readonly PressMediaCatalogItem[] {
    const company = this.form.controls.employers.value?.trim() || '';
    const normalizedCompany = this.normalizeSearch(company);
    const query = this.normalizeSearch(this.mediaSearch);
    return this.pressMedia.filter(item =>
      this.normalizeSearch(item.company) === normalizedCompany
      && (!query || this.normalizeSearch(item.name).includes(query))
    );
  }

  get filteredCompanyOptions(): readonly string[] {
    const query = this.normalizeSearch(this.form.controls.employers.value || '');
    return this.companyOptions
      .filter(company => !query || this.normalizeSearch(company).includes(query));
  }

  onCompanyInput(): void {
    this.mediaSearch = '';
    this.companySuggestionsOpen = true;
    this.form.patchValue({ mediaSelection: '', mediaName: '', mediaType: '' });
  }

  selectCompany(company: string): void {
    this.form.controls.employers.setValue(company);
    this.mediaSearch = '';
    this.form.patchValue({ mediaSelection: '', mediaName: '', mediaType: '' });
    this.companySuggestionsOpen = false;
  }

  closeCompanySuggestions(event?: Event): void {
    const autocomplete = (event?.currentTarget as HTMLElement | null)?.closest('.autocomplete');
    setTimeout(() => {
      if (!autocomplete?.contains(document.activeElement)) {
        this.companySuggestionsOpen = false;
      }
    });
  }

  onMediaSearch(event: Event): void {
    this.mediaSearch = (event.target as HTMLInputElement).value;
    this.mediaSuggestionsOpen = true;
    this.form.patchValue({ mediaSelection: '', mediaName: '', mediaType: '' });
  }

  selectMedia(selected: PressMediaCatalogItem): void {
    this.mediaSearch = selected.name;
    this.form.patchValue({
      mediaSelection: `${selected.type}|||${selected.name}`,
      mediaName: selected.name,
      mediaType: selected.type,
    });
    this.mediaSuggestionsOpen = false;
  }

  closeMediaSuggestions(event?: Event): void {
    const autocomplete = (event?.currentTarget as HTMLElement | null)?.closest('.autocomplete');
    setTimeout(() => {
      if (!autocomplete?.contains(document.activeElement)) {
        this.mediaSuggestionsOpen = false;
      }
    });
  }

  private normalizeSearch(value: string): string {
    return value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-zA-Z0-9]+/g, '')
      .toLowerCase();
  }

  submit(): void {
    this.submitted = true;
    this.submissionError = '';
    this.memberNumberError = '';
    this.normalizeIdentityFields();

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.submissionError = 'Veuillez cocher les cases obligatoires pour valider votre engagement.';
      setTimeout(() => {
        const firstInvalidField = document.querySelector<HTMLElement>(
          '.form-shell input.ng-invalid, .form-shell select.ng-invalid, .form-shell textarea.ng-invalid, .check input.ng-invalid',
        );
        firstInvalidField?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalidField?.focus();
      });
      return;
    }

    this.saving = true;

    const formData = new FormData();
    const rawValue = this.form.getRawValue();

    formData.append('requestType', this.applicationMode);

    Object.keys(rawValue).forEach(key => {
      const excluded = key === 'pressCardRectoFile'
        || key === 'pressCardVersoFile'
        || key === 'oldCardRectoFile'
        || key === 'oldCardVersoFile'
        || key === 'photoFile'
        || key === 'confirmPassword'
        || (key === 'currentMemberNumber'
          && (!this.isRenewal || !String(rawValue.currentMemberNumber || '').trim()));
      if (!excluded) {
        let value = (rawValue as any)[key];
        
        if (value === true) value = 1;
        if (value === false) value = 0;
        
        formData.append(key, value);
      }
    });

    if (this.selectedFiles['pressCardRectoFile']) formData.append('pressCardRectoFile', this.selectedFiles['pressCardRectoFile']);
    if (this.selectedFiles['pressCardVersoFile']) formData.append('pressCardVersoFile', this.selectedFiles['pressCardVersoFile']);
    if (this.selectedFiles['oldCardRectoFile']) formData.append('oldCardRectoFile', this.selectedFiles['oldCardRectoFile']);
    if (this.selectedFiles['oldCardVersoFile']) formData.append('oldCardVersoFile', this.selectedFiles['oldCardVersoFile']);
    if (this.selectedFiles['photoFile']) formData.append('photoFile', this.selectedFiles['photoFile']);

    this.memberService.submitApplication(formData).subscribe({
      next: (response) => {
        this.saving = false;
        this.router.navigate(['/verifier-email'], {
          state: {
            email: rawValue.email,
            sendOtp: true,
          }
        });
      },
      error: (error) => {
        this.saving = false;
        console.error(error);
        
        const validationErrors = error.error?.errors as Record<string, string[]> | undefined;

        const identityFieldsWithErrors = IDENTITY_FIELDS.filter(field => validationErrors?.[field]?.length);
        for (const field of identityFieldsWithErrors) {
          const control = this.form.controls[field];
          control.setErrors({
            ...(control.errors || {}),
            server: validationErrors![field].join(' '),
          });
          control.markAsTouched();
        }

        if (validationErrors?.['currentMemberNumber']) {
          this.currentStep = 1;
          this.memberNumberError = validationErrors['currentMemberNumber'].join(' ');
          this.form.controls.currentMemberNumber.setErrors({
            ...(this.form.controls.currentMemberNumber.errors || {}),
            server: this.memberNumberError,
          });
          this.form.controls.currentMemberNumber.markAsTouched();
          this.submissionError = this.memberNumberError;
        } else if (identityFieldsWithErrors.length) {
          this.currentStep = 1;
          this.submissionError = identityFieldsWithErrors
            .flatMap(field => validationErrors?.[field] || [])
            .join(' ');
        } else if (validationErrors?.['login'] || validationErrors?.['email']) {
          this.form.controls.email.setErrors({ emailTaken: true });
          this.submissionError = 'Cet e-mail est déjà utilisé pour un compte existant.';
        } else if (validationErrors) {
          this.submissionError = Object.values(validationErrors)
            .flat()
            .join(' ');
        } else {
          this.submissionError = error.error?.message
            || 'L\'enregistrement a échoué. Veuillez vérifier votre connexion internet et la taille de vos fichiers, puis réessayez.';
        }
        
        setTimeout(() => {
          if (validationErrors?.['currentMemberNumber']) {
            const memberNumberInput = document.querySelector<HTMLInputElement>('[formControlName="currentMemberNumber"]');
            memberNumberInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            memberNumberInput?.focus();
            return;
          }

          if (identityFieldsWithErrors.length) {
            const firstIdentityField = identityFieldsWithErrors[0];
            const identityInput = document.querySelector<HTMLInputElement>(`[formControlName="${firstIdentityField}"]`);
            identityInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            identityInput?.focus();
            return;
          }

          document.getElementById('submission-error')?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
          });
        });
      }
    });
  }

  private finishApplication(): void {
    this.isSubmittedSuccessfully = true;
    this.scrollToTop();
  }

  private passwordsMatch(control: AbstractControl): ValidationErrors | null {
    const password = control.get('password')?.value;
    const confirmation = control.get('confirmPassword')?.value;
    return password && confirmation && password !== confirmation ? { passwordsMismatch: true } : null;
  }
}
