
import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { passwordChecks } from '../../../lib/validators';

@Component({
  selector: 'app-password-hints',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './password-hints.html',
  styleUrl: './password-hints.css',
})
export class PasswordHints {

  readonly value = input('');
  protected readonly checks = computed(() => passwordChecks(this.value()));
}
