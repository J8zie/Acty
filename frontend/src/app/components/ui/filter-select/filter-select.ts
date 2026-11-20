
import { ChangeDetectionStrategy, Component, input, model } from '@angular/core';

export interface FilterOption {

  value: string;

  label: string;
}

@Component({
  selector: 'app-filter-select',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './filter-select.html',
  styleUrl: './filter-select.css',
})
export class FilterSelect {

  readonly label = input.required<string>();

  readonly options = input.required<readonly FilterOption[]>();

  readonly value = model('');

  readonly allLabel = input('All');

  readonly testId = input('filter');

  readonly withAll = input(true);

  protected onChange(event: Event): void {
    this.value.set((event.target as HTMLSelectElement).value);
  }
}
