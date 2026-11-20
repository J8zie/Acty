
import { ChangeDetectionStrategy, Component, OnDestroy, input, output, signal } from '@angular/core';
import { shrinkImage } from '../../../lib/image';

const MAX_SOURCE_BYTES = 12 * 1024 * 1024;
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const MAX_VIDEO_BYTES = 50 * 1024 * 1024;
const IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const VIDEO_TYPES = new Set(['video/mp4', 'video/webm', 'video/quicktime']);
const VIDEO_ACCEPT = 'video/mp4,video/webm,video/quicktime';
const IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp';

@Component({
  selector: 'app-file-drop',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './file-drop.html',
  styleUrl: './file-drop.css',
})
export class FileDrop implements OnDestroy {




  readonly title = input('Add a photo');


  readonly kind = input<'image' | 'video'>('image');


  readonly chosen = output<File | null>();


  protected readonly preview = signal<string | null>(null);

  protected readonly over = signal(false);

  protected readonly busy = signal(false);

  protected readonly problem = signal('');


  ngOnDestroy(): void {
    this.revoke();
  }


  protected readonly accept = () => (this.kind() === 'video' ? VIDEO_ACCEPT : IMAGE_ACCEPT);


  onPick(e: Event): void {
    const input = e.target as HTMLInputElement;
    void this.set(input.files?.[0] ?? null);
    input.value = '';
  }


  onDrop(e: DragEvent): void {
    e.preventDefault();
    this.over.set(false);
    void this.set(e.dataTransfer?.files?.[0] ?? null);
  }


  private async set(file: File | null): Promise<void> {
    this.problem.set('');
    if (!file) return;

    if (this.kind() === 'video') {
      await this.setVideo(file);
      return;
    }

    if (!IMAGE_TYPES.has(file.type)) {
      this.problem.set('Choose a JPG, PNG, or WebP photo.');
      return;
    }
    if (file.size > MAX_SOURCE_BYTES) {
      this.problem.set('That photo is too large. Choose one under 12 MB.');
      return;
    }

    this.busy.set(true);
    try {
      const ready = await shrinkImage(file);
      if (ready.size > MAX_UPLOAD_BYTES) {
        this.problem.set('The photo is still too large to upload. Choose a smaller photo.');
        return;
      }

      this.revoke();
      this.preview.set(URL.createObjectURL(ready));
      this.chosen.emit(ready);
    } catch {
      this.problem.set('That photo could not be read. Choose a JPG, PNG, or WebP photo.');
    } finally {
      this.busy.set(false);
    }
  }


  private async setVideo(file: File): Promise<void> {
    if (!VIDEO_TYPES.has(file.type)) {
      this.problem.set('Choose an MP4, WebM, or MOV video.');
      return;
    }
    if (file.size > MAX_VIDEO_BYTES) {
      this.problem.set('That video is too large. Choose one under 50 MB.');
      return;
    }

    this.revoke();
    this.preview.set(URL.createObjectURL(file));
    this.chosen.emit(file);
  }


  private revoke(): void {
    const url = this.preview();
    if (url) URL.revokeObjectURL(url);
  }
}
