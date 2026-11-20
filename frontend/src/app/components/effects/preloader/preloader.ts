
import { EASE } from '../../../lib/ease';
import {
  ChangeDetectionStrategy, Component, ElementRef, NgZone, afterNextRender, inject, signal, viewChild,
} from '@angular/core';
import { gsap } from 'gsap';
import { displayFontReady } from '../../../lib/fonts';
import { MotionService } from '../../../services/motion.service';
import { PreloaderService } from '../../../services/preloader.service';

const SEEN_KEY = 'actify-intro';

@Component({
  selector: 'app-preloader',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './preloader.html',
  styleUrl: './preloader.css',
})
export class Preloader {



  private readonly zone = inject(NgZone);
  private readonly motion = inject(MotionService);
  private readonly preloader = inject(PreloaderService);




  readonly visible = signal(true);

  readonly full = signal(!this.seen());




  protected readonly sparks = Array.from({ length: 14 }, (_, i) => ({
    a: -170 + (i * 160) / 13,
    d: 70 + ((i * 37) % 50),
    r: i % 3 === 0 ? 4.5 : 3,
    accent: i % 2 === 0,
  }));



  private readonly panel = viewChild<ElementRef<HTMLElement>>('panel');
  private readonly content = viewChild<ElementRef<HTMLElement>>('content');
  private readonly scene = viewChild<ElementRef<SVGSVGElement>>('scene');
  private readonly caps = viewChild<ElementRef<HTMLElement>>('caps');
  private readonly bar = viewChild<ElementRef<HTMLElement>>('bar');

  constructor() {


    afterNextRender(() => this.run());
  }


  private seen(): boolean {
    try {
      return sessionStorage.getItem(SEEN_KEY) === '1';
    } catch {

      return false;
    }
  }


  private run(): void {
    const panel = this.panel()?.nativeElement;
    const content = this.content()?.nativeElement;
    const scene = this.scene()?.nativeElement;
    const caps = this.caps()?.nativeElement;
    const bar = this.bar()?.nativeElement;

    if (this.motion.reduced || !panel || !content || !scene || !caps || !bar) {
      this.finish();
      this.visible.set(false);
      return;
    }

    try {
      sessionStorage.setItem(SEEN_KEY, '1');
    } catch {

    }


    this.motion.stop();
    this.zone.runOutsideAngular(() => {
      const master = gsap.timeline({ delay: 0.1 });
      const story = this.story(scene, caps, bar);

      story.timeScale(this.full() ? 1.3 : 2.8);
      master.add(story);


      master.add(() => {
        master.pause();
        void Promise.race([displayFontReady, new Promise((r) => setTimeout(r, 1500))]).then(() => master.resume());
      }, '-=0.3');



      master
        .to(content, { yPercent: -12, opacity: 0, duration: 0.35, ease: 'power3.in' })
        .fromTo(panel, { '--rb': '0vh' }, { '--rb': '16vh', duration: 0.45, ease: 'power2.in' }, '<')
        .to(panel, { yPercent: -100, duration: 0.85, ease: 'expo.inOut' }, '<0.04')
        .to(panel, { '--rb': '0vh', duration: 0.3, ease: 'power2.out' }, '-=0.28')
        .add(() => this.finish(), '-=0.65')
        .add(() => this.visible.set(false));
    });
  }




