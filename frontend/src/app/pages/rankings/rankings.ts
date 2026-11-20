
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { PageHead } from '../../components/layout/page-head/page-head';
import { FadeUpDirective } from '../../directives';
import { loader } from '../../lib/loader';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-rankings',
  imports: [PageHead, LoadStateView, FadeUpDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './rankings.html',
  styleUrl: './rankings.css',
})
export class Rankings {

  private readonly api = inject(ReportsService);





  protected readonly list = loader(() => this.api.rankings(), []);




  protected readonly ranked = computed(() =>
    [...this.list.data()].sort((a, b) => b.completed - a.completed || b.average - a.average),
  );
}
