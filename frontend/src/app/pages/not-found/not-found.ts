
import { ChangeDetectionStrategy, Component } from '@angular/core';
import { LinkDirective, MagneticDirective, RevealDirective } from '../../directives';

@Component({
  selector: 'app-not-found',

  imports: [LinkDirective, MagneticDirective, RevealDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './not-found.html',
  styleUrl: './not-found.css',
})
export class NotFound {}
