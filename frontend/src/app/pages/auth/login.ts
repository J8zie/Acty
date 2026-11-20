
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { PageHead } from '../../components/layout/page-head/page-head';
import { AuthService } from '../../api/auth.service';
import { LinkDirective, MagneticDirective } from '../../directives';
import { errorMessage } from '../../api/http';
import { PageTransitionService } from '../../services/page-transition.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, PageHead, LinkDirective, MagneticDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {





  readonly next = input<string>();



  private readonly auth = inject(AuthService);

  private readonly transition = inject(PageTransitionService);

  private readonly fb = inject(NonNullableFormBuilder);



  protected readonly error = signal('');

  protected readonly busy = signal(false);

  protected readonly form = this.fb.group({
    username: ['', Validators.required],
    password: ['', Validators.required],
  });



  protected bad(name: 'username' | 'password'): 'true' | null {
    const c = this.form.controls[name];
    return c.touched && c.invalid ? 'true' : null;
  }


  protected async submit(): Promise<void> {

    if (this.busy()) return;

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('Enter your username and password.');
      return;
    }
    const { username, password } = this.form.getRawValue();
    this.busy.set(true);
    this.error.set('');
    try {



      const user = await this.auth.login(username.trim(), password);
      const next = this.next();



      const safe = next && next.startsWith('/') && !next.startsWith('//') ? next : this.auth.homeFor(user.role);
      void this.transition.go(safe);
    } catch (e) {


      this.error.set(errorMessage(e));
    } finally {
      this.busy.set(false);
    }
  }
}
