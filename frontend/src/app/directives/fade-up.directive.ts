
import {
  AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, effect, inject, input, signal, untracked,
} from '@angular/core';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { MotionService } from '../services/motion.service';
import { EASE, rhythm } from '../lib/ease';
import { injectReady } from '../lib/inject-ready';

@Directive({ selector: '[appFadeUp]' })
export class FadeUpDirective implements AfterViewInit, OnDestroy {


  readonly appFadeUp = input<number | string>(0);


  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

  private readonly zone = inject(NgZone);

  private readonly motion = inject(MotionService);

  private readonly ready = injectReady();


  private readonly prepared = signal(false);

  private armed = false;

  private trigger?: ScrollTrigger;
  private tween?: gsap.core.Tween;

  constructor() {


    effect(() => {
      if (this.ready() && this.prepared()) untracked(() => this.arm());
    });
  }


  ngAfterViewInit(): void {
    if (this.motion.reduced) return;
    this.zone.runOutsideAngular(() => gsap.set(this.el, { opacity: 0, y: 26 }));
    this.prepared.set(true);
  }


  ngOnDestroy(): void {
    this.trigger?.kill();
    this.tween?.kill();
  }


  private arm(): void {
    if (this.armed) return;
    this.armed = true;
    const index = Number(this.appFadeUp()) || 0;
    this.zone.runOutsideAngular(() => {
      this.trigger = ScrollTrigger.create({
        trigger: this.el,

        start: 'top 94%',
        once: true,
        onEnter: () => {
          this.tween = gsap.to(this.el, {
            opacity: 1,
            y: 0,
            duration: 0.9,
            ease: EASE.settle,

            delay: rhythm(index),

            clearProps: 'transform,opacity',
          });
        },
      });
    });
  }
}
