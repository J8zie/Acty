
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { PageHead } from '../../components/layout/page-head/page-head';
import { ResultBar } from '../../components/ui/result-bar/result-bar';
import { SearchBox } from '../../components/ui/search-box/search-box';
import { ReportList } from '../../components/reports/report-list/report-list';
import { LinkDirective, MagneticDirective } from '../../directives';
import { loader } from '../../lib/loader';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-my-reports',
  imports: [PageHead, ReportList, LoadStateView, LinkDirective, MagneticDirective, SearchBox, ResultBar],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './my-reports.html',
  styleUrl: './my-reports.css',
})
export class MyReports {

  private readonly api = inject(ReportsService);





  protected readonly mine = loader(() => this.api.list({ scope: 'mine' }), []);




  protected readonly toRate = computed(() => this.mine.data().filter((r) => r.status === 'resolved' && !!r.volunteerId && !r.rating));



  protected readonly q = signal('');
  protected readonly status = signal('');

  protected readonly statuses = [
    { value: '', label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'cancelled', label: 'Cancelled' },
  ];

  protected readonly active = computed(() => !!this.q().trim() || !!this.status());


  protected readonly shown = computed(() => {
    const words = this.q().toLowerCase().split(/\s+/).filter(Boolean);
    const status = this.status();
    return this.mine.data().filter((r) => {
      if (status && r.status !== status) return false;
      const hay = `${r.title} ${r.barangay} ${r.category} ${r.description}`.toLowerCase();
      return words.every((w) => hay.includes(w) || (/^#?\d+$/.test(w) && r.number === Number(w.replace('#', ''))));
    });
  });


  protected clear(): void {
    this.q.set('');
    this.status.set('');
  }
}
