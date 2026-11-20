
import { Report } from '../../models';

export interface LatLng {
  lat: number;
  lng: number;
}

export type MapTheme = 'dark' | 'light';

export interface MapEngine {

  setTheme(theme: MapTheme): void;

  setPins(reports: Report[], fit: boolean): void;

  setPick(pos: LatLng | null): void;

  setMe(pos: LatLng | null): void;

  destroy(): void;
}

export interface EngineOptions {

  center: LatLng;
  zoom: number;


  pick: boolean;
  theme: MapTheme;

  onPick: (pos: LatLng) => void;

  onPin: (report: Report) => void;

  onGestureHint: () => void;

  onFail: (reason: string) => void;
}

export function pinHtml(cls: string, label: string): string {

  const delay = Math.round(Math.random() * 450);
  return `<span class="map-hit" role="button" tabindex="0" aria-label="${label.replace(/"/g, '&quot;')}"><span class="map-pin ${cls}" style="--d:${delay}ms"></span></span>`;
}
