
import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  selector: 'app-result-bar',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './result-bar.html',
  styleUrl: './result-bar.css',
})
export class ResultBar {

  readonly shown = input.required<number>();

  readonly total = input.required<number>();

  readonly noun = input('results');

  readonly filtered = input(false);

  readonly busy = input(false);

  readonly clear = output<void>();
}
