
import { ChangeDetectionStrategy, Component, ElementRef, afterNextRender, inject, viewChild } from '@angular/core';
import { gsap } from 'gsap';
import { EASE } from '../../../lib/ease';
import { PageTransitionService } from '../../../services/page-transition.service';

@Component({
  selector: 'app-page-curtain',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './page-curtain.html',
  styleUrl: './page-curtain.css',
})
export class PageCurtain {


  protected readonly transition = inject(PageTransitionService);


  private readonly panel = viewChild.required<ElementRef<HTMLElement>>('panel');
  private readonly label = viewChild.required<ElementRef<HTMLElement>>('label');

  constructor() {


    this.transition.register({ cover: () => this.cover(), reveal: () => this.reveal() });

    afterNextRender(() => gsap.set(this.panel().nativeElement, { yPercent: 100 }));
  }


  private main(): HTMLElement | null {
    return document.getElementById('main');
  }



  private cover(): Promise<void> {
    const panel = this.panel().nativeElement;
    const label = this.label().nativeElement;
    const main = this.main();
    return new Promise((resolve) => {



      gsap.set(panel, { visibility: 'visible', pointerEvents: 'auto', yPercent: 100, '--rt': '14vh', '--rb': '0vh' });
      gsap.set(label, { yPercent: 110 });
      const tl = gsap.timeline({
        onComplete: () => {

          if (main) gsap.set(main, { clearProps: 'transform,opacity' });
          resolve();
        },
      });

      tl.to(panel, { yPercent: 0, duration: 0.55, ease: EASE.glide })
        .to(panel, { '--rt': '0vh', duration: 0.55, ease: 'power2.in' }, '<')
        .to(label, { yPercent: 0, duration: 0.5, ease: EASE.settle }, '-=0.28');

      if (main) tl.to(main, { y: -48, duration: 0.55, ease: EASE.glide }, 0);
    });
  }




  private reveal(): Promise<void> {
    const panel = this.panel().nativeElement;
    const label = this.label().nativeElement;
    const main = this.main();
    return new Promise((resolve) => {
      const tl = gsap.timeline({
        onComplete: () => {

          gsap.set(panel, { visibility: 'hidden', pointerEvents: 'none', yPercent: 100, '--rt': '14vh', '--rb': '0vh' });
          if (main) gsap.set(main, { clearProps: 'transform,opacity' });
          resolve();
        },
      });
      tl.to(label, { yPercent: -110, duration: 0.3, ease: 'power3.in' })
        .to(panel, { '--rb': '14vh', duration: 0.38, ease: 'power2.in' }, '-=0.12')
        .to(panel, { yPercent: -100, duration: 0.7, ease: EASE.glide }, '<')
        .to(panel, { '--rb': '0vh', duration: 0.3, ease: 'power2.out' }, '-=0.28');

      if (main) tl.fromTo(main, { y: 56 }, { y: 0, duration: 0.9, ease: EASE.settle }, '<0.1');
    });
  }
}
