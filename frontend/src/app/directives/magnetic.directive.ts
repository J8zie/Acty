
import { AfterViewInit, Directive, ElementRef, NgZone, OnDestroy, inject, input } from '@angular/core';
import { gsap } from 'gsap';
import { MotionService } from '../services/motion.service';

interface Slot {

  el: HTMLElement;

  strength: number;

  setX: (v: number) => void;
  setY: (v: number) => void;

  cx: number;
  cy: number;

  reach: number;

  x: number;
  y: number;

  vx: number;
  vy: number;

  tx: number;
  ty: number;
}

const slots = new Set<Slot>();

const mouse = { x: -1e4, y: -1e4 };

let bound = false;

let running = false;
let dirty = true;

const STIFFNESS = 190;
const DAMPING = 19;

function measure(): void {
  for (const s of slots) {
    const r = s.el.getBoundingClientRect();

    s.cx = r.left + r.width / 2 - s.x;
    s.cy = r.top + r.height / 2 - s.y;
    s.reach = Math.max(r.width, r.height) * 0.7 + 24;
  }
  dirty = false;
}

function step(_time: number, deltaMs: number): void {
  if (dirty) measure();

  const dt = Math.min(deltaMs, 33) / 1000;
  let moving = false;
  for (const s of slots) {
    const dx = mouse.x - s.cx;
    const dy = mouse.y - s.cy;

    const near = Math.hypot(dx, dy) < s.reach;
    s.tx = near ? dx * s.strength : 0;
    s.ty = near ? dy * s.strength : 0;

    s.vx += (-STIFFNESS * (s.x - s.tx) - DAMPING * s.vx) * dt;
    s.vy += (-STIFFNESS * (s.y - s.ty) - DAMPING * s.vy) * dt;
    s.x += s.vx * dt;
    s.y += s.vy * dt;

    const still = Math.abs(s.x - s.tx) < 0.05 && Math.abs(s.y - s.ty) < 0.05 && Math.abs(s.vx) < 0.05 && Math.abs(s.vy) < 0.05;
    if (still) {
      s.x = s.tx; s.y = s.ty; s.vx = 0; s.vy = 0;
    } else moving = true;
    s.setX(s.x);
    s.setY(s.y);
    if (near) moving = true;
  }

  if (!moving) sleep();
}

function wake(): void {
  if (running) return;
  running = true;
  gsap.ticker.add(step);
}

function sleep(): void {
  running = false;
  gsap.ticker.remove(step);
}

function bind(): void {
  if (bound) return;
  bound = true;
  window.addEventListener('pointermove', (e) => {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
    if (slots.size) wake();
  }, { passive: true });
  window.addEventListener('scroll', () => { dirty = true; }, { passive: true });
  window.addEventListener('resize', () => { dirty = true; });
}

@Directive({ selector: '[appMagnetic]' })
export class MagneticDirective implements AfterViewInit, OnDestroy {


  readonly appMagnetic = input<number | string>(0.25);


  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

  private readonly zone = inject(NgZone);

  private readonly motion = inject(MotionService);

  private slot?: Slot;


  ngAfterViewInit(): void {

    if (!this.motion.finePointer || this.motion.reduced) return;
    const el = this.el;

    if (getComputedStyle(el).display === 'inline') el.style.display = 'inline-block';
    const strength = Number(this.appMagnetic()) || 0.25;
    this.zone.runOutsideAngular(() => {
      this.slot = {
        el, strength,

        setX: gsap.quickSetter(el, 'x', 'px') as (v: number) => void,
        setY: gsap.quickSetter(el, 'y', 'px') as (v: number) => void,
        cx: 0, cy: 0, reach: 0, x: 0, y: 0, vx: 0, vy: 0, tx: 0, ty: 0,
      };
      slots.add(this.slot);

      dirty = true;
      bind();
    });
  }


  ngOnDestroy(): void {
    if (this.slot) slots.delete(this.slot);
    if (!slots.size && running) sleep();
    gsap.killTweensOf(this.el);
  }
}
