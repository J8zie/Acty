
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Avatar } from '../../components/ui/avatar/avatar';
import { FieldError } from '../../components/ui/field-error/field-error';
import { PasswordHints } from '../../components/ui/password-hints/password-hints';
import { PageHead } from '../../components/layout/page-head/page-head';
import { AccountService } from '../../api/account.service';
import { AuthService } from '../../api/auth.service';
import { MagneticDirective } from '../../directives';
import { errorMessage, fieldErrors } from '../../api/http';
import { squareAvatar } from '../../lib/image';
import { differentValue, emailAddress, personName, sameValue, strongPassword } from '../../lib/validators';
import { ConfirmService } from '../../services/confirm.service';

type ProfileField = 'firstName' | 'lastName' | 'email';
type PasswordField = 'current' | 'next';

const NAME_RULES = [Validators.required, Validators.minLength(2), Validators.maxLength(60), personName];

const PROFILE_API: Record<string, ProfileField> = { first_name: 'firstName', last_name: 'lastName', email: 'email' };

const PASSWORD_API: Record<string, PasswordField> = { current_password: 'current', password: 'next' };

const ROLE_LABEL = { resident: 'Resident', volunteer: 'Volunteer', admin: 'Admin' } as const;

const MAX_PICK_BYTES = 15 * 1024 * 1024;

@Component({
  selector: 'app-account',
  imports: [ReactiveFormsModule, PageHead, Avatar, MagneticDirective, FieldError, PasswordHints],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './account.html',
  styleUrl: './account.css',
})
export class Account {


  private readonly auth = inject(AuthService);
  private readonly account = inject(AccountService);

  private readonly fb = inject(NonNullableFormBuilder);

  private readonly confirmer = inject(ConfirmService);




  protected readonly user = this.auth.user;



  protected readonly photoBusy = signal(false);

  protected readonly photoNote = signal('');

  protected readonly photoError = signal('');



  protected readonly profileBusy = signal(false);

  protected readonly profileNote = signal('');

  protected readonly profileError = signal('');

  protected readonly profileServer = signal<Partial<Record<ProfileField, string>>>({});


  protected readonly profile = this.fb.group({
    firstName: ['', NAME_RULES],
    lastName: ['', NAME_RULES],
    email: ['', [Validators.required, Validators.maxLength(255), emailAddress]],
  });


  protected readonly pwBusy = signal(false);
  protected readonly pwNote = signal('');
  protected readonly pwError = signal('');

  protected readonly pwServer = signal<Partial<Record<PasswordField, string>>>({});




  protected readonly pw = this.fb.group(
    {
      current: ['', Validators.required],
      next: ['', [Validators.required, strongPassword]],
      again: ['', Validators.required],
    },
    { validators: [sameValue('next', 'again'), differentValue('next', 'current')] },
  );

  constructor() {


    const u = this.user();
    if (u) this.profile.reset({ firstName: u.firstName, lastName: u.lastName, email: u.email });
  }


  protected roleLabel(): string {
    const r = this.user()?.role;
    return r ? ROLE_LABEL[r] : '';
  }




  protected badProfile(name: ProfileField): 'true' | null {
    const c = this.profile.controls[name];
    return (c.touched && c.invalid) || this.profileServer()[name] ? 'true' : null;
  }



  protected pwGroupMessage(): string {
    const e = this.pw.errors;
    if (!e) return '';
    if (e['mismatch'] && this.pw.controls.again.dirty) return 'The two new passwords do not match.';
    if (e['unchanged'] && this.pw.controls.next.dirty) return 'The new password must be different from the current one.';
    return '';
  }


  protected badPw(name: PasswordField | 'again'): 'true' | null {
    const c = this.pw.controls[name];
    const server = name === 'again' ? undefined : this.pwServer()[name];
    return (c.touched && c.invalid) || server ? 'true' : null;
  }






