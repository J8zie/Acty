
import { ChangeDetectionStrategy, Component, input, model } from '@angular/core';

@Component({
  selector: 'app-search-box',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './search-box.html',
  styleUrl: './search-box.css',
})
export class SearchBox {

  readonly value = model('');

  readonly label = input('Search');

  readonly placeholder = input('Search…');

  readonly testId = input('search');

  protected onInput(event: Event): void {
    this.value.set((event.target as HTMLInputElement).value);
  }
}
