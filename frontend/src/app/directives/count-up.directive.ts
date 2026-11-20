
import {
  AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, effect, inject, input, signal, untracked,
} from '@angular/core';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { MotionService } from '../services/motion.service';
import { EASE } from '../lib/ease';
import { injectReady } from '../lib/inject-ready';

@Directive({ selector: '[appCountUp]' })
export class CountUpDirective implements AfterViewInit, OnDestroy {


  readonly appCountUp = input.required<number>();


  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

  private readonly zone = inject(NgZone);

  private readonly motion = inject(MotionService);

  private readonly ready = injectReady();


  private readonly seen = signal(false);

  private shown = 0;

  private trigger?: ScrollTrigger;
  private tween?: gsap.core.Tween;

  constructor() {



    effect(() => {
      const target = this.appCountUp();
      const visible = this.seen();
      untracked(() => {

        if (this.motion.reduced) {
          this.el.textContent = String(target);
          return;
        }
        if (visible) this.run(target);
      });
    });
  }


  ngAfterViewInit(): void {
    this.el.textContent = '0';
    if (this.motion.reduced) return;
    this.zone.runOutsideAngular(() => {
      this.trigger = ScrollTrigger.create({
        trigger: this.el,

        start: 'top 92%',
        once: true,

        onEnter: () => this.zone.run(() => this.seen.set(true)),
      });
    });
  }


  ngOnDestroy(): void {
    this.trigger?.kill();
    this.tween?.kill();
  }



  private run(target: number): void {
    this.tween?.kill();
    const state = { v: this.shown };
    this.zone.runOutsideAngular(() => {
      this.tween = gsap.to(state, {
        v: target,
        duration: 1.3,
        ease: EASE.settle,

        delay: this.ready() ? 0 : 0.4,
        onUpdate: () => {
          this.shown = state.v;
          this.el.textContent = String(Math.round(state.v));
        },

        onComplete: () => {
          this.shown = target;
          this.el.textContent = String(target);
        },
      });
    });
  }
}
