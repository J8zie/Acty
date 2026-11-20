
import { computed, inject } from '@angular/core';
import { PageTransitionService } from '../services/page-transition.service';
import { PreloaderService } from '../services/preloader.service';

export function injectReady() {

  const preloader = inject(PreloaderService);
  const transition = inject(PageTransitionService);

  return computed(() => preloader.done() && transition.settled());
}
