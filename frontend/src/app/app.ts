
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ConfirmDialog } from './components/ui/confirm-dialog/confirm-dialog';
import { PageCurtain } from './components/effects/page-curtain/page-curtain';
import { Preloader } from './components/effects/preloader/preloader';
import { PreviewCursor } from './components/effects/preview-cursor/preview-cursor';
import { SiteFooter } from './components/layout/site-footer/site-footer';
import { SiteHeader } from './components/layout/site-header/site-header';
import { MotionService } from './services/motion.service';
import { ThemeService } from './services/theme.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Preloader, PageCurtain, SiteHeader, SiteFooter, PreviewCursor, ConfirmDialog],
  templateUrl: './app.html',
  styleUrl: './app.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class App {
  constructor() {


    inject(ThemeService);
    inject(MotionService).init();
  }
}
