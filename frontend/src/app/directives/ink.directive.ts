
import { AfterViewInit, Directive, ElementRef, OnDestroy, inject } from '@angular/core';
import { MotionService } from '../services/motion.service';

@Directive({ selector: '[appInk]', host: { class: 'ink' } })
export class InkDirective implements AfterViewInit, OnDestroy {

  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

  private readonly motion = inject(MotionService);


  private readonly side = (e: PointerEvent): string => {
    const r = this.el.getBoundingClientRect();
    return e.clientY < r.top + r.height / 2 ? '50% 0%' : '50% 100%';
  };


  private readonly enter = (e: PointerEvent) => this.el.style.setProperty('--ink-origin', this.side(e));

  private readonly leave = (e: PointerEvent) => this.el.style.setProperty('--ink-origin', this.side(e));


  ngAfterViewInit(): void {
    if (!this.motion.finePointer || this.motion.reduced) return;
    this.el.addEventListener('pointerenter', this.enter, { passive: true });
    this.el.addEventListener('pointerleave', this.leave, { passive: true });
  }


  ngOnDestroy(): void {
    this.el.removeEventListener('pointerenter', this.enter);
    this.el.removeEventListener('pointerleave', this.leave);
  }
}
