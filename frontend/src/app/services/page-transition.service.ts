
import { Injectable, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { MotionService } from './motion.service';

export interface CurtainApi {
  cover(): Promise<void>;
  reveal(): Promise<void>;
}

const ANCHOR_LABELS: Record<string, string> = { about: 'About', how: 'How it works' };

const HEADER_OFFSET = -72;

const LABELS: Record<string, string> = {
  '': 'Home',
  reports: 'Reports',
  report: 'Report',
  rankings: 'Volunteers',
  login: 'Log in',
  register: 'Join',
  resident: 'Resident',
  volunteer: 'Volunteer',
  admin: 'Admin',
};

@Injectable({ providedIn: 'root' })
export class PageTransitionService {

  private readonly router = inject(Router);
  private readonly motion = inject(MotionService);

  private curtain?: CurtainApi;

  private busy = false;



  readonly label = signal('');

  readonly settled = signal(true);


  register(api: CurtainApi): void {
    this.curtain = api;
  }


  async go(fullUrl: string, label?: string): Promise<void> {

    if (this.busy) return;
    const [url, anchor] = fullUrl.split('#');
    const current = this.router.url.split(/[?#]/)[0];
    const target = url.split('?')[0];
    const sameView = current === target && !url.includes('?');


    if (sameView && anchor) {
      await this.afterRender();
      this.scrollToAnchor(anchor);
      return;
    }


    if (!this.curtain || this.motion.reduced) {
      await this.router.navigateByUrl(url);
      this.motion.scrollTop();
      if (anchor) await this.afterRender().then(() => this.scrollToAnchor(anchor, true));
      return;
    }

    if (sameView) {
      this.motion.scrollTop(false);
      return;
    }


    this.busy = true;
    this.settled.set(false);
    this.label.set((anchor && ANCHOR_LABELS[anchor]) || label || this.labelFor(target));
    try {
      await this.curtain.cover();

      await this.router.navigateByUrl(url);
      this.motion.scrollTop();

      await this.afterRender();
      this.motion.refresh();

      if (anchor) this.scrollToAnchor(anchor, true);
      const lifting = this.curtain.reveal();
      this.settled.set(true);
      await lifting;
    } finally {

      this.settled.set(true);
      this.busy = false;
    }
  }


  keepHeaderUntil = 0;


  scrollToAnchor(id: string, immediate = false): void {
    this.keepHeaderUntil = performance.now() + (immediate ? 400 : 2400);

    const custom = document.getElementById(id)?.dataset['anchorOffset'];
    this.motion.scrollTo('#' + id, { offset: custom ? +custom : HEADER_OFFSET, immediate });
  }


  private afterRender(): Promise<void> {
    return new Promise<void>((r) => requestAnimationFrame(() => requestAnimationFrame(() => r())));
  }


  private labelFor(path: string): string {
    const first = path.split('/').filter(Boolean)[0] ?? '';
    return LABELS[first] ?? 'Actify';
  }
}
