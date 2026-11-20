
import { Directive, OnDestroy, inject, input } from '@angular/core';
import { PreviewService } from '../services/preview.service';

@Directive({
  selector: '[appPreview]',
  host: {
    '(pointerenter)': 'enter($event)',
    '(pointerleave)': 'leave()',
    '(focusin)': 'focus()',
    '(focusout)': 'leave()',
  },
})
export class PreviewDirective implements OnDestroy {


  readonly appPreview = input.required<string>();

  readonly previewLabel = input('');
  readonly previewNumber = input('');

  private readonly preview = inject(PreviewService);


  enter(e: PointerEvent): void {
    if (e.pointerType === 'mouse') this.preview.show(this.appPreview(), this.previewLabel(), this.previewNumber());
  }


  focus(): void {

    this.preview.show(this.appPreview(), this.previewLabel(), this.previewNumber());
  }


  leave(): void {
    this.preview.hide();
  }


  ngOnDestroy(): void {
    this.preview.hide();
  }
}
