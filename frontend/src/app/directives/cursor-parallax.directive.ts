
import { AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, inject, input } from '@angular/core';
import { gsap } from 'gsap';
import { MotionService } from '../services/motion.service';

@Directive({ selector: '[appCursorParallax]' })
export class CursorParallaxDirective implements AfterViewInit, OnDestroy {

  readonly appCursorParallax = input<number | string>(16);


  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

  private readonly zone = inject(NgZone);

  private readonly motion = inject(MotionService);

  private move?: (e: PointerEvent) => void;


  ngAfterViewInit(): void {
    if (!this.motion.finePointer || this.motion.reduced) return;
    const depth = Number(this.appCursorParallax()) || 16;
    this.zone.runOutsideAngular(() => {


      const xTo = gsap.quickTo(this.el, 'x', { duration: 1.1, ease: 'power3.out' });
      const yTo = gsap.quickTo(this.el, 'y', { duration: 1.1, ease: 'power3.out' });
      this.move = (e: PointerEvent) => {


        xTo((e.clientX / window.innerWidth - 0.5) * -2 * depth);
        yTo((e.clientY / window.innerHeight - 0.5) * -2 * depth);
      };

      window.addEventListener('pointermove', this.move, { passive: true });
    });
  }


  ngOnDestroy(): void {
    if (this.move) window.removeEventListener('pointermove', this.move);
    gsap.killTweensOf(this.el);
  }
}
