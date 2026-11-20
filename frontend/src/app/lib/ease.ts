
import { gsap } from 'gsap';
import { CustomEase } from 'gsap/CustomEase';

gsap.registerPlugin(CustomEase);

CustomEase.create('settle', '0.22, 0.8, 0.2, 1');
CustomEase.create('glide', '0.7, 0, 0.2, 1');
CustomEase.create('rise', '0.3, 1.3, 0.5, 1');

export const EASE = { settle: 'settle', glide: 'glide', rise: 'rise' } as const;

const GAPS = [0, 0.06, 0.13, 0.17, 0.27, 0.32];
export const rhythm = (i: number): number => GAPS[i % GAPS.length];

export const nudge = (i: number): number => {
  const x = Math.sin((i + 1) * 12.9898) * 43758.5453;
  return x - Math.floor(x);
};
