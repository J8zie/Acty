
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MagneticDirective } from '../../../directives';
import { ThemeService } from '../../../services/theme.service';

@Component({
  selector: 'app-theme-toggle',

  imports: [MagneticDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './theme-toggle.html',
  styleUrl: './theme-toggle.css',
})
export class ThemeToggle {


  protected readonly theme = inject(ThemeService);



  toggle(e: MouseEvent): void {
    const el = e.currentTarget as HTMLElement;
    const r = el.getBoundingClientRect();

    const keyboard = e.detail === 0;
    this.theme.toggle({ x: keyboard ? r.left + r.width / 2 : e.clientX, y: keyboard ? r.top + r.height / 2 : e.clientY });
  }
}
