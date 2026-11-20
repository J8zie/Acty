
import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { LoadState } from '../../../lib/loader';

@Component({
  selector: 'app-load-state',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './load-state.html',
  styleUrl: './load-state.css',
})
export class LoadStateView {



  readonly state = input.required<LoadState>();


  readonly error = input('');

  readonly rows = input(4);


  readonly retry = output<void>();


  protected rowList(): number[] {
    return Array.from({ length: this.rows() }, (_, i) => i);
  }
}
