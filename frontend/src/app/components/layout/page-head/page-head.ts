
import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RevealDirective } from '../../../directives';

@Component({
  selector: 'app-page-head',

  imports: [RevealDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './page-head.html',
  styleUrl: './page-head.css',
})
export class PageHead {



  readonly eyebrow = input('');

  readonly meta = input('');


  readonly title = input.required<string>();

  readonly lead = input('');
}
