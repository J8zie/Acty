
import { ChangeDetectionStrategy, Component, computed, effect, input, signal } from '@angular/core';
import { AbstractControl } from '@angular/forms';
import { validationMessage } from '../../../lib/validators';

@Component({
  selector: 'app-field-error',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './field-error.html',
  styleUrl: './field-error.css',
})
export class FieldError {

  readonly control = input<AbstractControl | null>(null);

  readonly label = input('This field');

  readonly server = input('');

  readonly message = input('');



  private readonly tick = signal(0);

  constructor() {
    effect((onCleanup) => {
      const control = this.control();
      if (!control) return;
      const sub = control.events.subscribe(() => this.tick.update((n) => n + 1));
      onCleanup(() => sub.unsubscribe());
    });
  }


  protected readonly text = computed(() => {
    this.tick();
    if (this.server()) return this.server();
    if (this.message()) return this.message();
    const control = this.control();
    if (!control || !control.touched || !control.invalid) return '';
    return validationMessage(control, this.label());
  });
}
