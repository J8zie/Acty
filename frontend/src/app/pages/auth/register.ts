
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { PageHead } from '../../components/layout/page-head/page-head';
import { FieldError } from '../../components/ui/field-error/field-error';
import { PasswordHints } from '../../components/ui/password-hints/password-hints';
import { AuthService } from '../../api/auth.service';
import { LinkDirective, MagneticDirective } from '../../directives';
import { errorMessage, fieldErrors } from '../../api/http';
import { emailAddress, personName, strongPassword, usernameFormat } from '../../lib/validators';
import { PageTransitionService } from '../../services/page-transition.service';

type Field = 'firstName' | 'lastName' | 'username' | 'email' | 'password';

const API_FIELD: Record<string, Field> = {
  first_name: 'firstName', last_name: 'lastName', username: 'username', email: 'email', password: 'password',
};

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, PageHead, LinkDirective, MagneticDirective, FieldError, PasswordHints],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './register.html',
  styleUrl: './register.css',
})
export class Register {


  private readonly auth = inject(AuthService);

  private readonly transition = inject(PageTransitionService);

  private readonly fb = inject(NonNullableFormBuilder);



  protected readonly error = signal('');

  protected readonly busy = signal(false);


  protected readonly server = signal<Partial<Record<Field, string>>>({});




  protected readonly form = this.fb.group({
    role: this.fb.control<'resident' | 'volunteer'>('resident'),
    firstName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(60), personName]],
    lastName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(60), personName]],
    username: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(30), usernameFormat]],
    email: ['', [Validators.required, Validators.maxLength(255), emailAddress]],
    password: ['', [Validators.required, strongPassword]],
  });

  protected readonly showPassword = signal(false);



  protected bad(name: Field): 'true' | null {
    const c = this.form.controls[name];
    return (c.touched && c.invalid) || this.server()[name] ? 'true' : null;
  }


  protected async submit(): Promise<void> {

    if (this.busy()) return;

    this.server.set({});

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('Please complete the highlighted fields.');
      return;
    }
    const v = this.form.getRawValue();
    this.busy.set(true);
    this.error.set('');
    try {




      const user = await this.auth.register({ ...v, username: v.username.trim(), email: v.email.trim() });
      void this.transition.go(this.auth.homeFor(user.role));
    } catch (e) {




      const mapped: Partial<Record<Field, string>> = {};
      for (const [k, msg] of Object.entries(fieldErrors(e))) {
        const f = API_FIELD[k];
        if (f) mapped[f] = msg;
      }
      this.server.set(mapped);
      this.error.set(Object.keys(mapped).length ? 'Please fix the highlighted fields.' : errorMessage(e));
    } finally {
      this.busy.set(false);
    }
  }
}
