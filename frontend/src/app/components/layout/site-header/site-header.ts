
import {
  ChangeDetectionStrategy, Component, DestroyRef, ElementRef, NgZone, computed, effect, inject, signal, viewChild,
} from '@angular/core';
import { AuthService } from '../../../api/auth.service';
import { NAV_LINKS, NavLink } from '../../../data/nav-links';
import { Avatar } from '../../ui/avatar/avatar';
import { LinkDirective, MagneticDirective } from '../../../directives';
import { MotionService } from '../../../services/motion.service';
import { PageTransitionService } from '../../../services/page-transition.service';
import { ThemeToggle } from '../../ui/theme-toggle/theme-toggle';

@Component({
  selector: 'app-site-header',

  imports: [LinkDirective, MagneticDirective, ThemeToggle, Avatar],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './site-header.html',
  styleUrl: './site-header.css',
})
export class SiteHeader {



  protected readonly auth = inject(AuthService);


  private readonly transition = inject(PageTransitionService);

  private readonly motion = inject(MotionService);

  private readonly zone = inject(NgZone);

  private readonly progress = viewChild<ElementRef<HTMLElement>>('progress');



  protected readonly hidden = signal(false);

  protected readonly open = signal(false);


  protected readonly clock = signal(this.now());




  protected readonly links = computed<NavLink[]>(() => NAV_LINKS[this.auth.role() ?? 'visitor']);

  constructor() {

    const destroyRef = inject(DestroyRef);


    let last = window.scrollY;
    const onScroll = () => {
      const y = window.scrollY;


      if (y > last + 6 && y > 140 && performance.now() > this.transition.keepHeaderUntil) this.hidden.set(true);
      else if (y < last - 6 || y < 60) this.hidden.set(false);
      last = y;

      const bar = this.progress()?.nativeElement;
      if (bar) {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.transform = `scaleX(${max > 0 ? Math.min(1, Math.max(0, y / max)) : 0})`;
      }
    };

    this.zone.runOutsideAngular(() => window.addEventListener('scroll', onScroll, { passive: true }));


    const timer = setInterval(() => this.clock.set(this.now()), 20_000);

    destroyRef.onDestroy(() => {
      window.removeEventListener('scroll', onScroll);
      clearInterval(timer);
    });



    effect(() => (this.open() ? this.motion.stop() : this.motion.start()));
  }


  toggleMenu(): void {
    this.open.update((v) => !v);
  }


  close(): void {
    this.open.set(false);
  }


  async logout(): Promise<void> {
    this.close();



    await this.auth.logout();

    void this.transition.go('/', 'Home');
  }


  private now(): string {
    return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Manila' }).format(new Date());
  }
}
