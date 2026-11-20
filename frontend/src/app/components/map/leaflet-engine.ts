
import type * as Leaflet from 'leaflet';
import { config } from '../../config';
import { Report } from '../../models';
import { EngineOptions, LatLng, MapEngine, MapTheme, pinHtml } from './engine';

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export class LeafletEngine implements MapEngine {

  private L!: typeof Leaflet;
  private map!: Leaflet.Map;
  private tiles?: Leaflet.TileLayer;

  private pins?: Leaflet.LayerGroup;

  private pickMarker?: Leaflet.Marker;
  private meMarker?: Leaflet.Marker;

  private observer?: ResizeObserver;


  static async create(host: HTMLElement, opts: EngineOptions): Promise<LeafletEngine> {
    const mod = await import('leaflet');
    const engine = new LeafletEngine();
    engine.L = ((mod as unknown as { default?: typeof Leaflet }).default ?? mod) as typeof Leaflet;
    engine.init(host, opts);
    return engine;
  }


  private init(host: HTMLElement, opts: EngineOptions): void {
    const L = this.L;

    const touch = L.Browser.mobile || window.matchMedia('(pointer: coarse)').matches;
    const map = L.map(host, {
      center: [opts.center.lat, opts.center.lng],
      zoom: opts.zoom,
      zoomControl: true,
      scrollWheelZoom: false,

      dragging: !touch,
      attributionControl: true,
    });
    this.map = map;
    this.pins = L.layerGroup().addTo(map);



    if (opts.pick) {
      map.getContainer().classList.add('is-pick');
      map.on('click', (e: Leaflet.LeafletMouseEvent) =>
        opts.onPick({ lat: +e.latlng.lat.toFixed(6), lng: +e.latlng.lng.toFixed(6) }),
      );
    }



    if (touch) {
      const el = map.getContainer();
      el.addEventListener('touchstart', (e: TouchEvent) => {
        if (e.touches.length >= 2) map.dragging.enable();
        else opts.onGestureHint();
      }, { passive: true });
      el.addEventListener('touchend', (e: TouchEvent) => {
        if (e.touches.length < 2) map.dragging.disable();
      }, { passive: true });
    }


    this.observer = new ResizeObserver(() => map.invalidateSize());
    this.observer.observe(host);
    this.setTheme(opts.theme);
    this.onPin = opts.onPin;
  }


  private onPin: (r: Report) => void = () => undefined;



  setTheme(theme: MapTheme): void {




    this.map.getContainer().classList.toggle('map-plain', !config.mapTint[theme]);

    if (this.tiles) return;
    this.tiles = this.L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(this.map);
  }


  private icon(cls: string, label: string): Leaflet.DivIcon {
    return this.L.divIcon({ className: '', html: pinHtml(cls, label), iconSize: [36, 36], iconAnchor: [18, 18] });
  }




  setPins(reports: Report[], fit: boolean): void {
    const L = this.L;
    if (!this.pins) return;
    this.pins.clearLayers();
    for (const r of reports) {
      L.marker([r.latitude, r.longitude], { icon: this.icon('is-' + r.status, r.title), keyboard: true })
        .on('click', () => this.onPin(r))
        .addTo(this.pins);
    }
    if (fit && reports.length) {
      this.map.fitBounds(L.latLngBounds(reports.map((r) => [r.latitude, r.longitude] as [number, number])), {
        padding: [56, 56],
        maxZoom: 16,
      });
    }
  }



  setPick(pos: LatLng | null): void {
    if (!pos) {
      this.pickMarker?.remove();
      this.pickMarker = undefined;
      return;
    }
    if (!this.pickMarker) this.pickMarker = this.L.marker([pos.lat, pos.lng], { icon: this.icon('is-pick', 'Selected location') }).addTo(this.map);
    else this.pickMarker.setLatLng([pos.lat, pos.lng]);
    this.map.flyTo([pos.lat, pos.lng], Math.max(this.map.getZoom(), 16), { duration: 0.8 });
  }


  setMe(pos: LatLng | null): void {
    if (!pos) {
      this.meMarker?.remove();
      this.meMarker = undefined;
      return;
    }
    if (!this.meMarker) this.meMarker = this.L.marker([pos.lat, pos.lng], { icon: this.icon('is-me', 'You are here'), interactive: false }).addTo(this.map);
    else this.meMarker.setLatLng([pos.lat, pos.lng]);
  }


  destroy(): void {
    this.observer?.disconnect();
    this.map?.remove();
  }
}
