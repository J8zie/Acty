
import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { FadeUpDirective, InkDirective, LinkDirective, PreviewDirective } from '../../../directives';
import { Report } from '../../../models';
import { reportImage } from '../../../lib/report-image';
import { StatusBadge } from '../status-badge/status-badge';

@Component({
  selector: 'app-report-list',
  imports: [FadeUpDirective, InkDirective, LinkDirective, PreviewDirective, StatusBadge],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-list.html',
  styleUrl: './report-list.css',
})
export class ReportList {

  readonly reports = input.required<Report[]>();


  readonly emptyTitle = input('Nothing here yet.');
  readonly emptyText = input('');


  protected image(r: Report): string {
    return reportImage(r);
  }


  protected pad(n: number): string {
    return String(n).padStart(3, '0');
  }
}
