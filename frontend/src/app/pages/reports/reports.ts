
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { MapView } from '../../components/map/map-view';
import { PageHead } from '../../components/layout/page-head/page-head';
import { ReportList } from '../../components/reports/report-list/report-list';
import { FilterOption, FilterSelect } from '../../components/ui/filter-select/filter-select';
import { ResultBar } from '../../components/ui/result-bar/result-bar';
import { SearchBox } from '../../components/ui/search-box/search-box';
import { AuthService } from '../../api/auth.service';
import { LinkDirective, MagneticDirective } from '../../directives';
import { BARANGAYS } from '../../data/barangays';
import { listQuery } from '../../lib/list-query';
import { loader } from '../../lib/loader';
import { ReportStatus, STATUS_LABELS, URGENCY_LABELS, Urgency } from '../../models';
import { PageTransitionService } from '../../services/page-transition.service';
import { ReportQuery, ReportsService } from '../../api/reports.service';

const SORTS: FilterOption[] = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'urgency', label: 'Most urgent first' },
  { value: 'status', label: 'By status' },
];

@Component({
  selector: 'app-reports',
  imports: [PageHead, ReportList, MapView, LoadStateView, LinkDirective, MagneticDirective, SearchBox, FilterSelect, ResultBar],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './reports.html',
  styleUrl: './reports.css',
})
export class Reports {



  private readonly api = inject(ReportsService);
  private readonly transition = inject(PageTransitionService);
  private readonly auth = inject(AuthService);



  protected readonly fileLink = computed(() => {
    switch (this.auth.role()) {
      case null: return { to: '/report', label: 'File a report' };
      case 'resident': return { to: '/resident/reports/new', label: 'File a report' };
      default: return null;
    }
  });


  private readonly categories = loader(() => this.api.categories(), []);
  protected readonly categoryOptions = computed<FilterOption[]>(() => this.categories.data().map((c) => ({ value: c.id, label: c.name })));



  protected readonly list = listQuery({
    defaults: { q: '', status: '', category: '', urgency: '', barangay: '', sort: '' },
    perPage: 30,
    fetch: (f, page, perPage) =>
      this.api.listPage({
        q: f.q,
        status: (f.status || undefined) as ReportStatus | undefined,
        categoryId: f.category || undefined,
        urgency: (f.urgency || undefined) as Urgency | undefined,
        barangay: f.barangay || undefined,
        sort: (f.sort || undefined) as ReportQuery['sort'],
        page,
        limit: perPage,
      }),
  });


  protected readonly view = signal<'list' | 'map'>('list');


  protected readonly statuses: { value: '' | ReportStatus; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'open', label: STATUS_LABELS.open },
    { value: 'in_progress', label: STATUS_LABELS.in_progress },
    { value: 'resolved', label: STATUS_LABELS.resolved },
  ];
  protected readonly urgencyOptions: FilterOption[] = (Object.keys(URGENCY_LABELS) as Urgency[]).map((value) => ({ value, label: URGENCY_LABELS[value] }));
  protected readonly barangayOptions: FilterOption[] = BARANGAYS.map((b) => ({ value: b, label: b }));
  protected readonly sortOptions = SORTS;


  protected readonly remaining = computed(() => Math.max(0, this.list.total() - this.list.items().length));



  protected open(id: string): void {
    void this.transition.go('/reports/' + id, 'Report');
  }
}
