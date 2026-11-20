
import {
  AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, effect, inject, signal, untracked,
} from '@angular/core';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { MotionService } from '../services/motion.service';
import { EASE } from '../lib/ease';
import { injectReady } from '../lib/inject-ready';

@Directive({ selector: '[appImageReveal]' })
export class ImageRevealDirective implements AfterViewInit, OnDestroy {

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
    this.zone.runOutsideAngular(() => gsap.set(this.el, { clipPath: 'inset(100% 0% 0% 0% round 28px)', scale: 1.07 }));
    this.prepared.set(true);
  }


  ngOnDestroy(): void {
    this.trigger?.kill();
    this.tween?.kill();
  }


  private arm(): void {
    if (this.armed) return;
    this.armed = true;
    this.zone.runOutsideAngular(() => {
      this.trigger = ScrollTrigger.create({
        trigger: this.el,

        start: 'top 90%',
        once: true,
        onEnter: () => {
          this.tween = gsap.to(this.el, {
            clipPath: 'inset(0% 0% 0% 0% round 28px)',
            scale: 1,
            duration: 1.25,
            ease: EASE.settle,

            clearProps: 'scale',
          });
        },
      });
    });
  }
}
