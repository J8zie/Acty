
import {
  ChangeDetectionStrategy, Component, ElementRef, OnDestroy, effect, input, output, signal, untracked, viewChild,
} from '@angular/core';
import { MAX_UPLOAD_BYTES, burnStamp, cameraProblem, fit, isFresh, stampTime, toJpeg } from '../../../lib/live-photo';
import { LivePhoto } from '../../../api/reports.service';

type CamState = 'idle' | 'starting' | 'live' | 'shot';

@Component({
  selector: 'app-camera-capture',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './camera-capture.html',
  styleUrl: './camera-capture.css',
})
export class CameraCapture implements OnDestroy {




  readonly facing = input<'environment' | 'user'>('environment');

  readonly title = input('Take a photo');

  readonly hint = input('');

  readonly prompt = input('');

  readonly stamp = input('');

  readonly value = input<LivePhoto | null>(null);


  readonly shot = output<LivePhoto | null>();



  protected readonly state = signal<CamState>('idle');

  protected readonly error = signal('');

  protected readonly preview = signal('');

  protected readonly at = signal('');

  protected readonly phone = typeof matchMedia !== 'undefined' && matchMedia('(pointer: coarse)').matches;


  private readonly video = viewChild.required<ElementRef<HTMLVideoElement>>('video');

  private stream?: MediaStream;

  constructor() {



    effect(() => {
      const v = this.value();

      untracked(() => {
        if (v && this.state() === 'idle') this.show(v.file, new Date(v.capturedAt));
      });
    });
  }



  ngOnDestroy(): void {
    this.stop();
    this.revoke();
  }



  protected async start(): Promise<void> {
    this.error.set('');

    if (!navigator.mediaDevices?.getUserMedia) {
      this.error.set(
        window.isSecureContext
          ? 'This browser cannot open the camera.'
          : 'The camera only works on a secure page (https). Open the site with https and try again.',
      );
      return;
    }
    this.state.set('starting');
    try {


      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: this.facing() }, width: { ideal: 1280 }, height: { ideal: 960 } },
        audio: false,
      });

      const el = this.video().nativeElement;
      el.srcObject = this.stream;
      await el.play();
      this.state.set('live');
    } catch (e) {

      this.stop();
      this.state.set('idle');
      this.error.set(cameraProblem(e));
    }
  }


  protected cancel(): void {
    this.stop();
    this.state.set('idle');
  }



  protected retake(): void {
    this.revoke();
    this.at.set('');
    this.shot.emit(null);
    void this.start();
  }



  protected async snap(): Promise<void> {
    const el = this.video().nativeElement;
    if (!el.videoWidth) return;

    const { w, h } = fit(el.videoWidth, el.videoHeight);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.drawImage(el, 0, 0, w, h);

    const now = new Date();
    burnStamp(ctx, w, h, this.caption(now));
    const blob = await toJpeg(canvas);

    this.stop();
    if (!blob) {
      this.state.set('idle');
      this.error.set('The picture could not be saved. Please try again.');
      return;
    }
    const file = new File([blob], 'live.jpg', { type: 'image/jpeg', lastModified: now.getTime() });
    this.accept(file, now, 'live');
  }




  protected async onNative(e: Event): Promise<void> {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';
    this.error.set('');
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      this.error.set('That is not a picture.');
      return;
    }

    if (!isFresh(file)) {
      this.error.set('That photo was not taken just now. Please take a new one with the camera.');
      return;
    }
    try {

      const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const { w, h } = fit(bitmap.width, bitmap.height);
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('no canvas');
      ctx.drawImage(bitmap, 0, 0, w, h);
      bitmap.close();

      const taken = new Date(file.lastModified || Date.now());
      burnStamp(ctx, w, h, this.caption(taken));
      const blob = await toJpeg(canvas);
      if (!blob) throw new Error('no blob');
      this.accept(new File([blob], 'camera-app.jpg', { type: 'image/jpeg', lastModified: taken.getTime() }), taken, 'camera-app');
    } catch {
      this.error.set('That photo could not be read. Please try again.');
    }
  }


  private caption(d: Date): string {
    return [this.stamp(), stampTime(d)].filter(Boolean).join(' · ');
  }




  private accept(file: File, when: Date, method: LivePhoto['method']): void {
    if (file.size > MAX_UPLOAD_BYTES) {
      this.state.set('idle');
      this.error.set('That photo is too large to upload. Please take a smaller photo and try again.');
      return;
    }

    this.show(file, when);
    this.shot.emit({ file, capturedAt: when.toISOString(), method });
  }



  private show(file: File, when: Date): void {
    this.revoke();
    this.preview.set(URL.createObjectURL(file));
    this.at.set(new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(when));
    this.state.set('shot');
  }


  private stop(): void {
    this.stream?.getTracks().forEach((t) => t.stop());
    this.stream = undefined;
    try {
      this.video().nativeElement.srcObject = null;
    } catch {

    }
  }


  private revoke(): void {
    const url = this.preview();
    if (url) URL.revokeObjectURL(url);
    this.preview.set('');
  }
}
