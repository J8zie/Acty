
import { Injectable, signal } from '@angular/core';

export type Theme = 'dark' | 'light';

const KEY = 'actify-theme';

const META_COLOR: Record<Theme, string> = { dark: '#05386b', light: '#5cdb95' };

@Injectable({ providedIn: 'root' })
export class ThemeService {


  readonly theme = signal<Theme>(this.read());

  constructor() {

    this.apply(this.theme());
  }


  toggle(origin?: { x: number; y: number }): void {
    const next: Theme = this.theme() === 'dark' ? 'light' : 'dark';
    const root = document.documentElement;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const doc = document as Document & {
      startViewTransition?: (cb: () => void) => { ready: Promise<void> };
    };


    if (!doc.startViewTransition || reduced) {
      root.classList.add('theme-anim');
      this.set(next);
      window.setTimeout(() => root.classList.remove('theme-anim'), 650);
      return;
    }


    const x = origin?.x ?? window.innerWidth / 2;
    const y = origin?.y ?? 0;
    const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));


    const transition = doc.startViewTransition(() => this.set(next));
    transition.ready
      .then(() => {
        root.animate(
          { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
          { duration: 750, easing: 'cubic-bezier(0.16, 1, 0.3, 1)', pseudoElement: '::view-transition-new(root)' },
        );
      })
      .catch(() => undefined);
  }


  set(theme: Theme): void {
    this.theme.set(theme);
    this.apply(theme);
    try {
      localStorage.setItem(KEY, theme);
    } catch {

    }
  }



  private apply(theme: Theme): void {
    document.documentElement.dataset['theme'] = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', META_COLOR[theme]);
  }


  private read(): Theme {
    try {
      const saved = localStorage.getItem(KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch {

    }
    return 'dark';
  }
}
