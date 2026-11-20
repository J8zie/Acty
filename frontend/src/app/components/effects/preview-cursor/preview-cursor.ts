
import {
  ChangeDetectionStrategy, Component, ElementRef, NgZone, OnDestroy, afterNextRender, effect, inject, signal, viewChild,
} from '@angular/core';
import { gsap } from 'gsap';
import { MotionService } from '../../../services/motion.service';
import { PreviewService } from '../../../services/preview.service';

@Component({
  selector: 'app-preview-cursor',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './preview-cursor.html',
  styleUrl: './preview-cursor.css',
})
export class PreviewCursor implements OnDestroy {


  protected readonly preview = inject(PreviewService);



  protected readonly failed = signal('');

  protected readonly enabled = inject(MotionService).finePointer && !inject(MotionService).reduced;


  private readonly zone = inject(NgZone);


  private readonly box = viewChild<ElementRef<HTMLElement>>('box');

  private move?: (e: PointerEvent) => void;

  private shown = false;


  private last = { x: -999, y: -999 };

  constructor() {

    afterNextRender(() => this.setup());


    effect(() => {
      const visible = this.preview.state().visible;
      const el = this.box()?.nativeElement;
      if (el) this.toggle(el, visible);
    });
  }


  ngOnDestroy(): void {
    if (this.move) window.removeEventListener('pointermove', this.move);
  }


  private setup(): void {
    const el = this.box()?.nativeElement;
    if (!el) return;
    this.zone.runOutsideAngular(() => {

      const xTo = gsap.quickTo(el, 'x', { duration: 0.7, ease: 'power3.out' });
      const yTo = gsap.quickTo(el, 'y', { duration: 0.7, ease: 'power3.out' });

      const place = (x: number, y: number) => {

        const w = el.offsetWidth;
        const tx = x + 28 + w > window.innerWidth ? x - 28 - w : x + 28;
        xTo(tx);
        yTo(Math.max(12, y - el.offsetHeight / 2));
      };
      this.move = (e: PointerEvent) => {
        this.last = { x: e.clientX, y: e.clientY };
        if (this.shown) place(e.clientX, e.clientY);
      };

      window.addEventListener('pointermove', this.move, { passive: true });
    });
  }


  private toggle(el: HTMLElement, visible: boolean): void {
    this.zone.runOutsideAngular(() => {
      if (visible) {


        if (!this.shown) {
          const w = el.offsetWidth;
          const tx = this.last.x + 28 + w > window.innerWidth ? this.last.x - 28 - w : this.last.x + 28;
          gsap.set(el, { x: tx, y: Math.max(12, this.last.y - el.offsetHeight / 2) });
        }
        this.shown = true;

        gsap.to(el, { autoAlpha: 1, scale: 1, duration: 0.55, ease: 'expo.out', overwrite: 'auto' });
      } else {
        this.shown = false;
        gsap.to(el, { autoAlpha: 0, scale: 0.9, duration: 0.4, ease: 'power3.out', overwrite: 'auto' });
      }
    });
  }
}
