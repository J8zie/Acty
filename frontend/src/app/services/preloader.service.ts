
import { Injectable, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class PreloaderService {


  readonly done = signal(false);


  finish(): void {
    this.done.set(true);
  }
}
