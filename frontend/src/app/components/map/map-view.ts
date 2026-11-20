
import {
  ChangeDetectionStrategy, Component, ElementRef, OnDestroy, afterNextRender, effect, inject, input, model,
  output, signal, viewChild, ViewEncapsulation,
} from '@angular/core';
import { config } from '../../config';
import { Report, STATUS_LABELS } from '../../models';
import { ThemeService } from '../../services/theme.service';
import { EngineOptions, LatLng, MapEngine } from './engine';
import { StatusBadge } from '../reports/status-badge/status-badge';

export type { LatLng } from './engine';

@Component({
  selector: 'app-map',

  imports: [StatusBadge],
  changeDetection: ChangeDetectionStrategy.OnPush,
  encapsulation: ViewEncapsulation.None,
  host: { class: 'block relative isolate overflow-hidden rounded-xl border border-brand-text/30' },
  templateUrl: './map-view.html',
  styleUrl: './map-view.css',
})
export class MapView implements OnDestroy {





  readonly reports = input<Report[]>([]);

  readonly pick = input(false);


  readonly position = model<LatLng | null>(null);

  readonly me = input<LatLng | null>(null);

  readonly center = input<LatLng>(config.mapCenter);
  readonly zoom = input(14);

  readonly fit = input(false);

  readonly selected = output<string>();



  protected readonly active = signal<Report | null>(null);

  protected readonly hint = signal(false);

  protected readonly fallback = signal(false);


  private readonly theme = inject(ThemeService);

  private readonly host = viewChild.required<ElementRef<HTMLElement>>('host');

  private readonly ready = signal(false);

  private engine?: MapEngine;

  private hintTimer?: ReturnType<typeof setTimeout>;

  private destroyed = false;

  constructor() {

    afterNextRender(() => void this.start());





    effect(() => {
      const theme = this.theme.theme();
      if (this.ready()) this.engine?.setTheme(theme);
    });


    effect(() => {
      const list = this.reports();
      if (this.ready()) {
        this.engine?.setPins(list, this.fit());
        const a = this.active();
        if (a && !list.some((r) => r.id === a.id)) this.active.set(null);
      }
    });

    effect(() => {
      const pos = this.position();
      if (this.ready()) this.engine?.setPick(pos);
    });

    effect(() => {
      const me = this.me();
      if (this.ready()) this.engine?.setMe(me);
    });
  }


  ngOnDestroy(): void {
    this.destroyed = true;
    clearTimeout(this.hintTimer);
    this.engine?.destroy();
  }


  private options(): EngineOptions {
    return {
      center: this.center(),
      zoom: this.zoom(),
      pick: this.pick(),
      theme: this.theme.theme(),

      onPick: (pos) => this.position.set(pos),

      onPin: (r) => this.active.set(r),

      onGestureHint: () => {
        this.hint.set(true);
        clearTimeout(this.hintTimer);
        this.hintTimer = setTimeout(() => this.hint.set(false), 1800);
      },

      onFail: (reason) => {
        console.warn('[Actify map]', reason, 'Falling back to the free OpenStreetMap map.');
        void this.useFreeMap();
      },
    };
  }




  private async start(): Promise<void> {
    const key = config.googleMapsApiKey.trim();
    try {
      if (key) {

        const { GoogleEngine } = await import('./google-engine');
        this.engine = await GoogleEngine.create(this.host().nativeElement, key, this.options());
      } else {
        await this.useFreeMap(false);
        return;
      }
    } catch (e) {
      console.warn('[Actify map]', e);
      await this.useFreeMap();
      return;
    }
    if (!this.destroyed) this.ready.set(true);
  }



  private async useFreeMap(flag = true): Promise<void> {
    if (this.destroyed) return;

    this.ready.set(false);
    this.engine?.destroy();
    const el = this.host().nativeElement;
    el.replaceChildren();
    el.removeAttribute('style');
    el.className = 'absolute inset-0';
    const { LeafletEngine } = await import('./leaflet-engine');
    this.engine = await LeafletEngine.create(el, this.options());
    this.fallback.set(flag);
    if (!this.destroyed) this.ready.set(true);
  }


  protected label(r: Report): string {
    return `${r.title} (${STATUS_LABELS[r.status]})`;
  }
}
