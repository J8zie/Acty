
import { Injectable, signal } from '@angular/core';

export interface PreviewState {

  visible: boolean;

  src: string;
  label: string;
  number: string;
}

@Injectable({ providedIn: 'root' })
export class PreviewService {

  readonly state = signal<PreviewState>({ visible: false, src: '', label: '', number: '' });


  show(src: string, label: string, number: string): void {
    this.state.set({ visible: true, src, label, number });
  }


  hide(): void {
    this.state.update((s) => ({ ...s, visible: false }));
  }
}
