
import { Injectable, signal } from '@angular/core';

export interface ConfirmOptions {

  title: string;

  message?: string;

  details?: string[];

  confirmLabel?: string;

  cancelLabel?: string;

  tone?: 'normal' | 'danger';

  password?: { label?: string; hint?: string };
}

export interface ConfirmAnswer {

  password?: string;
}

export interface OpenConfirm extends ConfirmOptions {
  resolve: (answer: ConfirmAnswer | null) => void;
}

@Injectable({ providedIn: 'root' })
export class ConfirmService {

  readonly current = signal<OpenConfirm | null>(null);


  ask(options: ConfirmOptions): Promise<ConfirmAnswer | null> {

    this.current()?.resolve(null);
    return new Promise((resolve) => {
      this.current.set({
        ...options,
        resolve: (answer) => {
          this.current.set(null);
          resolve(answer);
        },
      });
    });
  }


  async confirm(options: ConfirmOptions): Promise<boolean> {
    return (await this.ask(options)) !== null;
  }
}