  private story(scene: SVGSVGElement, caps: HTMLElement, bar: HTMLElement): gsap.core.Timeline {

    const $ = gsap.utils.selector(scene);
    const ground = $('.ground');
    const houses = $('.house');
    const wins = $('.win');
    const halo = $('.halo');
    const ring = $('.ring');
    const persons = $('.person');
    const pinOpen = $('.pin-open');
    const pinDone = $('.pin-done');
    const check = $('.check');
    const sparks = $('.spark');
    const cap = Array.from(caps.children) as HTMLElement[];


    gsap.set(ground, { scaleX: 0, svgOrigin: '320 250' });
    gsap.set(houses, { opacity: 0, y: 18 });
    gsap.set(wins, { opacity: 0 });
    gsap.set(halo, { opacity: 0 });

    gsap.set(persons, { x: (_i, el: Element) => Number(el.getAttribute('data-dir')) * 190, opacity: 0 });
    gsap.set(pinOpen, { y: -330, opacity: 0, svgOrigin: '320 250' });
    gsap.set(pinDone, { scale: 0.5, svgOrigin: '320 250' });
    gsap.set(cap, { yPercent: 115 });
    gsap.set(bar, { scaleX: 0 });
    gsap.set(sparks, { opacity: 0 });
    gsap.set([ring, check], { opacity: 0 });

    const tl = gsap.timeline();

    const to = (el: gsap.TweenTarget, vars: gsap.TweenVars, at: gsap.Position) => tl.to(el, vars, at);



    to(bar, { scaleX: 1, duration: 2.95, ease: 'none' }, 0);
    to(ground, { scaleX: 1, duration: 0.55, ease: 'power3.out' }, 0);
    to(houses, { opacity: 0.6, y: 0, duration: 0.6, stagger: 0.09, ease: 'power3.out' }, 0.1);
    to(cap[0], { yPercent: 0, duration: 0.45, ease: 'expo.out' }, 0.3);
    to(pinOpen, { y: 0, opacity: 1, duration: 0.7, ease: EASE.rise }, 0.3);
    tl.fromTo(halo, { scale: 0.4, opacity: 0.9 }, { scale: 1.7, opacity: 0, duration: 0.7, ease: 'power2.out', repeat: 1, repeatDelay: 0.05, immediateRender: false }, 0.78);


    to(cap[0], { yPercent: -115, duration: 0.3, ease: 'power3.in' }, 1.05);
    to(cap[1], { yPercent: 0, duration: 0.45, ease: 'expo.out' }, 1.34);
    to(persons, { x: 0, opacity: (_i, el: Element) => Number(el.getAttribute('data-o')), duration: 0.6, ease: EASE.rise, stagger: { each: 0.11, from: 'edges' } }, 1.05);


    to(ring, { opacity: 1, duration: 0.01 }, 1.6);
    to(ring, { strokeDashoffset: 0, duration: 1.0, ease: 'power1.inOut' }, 1.6);
    to(persons, { y: -9, duration: 0.17, ease: 'power2.out', yoyo: true, repeat: 5, stagger: 0.06 }, 1.65);
    to(pinOpen, { rotation: 4, duration: 0.14, yoyo: true, repeat: 5, ease: 'sine.inOut' }, 1.65);


    to(cap[1], { yPercent: -115, duration: 0.28, ease: 'power3.in' }, 2.6);
    to(cap[2], { yPercent: 0, duration: 0.45, ease: 'expo.out' }, 2.9);
    to(pinOpen, { opacity: 0, scale: 0.7, rotation: 0, duration: 0.2, ease: 'power2.in' }, 2.62);
    to(pinDone, { opacity: 1, scale: 1, duration: 0.5, ease: EASE.rise }, 2.62);
    to(check, { opacity: 1, duration: 0.01 }, 2.82);
    to(check, { strokeDashoffset: 0, duration: 0.35, ease: 'power2.out' }, 2.82);

    sparks.forEach((s, i) => {
      const a = (Number(s.getAttribute('data-a')) * Math.PI) / 180;
      const d = Number(s.getAttribute('data-d'));
      tl.fromTo(s, { opacity: 1, x: 0, y: 0, scale: 1 }, { x: Math.cos(a) * d, y: Math.sin(a) * d, opacity: 0, scale: 0.2, duration: 0.8, ease: 'power3.out', immediateRender: false }, 2.66 + i * 0.008);
    });
    to(wins, { opacity: 1, duration: 0.3, stagger: 0.08 }, 2.75);
    to(houses, { opacity: 1, duration: 0.4 }, 2.75);
    to(persons, { y: -14, duration: 0.16, ease: 'power2.out', yoyo: true, repeat: 1, stagger: 0.04 }, 2.78);
    return tl;
  }



  private finish(): void {
    this.motion.start();
    this.motion.refresh();
    this.preloader.finish();
  }
}
