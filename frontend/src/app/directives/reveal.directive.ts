
import {
  AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, effect, inject, input, signal, untracked,
} from '@angular/core';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { MotionService } from '../services/motion.service';
import { EASE, nudge } from '../lib/ease';
import { injectReady } from '../lib/inject-ready';

@Directive({ selector: '[appReveal]' })
export class RevealDirective implements AfterViewInit, OnDestroy {


  readonly appReveal = input<'load' | 'scroll' | ''>('scroll');

  readonly revealDelay = input(0);


  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;


  private readonly zone = inject(NgZone);

  private readonly motion = inject(MotionService);

  private readonly ready = injectReady();


  private readonly prepared = signal(false);

  private words: HTMLElement[] = [];

  private marks: HTMLElement[] = [];
  private markColors: string[] = [];

  private armed = false;

  private trigger?: ScrollTrigger;
  private tween?: gsap.core.Tween;
  private markTween?: gsap.core.Tween;

  constructor() {



    effect(() => {
      if (this.ready() && this.prepared()) untracked(() => this.arm());
    });
  }


  ngAfterViewInit(): void {

    if (this.motion.reduced) return;
    this.split();
    this.zone.runOutsideAngular(() => {


      gsap.set(this.words, { yPercent: 108, rotation: 3.5, transformOrigin: '0% 100%' });

      this.marks = Array.from(this.el.querySelectorAll<HTMLElement>('.mark'));
      this.markColors = this.marks.map((m) => getComputedStyle(m).color);
      this.marks.forEach((m) => gsap.set(m, { backgroundSize: '0% 0.94em', color: getComputedStyle(m.parentElement ?? this.el).color }));
    });
    this.prepared.set(true);
  }



  ngOnDestroy(): void {
    this.trigger?.kill();
    this.tween?.kill();
    this.markTween?.kill();
  }





  private split(): void {

    const walk = (from: Node, into: Node): void => {
      from.childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {

          for (const part of (node.textContent ?? '').split(/(\s+)/)) {
            if (!part) continue;
            if (/^\s+$/.test(part)) {
              into.appendChild(document.createTextNode(' '));
              continue;
            }
            const mask = document.createElement('span');
            mask.className = 'rv-mask';
            const word = document.createElement('span');
            word.className = 'rv-word';
            word.textContent = part;
            mask.append(word);
            into.appendChild(mask);
            this.words.push(word);
          }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
          const copy = (node as Element).cloneNode(false) as Element;
          walk(node, copy);
          into.appendChild(copy);
        }
      });
    };

    const frag = document.createDocumentFragment();
    walk(this.el, frag);
    this.el.replaceChildren(frag);
  }



  private arm(): void {
    if (this.armed) return;
    this.armed = true;
    this.zone.runOutsideAngular(() => {

      const play = () => {
        const n = this.words.length;

        const each = Math.min(0.045, 0.7 / Math.max(1, n));
        const lead = this.revealDelay();
        this.tween = gsap.to(this.words, {
          yPercent: 0,
          rotation: 0,
          duration: 0.95,
          ease: EASE.settle,

          delay: (i: number) => lead + i * each + (nudge(i) - 0.5) * each * 1.2,
          clearProps: 'transform',
        });
        if (this.marks.length) {

          const landed = lead + n * each * 0.7 + 0.25;
          this.markTween = gsap.to(this.marks, {
            backgroundSize: '100% 0.94em',
            color: (i: number) => this.markColors[i],
            duration: 0.7,
            ease: EASE.settle,
            delay: landed,
            stagger: 0.12,
            clearProps: 'backgroundSize,color',
          });
        }
      };
      if (this.appReveal() === 'load') {
        play();
      } else {

        this.trigger = ScrollTrigger.create({ trigger: this.el, start: 'top 90%', once: true, onEnter: play });
      }
    });
  }
}
