
import { Injectable, NgZone, inject } from '@angular/core';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import '../lib/ease';

gsap.registerPlugin(ScrollTrigger);

ScrollTrigger.config({ ignoreMobileResize: true });

@Injectable({ providedIn: 'root' })
export class MotionService {


  private readonly zone = inject(NgZone);

  private lenis?: Lenis;
  private started = false;



  readonly reduced =
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;


  readonly finePointer =
    typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine)').matches;


  init(): void {
    if (this.started || this.reduced) return;
    this.started = true;

    this.zone.runOutsideAngular(() => {
      const lenis = new Lenis({ lerp: 0.1, smoothWheel: true });
      this.lenis = lenis;

      lenis.on('scroll', () => ScrollTrigger.update());
      gsap.ticker.add((time) => lenis.raf(time * 1000));
      gsap.ticker.lagSmoothing(0);
    });
  }


  stop(): void {
    this.lenis?.stop();
  }


  start(): void {
    this.lenis?.start();
  }


  scrollTop(immediate = true): void {
    if (this.lenis) this.lenis.scrollTo(0, { immediate, force: true });
    else window.scrollTo(0, 0);
  }


  scrollTo(target: string | number | HTMLElement, opts: { offset?: number; immediate?: boolean } = {}): void {
    const offset = opts.offset ?? 0;

    const el = typeof target === 'string' ? document.querySelector<HTMLElement>(target) : typeof target === 'number' ? null : target;
    if (typeof target !== 'number' && !el) return;
    if (this.lenis) {
      this.lenis.resize();
      this.lenis.scrollTo(el ?? (target as number), { offset, duration: 1.4, immediate: opts.immediate, force: true });
      return;
    }

    const top = el ? el.getBoundingClientRect().top + window.scrollY + offset : (target as number);
    window.scrollTo({ top, behavior: opts.immediate || this.reduced ? 'auto' : 'smooth' });
  }


  refresh(): void {
    this.zone.runOutsideAngular(() => ScrollTrigger.refresh());
  }
}
