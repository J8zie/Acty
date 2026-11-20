
import { Directive, computed, inject, input } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map } from 'rxjs';
import { PageTransitionService } from '../services/page-transition.service';

@Directive({
  selector: 'a[appLink]',
  host: {
    '[attr.href]': 'appLink()',
    '[attr.data-active]': 'active()',
    '(click)': 'onClick($event)',
  },
})
export class LinkDirective {


  readonly appLink = input.required<string>();


  readonly linkLabel = input<string>();


  readonly exact = input(false);



  private readonly router = inject(Router);
  private readonly transition = inject(PageTransitionService);


  private readonly url = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      map(() => this.router.url),
    ),
    { initialValue: this.router.url },
  );



  readonly active = computed(() => {
    if (this.appLink().includes('#')) return null;

    const path = this.url().split(/[?#]/)[0];
    const target = this.appLink().split(/[?#]/)[0];

    if (this.exact() || target === '/') return path === target ? 'true' : null;
    return path === target || path.startsWith(target + '/') ? 'true' : null;
  });


  onClick(e: MouseEvent): void {

    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const anchor = e.currentTarget as HTMLAnchorElement;

    if (anchor.target && anchor.target !== '_self') return;

    e.preventDefault();
    void this.transition.go(this.appLink(), this.linkLabel());
  }
}