  protected async pick(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';
    if (!file || this.photoBusy()) return;
    this.photoNote.set('');
    this.photoError.set('');

    if (!file.type.startsWith('image/')) {
      this.photoError.set('Please choose a picture (JPG or PNG).');
      return;
    }
    if (file.size > MAX_PICK_BYTES) {
      this.photoError.set('That picture is too big. Choose one under 15 MB.');
      return;
    }
    this.photoBusy.set(true);
    try {
      let small: File;
      try {

        small = await squareAvatar(file);
      } catch {

        this.photoError.set('We could not read that picture. Try a JPG or PNG.');
        return;
      }




      await this.account.setPhoto(small);
      this.photoNote.set('Photo updated.');
    } catch (e) {
      this.photoError.set(errorMessage(e));
    } finally {

      this.photoBusy.set(false);
    }
  }


  protected async removePhoto(): Promise<void> {
    if (this.photoBusy()) return;
    const sure = await this.confirmer.confirm({
      title: 'Remove your photo?',
      message: 'Your initials will be shown instead. You can add a new photo whenever you like.',
      confirmLabel: 'Remove photo',
      tone: 'danger',
    });
    if (!sure) return;
    this.photoNote.set('');
    this.photoError.set('');
    this.photoBusy.set(true);
    try {



      await this.account.removePhoto();
      this.photoNote.set('Photo removed.');
    } catch (e) {
      this.photoError.set(errorMessage(e));
    } finally {
      this.photoBusy.set(false);
    }
  }





  protected async saveProfile(): Promise<void> {
    if (this.profileBusy()) return;

    this.profileNote.set('');
    this.profileError.set('');
    this.profileServer.set({});
    if (this.profile.invalid) {

      this.profile.markAllAsTouched();
      this.profileError.set('Please complete the highlighted fields.');
      return;
    }
    const v = this.profile.getRawValue();
    const email = v.email.trim();
    const current = this.user();

    if (current && v.firstName.trim() === current.firstName && v.lastName.trim() === current.lastName && email.toLowerCase() === current.email.toLowerCase()) {
      this.profileNote.set('Nothing to save: your details are already up to date.');
      return;
    }


    let password: string | undefined;
    if (current && email.toLowerCase() !== current.email.toLowerCase()) {
      const answer = await this.confirmer.ask({
        title: 'Change your email?',
        message: `Your email will change to ${email}. Type your current password to confirm that it is you.`,
        confirmLabel: 'Change email',
        password: { label: 'Current password' },
      });
      if (!answer) return;
      password = answer.password;
    }
    this.profileBusy.set(true);
    try {




      const u = await this.account.updateProfile({ firstName: v.firstName.trim(), lastName: v.lastName.trim(), email }, password);
      this.profile.reset({ firstName: u.firstName, lastName: u.lastName, email: u.email });
      this.profileNote.set('Saved.');
    } catch (e) {



      const mapped: Partial<Record<ProfileField, string>> = {};
      const errors = fieldErrors(e);
      for (const [k, msg] of Object.entries(errors)) {
        const f = PROFILE_API[k];
        if (f) mapped[f] = msg;
      }
      this.profileServer.set(mapped);

      this.profileError.set(errors['current_password'] ?? (Object.keys(mapped).length ? 'Please fix the highlighted fields.' : errorMessage(e)));
    } finally {
      this.profileBusy.set(false);
    }
  }





  protected async savePassword(): Promise<void> {
    if (this.pwBusy()) return;

    this.pwNote.set('');
    this.pwError.set('');
    this.pwServer.set({});
    if (this.pw.invalid) {
      this.pw.markAllAsTouched();
      this.pwError.set(this.pwGroupMessage() || 'Please fix the highlighted fields.');
      return;
    }
    const v = this.pw.getRawValue();

    const sure = await this.confirmer.confirm({
      title: 'Change your password?',
      message: 'You will stay signed in here, but you will be signed out on every other device.',
      confirmLabel: 'Change password',
    });
    if (!sure) return;
    this.pwBusy.set(true);
    try {



      await this.account.changePassword(v.current, v.next);
      this.pw.reset({ current: '', next: '', again: '' });
      this.pwNote.set('Password changed.');
    } catch (e) {



      const mapped: Partial<Record<PasswordField, string>> = {};
      for (const [k, msg] of Object.entries(fieldErrors(e))) {
        const f = PASSWORD_API[k];
        if (f) mapped[f] = msg;
      }
      this.pwServer.set(mapped);
      this.pwError.set(Object.values(mapped)[0] ?? errorMessage(e));
    } finally {
      this.pwBusy.set(false);
    }
  }
}
