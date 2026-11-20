
import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

export const NAME_PATTERN = /^[\p{L}\p{M}][\p{L}\p{M}\s.'’-]*$/u;
/** Letters, digits and underscore. Backend: App\Rules\Patterns::USERNAME */
export const USERNAME_PATTERN = /^[A-Za-z0-9_]+$/;
/** Digits only, 7-15 long. Backend: App\Rules\PhoneNumber */
export const PHONE_SHAPE = /^\d+$/;

const text = (c: AbstractControl): string => String(c.value ?? '').trim();

/** A person's name (first or last). */
export const personName: ValidatorFn = (c) => (!text(c) || NAME_PATTERN.test(text(c)) ? null : { personName: true });

export const usernameFormat: ValidatorFn = (c) => (!text(c) || USERNAME_PATTERN.test(text(c)) ? null : { usernameFormat: true });

export const phoneNumber: ValidatorFn = (c) => {
  const v = text(c);
  if (!v) return null;
  const digits = v.replace(/\D/g, '').length;
  return PHONE_SHAPE.test(v) && digits >= 7 && digits <= 15 ? null : { phoneNumber: true };
};

export const emailAddress: ValidatorFn = (c) => (!text(c) || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(text(c)) ? null : { emailAddress: true });

export function passwordChecks(value: string): { label: string; ok: boolean }[] {
  return [
    { label: 'At least 8 characters', ok: value.length >= 8 },
    { label: 'A letter', ok: /\p{L}/u.test(value) },
    { label: 'A number', ok: /\d/.test(value) },
  ];
}

export const strongPassword: ValidatorFn = (c) => {
  const v = String(c.value ?? '');
  if (!v) return null;
  const errors: ValidationErrors = {};
  if (v.length < 8) errors['passwordShort'] = true;
  if (v.length > 72) errors['passwordLong'] = true;
  if (!/\p{L}/u.test(v)) errors['passwordLetter'] = true;
  if (!/\d/.test(v)) errors['passwordNumber'] = true;
  return Object.keys(errors).length ? errors : null;
};

export function sameValue(first: string, second: string): ValidatorFn {
  return (group) => (group.get(first)?.value === group.get(second)?.value ? null : { mismatch: true });
}

export function differentValue(first: string, second: string): ValidatorFn {
  return (group) => {
    const a = group.get(first)?.value;
    return a && a === group.get(second)?.value ? { unchanged: true } : null;
  };
}

export function validationMessage(control: AbstractControl | null, label: string): string {
  const e = control?.errors;
  if (!e) return '';
  if (e['required']) return `${label} is required.`;
  if (e['minlength']) return `${label} must be at least ${e['minlength'].requiredLength} characters.`;
  if (e['maxlength']) return `${label} must be at most ${e['maxlength'].requiredLength} characters.`;
  if (e['personName']) return `${label} may only contain letters, spaces, dots, apostrophes and hyphens.`;
  if (e['usernameFormat']) return `${label} may only contain letters, numbers and underscores.`;
  if (e['phoneNumber']) return 'Enter a phone number with digits only, like 09123456789.';
  if (e['emailAddress'] || e['email']) return 'Enter a valid email address, like name@example.com.';
  if (e['passwordShort']) return `${label} must be at least 8 characters.`;
  if (e['passwordLong']) return `${label} must be at most 72 characters.`;
  if (e['passwordLetter']) return `${label} needs at least one letter.`;
  if (e['passwordNumber']) return `${label} needs at least one number.`;
  if (e['min'] || e['max']) return `${label} is out of range.`;
  return `${label} is not valid.`;
}
