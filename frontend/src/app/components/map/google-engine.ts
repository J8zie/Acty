/// <reference types="google.maps" />
import { Report } from '../../models';
import { EngineOptions, LatLng, MapEngine, MapTheme, pinHtml } from './engine';
import { GOOGLE_STYLES } from './google-styles';

declare global {
  interface Window {
    __actifyGoogleReady?: () => void;
    gm_authFailure?: () => void;
  }
}

let loading: Promise<void> | undefined;

function loadGoogle(key: string): Promise<void> {
  if (loading) return loading;
  loading = new Promise<void>((resolve, reject) => {

    if (typeof window.google?.maps?.importLibrary === 'function') {
      resolve();
      return;
    }
    window.__actifyGoogleReady = () => resolve();
    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&loading=async&callback=__actifyGoogleReady`;
    s.async = true;
    s.onerror = () => reject(new Error('The Google Maps script could not be loaded.'));
    document.head.appendChild(s);
  }).then(async () => {
    await google.maps.importLibrary('core');
    await google.maps.importLibrary('maps');
  });

  loading.catch(() => (loading = undefined));
  return loading;
}

function createPinClass() {
  return class Pin extends google.maps.OverlayView {

    readonly el = document.createElement('div');
    constructor(private pos: google.maps.LatLng, cls: string, label: string, onClick?: () => void) {
      super();
      this.el.style.cssText = 'position:absolute;transform:translate(-50%,-50%);';
      this.el.innerHTML = pinHtml(cls, label);

      if (onClick) {
        const hit = this.el.firstElementChild as HTMLElement;
        hit.style.cursor = 'pointer';
        this.el.addEventListener('click', (e) => {

          e.stopPropagation();
          onClick();
        });
        hit.addEventListener('keydown', (e) => {
          if ((e as KeyboardEvent).key === 'Enter' || (e as KeyboardEvent).key === ' ') onClick();
        });
      }
    }


    override onAdd(): void {
      this.getPanes()?.overlayMouseTarget.appendChild(this.el);
    }
    override draw(): void {
      const p = this.getProjection()?.fromLatLngToDivPixel(this.pos);
      if (p) {
        this.el.style.left = `${p.x}px`;
        this.el.style.top = `${p.y}px`;
      }
    }
    override onRemove(): void {
      this.el.remove();
    }
    moveTo(pos: google.maps.LatLng): void {
      this.pos = pos;
      this.draw();
    }
  };
}

export class GoogleEngine implements MapEngine {
  private map!: google.maps.Map;

  private Pin!: ReturnType<typeof createPinClass>;
  private pins: InstanceType<ReturnType<typeof createPinClass>>[] = [];
  private pick?: InstanceType<ReturnType<typeof createPinClass>>;
  private me?: InstanceType<ReturnType<typeof createPinClass>>;

  private onPin: (r: Report) => void = () => undefined;


  static async create(host: HTMLElement, key: string, opts: EngineOptions): Promise<GoogleEngine> {

    window.gm_authFailure = () => opts.onFail('Google Maps rejected the API key.');
    await loadGoogle(key);
    const engine = new GoogleEngine();
    engine.init(host, opts);
    return engine;
  }


  private init(host: HTMLElement, opts: EngineOptions): void {
    this.Pin = createPinClass();
    this.onPin = opts.onPin;
    this.map = new google.maps.Map(host, {
      center: opts.center,
      zoom: opts.zoom,
      styles: GOOGLE_STYLES[opts.theme],
      disableDefaultUI: true,
      zoomControl: true,
      clickableIcons: false,

      gestureHandling: 'cooperative',
      draggableCursor: opts.pick ? 'crosshair' : undefined,
    });

    if (opts.pick) {
      this.map.addListener('click', (e: google.maps.MapMouseEvent) => {
        if (e.latLng) opts.onPick({ lat: +e.latLng.lat().toFixed(6), lng: +e.latLng.lng().toFixed(6) });
      });
    }
  }


  setTheme(theme: MapTheme): void {
    this.map.setOptions({ styles: GOOGLE_STYLES[theme] });
  }


  private pin(pos: LatLng, cls: string, label: string, onClick?: () => void) {
    const p = new this.Pin(new google.maps.LatLng(pos.lat, pos.lng), cls, label, onClick);
    p.setMap(this.map);
    return p;
  }



  setPins(reports: Report[], fit: boolean): void {
    this.pins.forEach((p) => p.setMap(null));
    this.pins = reports.map((r) => this.pin({ lat: r.latitude, lng: r.longitude }, 'is-' + r.status, r.title, () => this.onPin(r)));
    if (fit && reports.length) {
      const bounds = new google.maps.LatLngBounds();
      reports.forEach((r) => bounds.extend({ lat: r.latitude, lng: r.longitude }));
      this.map.fitBounds(bounds, 56);
      google.maps.event.addListenerOnce(this.map, 'idle', () => {
        if ((this.map.getZoom() ?? 0) > 16) this.map.setZoom(16);
      });
    }
  }


  setPick(pos: LatLng | null): void {
    this.pick?.setMap(null);
    this.pick = undefined;
    if (!pos) return;
    this.pick = this.pin(pos, 'is-pick', 'Selected location');
    this.map.panTo(pos);
    if ((this.map.getZoom() ?? 0) < 16) this.map.setZoom(16);
  }


  setMe(pos: LatLng | null): void {
    this.me?.setMap(null);
    this.me = undefined;
    if (pos) this.me = this.pin(pos, 'is-me', 'You are here');
  }


  destroy(): void {
    this.pins.forEach((p) => p.setMap(null));
    this.pick?.setMap(null);
    this.me?.setMap(null);
    google.maps.event.clearInstanceListeners(this.map);
  }
}
