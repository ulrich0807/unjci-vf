import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

@Component({
  selector: 'app-contact',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './contact.component.html',
  styleUrl: './contact.component.css',
})
export class ContactComponent {
  submitted = false;
  sent = false;

  readonly form = new FormGroup({
    fullName: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(2)],
    }),
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    phone: new FormControl('', { nonNullable: true }),
    subject: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    message: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(10)],
    }),
    consent: new FormControl(false, {
      nonNullable: true,
      validators: [Validators.requiredTrue],
    }),
  });

  submit(): void {
    this.submitted = true;
    this.sent = false;

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.sent = true;
    this.submitted = false;
    this.form.reset();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}
