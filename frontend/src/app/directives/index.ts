
import { CountUpDirective } from './count-up.directive';
import { CursorParallaxDirective } from './cursor-parallax.directive';
import { FadeUpDirective } from './fade-up.directive';
import { ImageRevealDirective } from './image-reveal.directive';
import { InkDirective } from './ink.directive';
import { LinkDirective } from './link.directive';
import { MagneticDirective } from './magnetic.directive';
import { ParallaxDirective } from './parallax.directive';
import { PreviewDirective } from './preview.directive';
import { RevealDirective } from './reveal.directive';

export {
  CountUpDirective, CursorParallaxDirective, FadeUpDirective, ImageRevealDirective, InkDirective, LinkDirective, MagneticDirective,
  ParallaxDirective, PreviewDirective, RevealDirective,
};

export const MOTION = [
  CountUpDirective, CursorParallaxDirective, FadeUpDirective, ImageRevealDirective, InkDirective, LinkDirective, MagneticDirective,
  ParallaxDirective, PreviewDirective, RevealDirective,
] as const;
