
import { AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, inject, input } from '@angular/core';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { MotionService } from '../services/motion.service';

@Directive({ selector: '[appParallax]' })
export class ParallaxDirective implements AfterViewInit, OnDestroy {


  readonly appParallax = input<number | string>(8);


  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

  private readonly zone = inject(NgZone);

  private readonly motion = inject(MotionService);

  private tween?: gsap.core.Tween;


  ngAfterViewInit(): void {

    if (this.motion.reduced || !this.motion.finePointer) return;
    const amount = Number(this.appParallax()) || 8;
    this.zone.runOutsideAngular(() => {

      gsap.set(this.el, { scale: 1 + (amount * 2) / 100 + 0.04 });




      this.tween = gsap.fromTo(
        this.el,
        { yPercent: -amount },
        {
          yPercent: amount,
          ease: 'none',
          scrollTrigger: { trigger: this.el.parentElement ?? this.el, start: 'top bottom', end: 'bottom top', scrub: true },
        },
      );
    });
  }


  ngOnDestroy(): void {
    this.tween?.scrollTrigger?.kill();
    this.tween?.kill();
  }
}
