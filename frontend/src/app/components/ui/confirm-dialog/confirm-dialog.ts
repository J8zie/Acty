
import { ChangeDetectionStrategy, Component, ElementRef, effect, inject, signal, viewChild } from '@angular/core';
import { ConfirmService } from '../../../services/confirm.service';
import { MotionService } from '../../../services/motion.service';

@Component({
  selector: 'app-confirm-dialog',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './confirm-dialog.html',
  styleUrl: './confirm-dialog.css',
})
export class ConfirmDialog {
  protected readonly service = inject(ConfirmService);
  private readonly motion = inject(MotionService);


  private readonly panel = viewChild<ElementRef<HTMLElement>>('panel');

  protected readonly password = signal('');

  protected readonly passwordMissing = signal(false);

  private opener: HTMLElement | null = null;

  constructor() {
    effect(() => {
      const open = this.service.current();
      if (open) {
        this.opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        this.password.set('');
        this.passwordMissing.set(false);
        this.motion.stop();

        queueMicrotask(() => this.focusFirst(open.password ? 'input' : open.tone === 'danger' ? '[data-testid="confirm-cancel"]' : '[data-testid="confirm-ok"]'));
      } else {
        this.motion.start();
        this.opener?.focus?.();
        this.opener = null;
      }
    });
  }

  protected cancel(): void {
    this.service.current()?.resolve(null);
  }

  protected ok(): void {
    const open = this.service.current();
    if (!open) return;
    if (open.password) {
      if (!this.password()) {
        this.passwordMissing.set(true);
        this.focusFirst('input');
        return;
      }
      open.resolve({ password: this.password() });
      return;
    }
    open.resolve({});
  }

  protected onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault();
      this.cancel();
      return;
    }
    if (event.key === 'Tab') this.keepFocusInside(event);
  }

  protected onPasswordInput(event: Event): void {
    this.password.set((event.target as HTMLInputElement).value);
    this.passwordMissing.set(false);
  }

  private focusFirst(selector: string): void {
    this.panel()?.nativeElement.querySelector<HTMLElement>(selector)?.focus();
  }


  private keepFocusInside(event: KeyboardEvent): void {
    const nodes = Array.from(this.panel()?.nativeElement.querySelectorAll<HTMLElement>('button, input, [href], [tabindex]:not([tabindex="-1"])') ?? []).filter((n) => !n.hasAttribute('disabled'));
    if (!nodes.length) return;
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && active === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  }
}
