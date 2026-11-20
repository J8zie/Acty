
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { PageHead } from '../../components/layout/page-head/page-head';
import { FilterOption, FilterSelect } from '../../components/ui/filter-select/filter-select';
import { ResultBar } from '../../components/ui/result-bar/result-bar';
import { SearchBox } from '../../components/ui/search-box/search-box';
import { FadeUpDirective, LinkDirective, MagneticDirective, CountUpDirective } from '../../directives';
import { errorMessage } from '../../api/http';
import { listQuery } from '../../lib/list-query';
import { loader } from '../../lib/loader';
import { Report, ReportStatus, STATUS_LABELS, Stats } from '../../models';
import { ConfirmService } from '../../services/confirm.service';
import { ReportQuery, ReportsService } from '../../api/reports.service';

type StatusFilter = '' | ReportStatus;

@Component({
  selector: 'app-dashboard',
  imports: [PageHead, LoadStateView, FadeUpDirective, LinkDirective, MagneticDirective, CountUpDirective, SearchBox, FilterSelect, ResultBar],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css',
})
export class Dashboard {

  private readonly api = inject(ReportsService);

  private readonly confirmer = inject(ConfirmService);



  protected readonly list = listQuery({
    defaults: { q: '', status: '', category: '', sort: '' },
    perPage: 30,
    fetch: (f, page, perPage) =>
      this.api.listPage({
        q: f.q,
        status: (f.status || undefined) as ReportStatus | undefined,
        categoryId: f.category || undefined,
        sort: (f.sort || undefined) as ReportQuery['sort'],
        page,
        limit: perPage,
      }),
  });



  protected readonly queue = loader(() => this.api.list({ review: 'pending' }), [] as Report[]);

  private readonly categories = loader(() => this.api.categories(), []);
  protected readonly categoryOptions = computed<FilterOption[]>(() => this.categories.data().map((c) => ({ value: c.id, label: c.name })));



  protected readonly counts = signal<Stats | null>(null);


  protected readonly reviewing = signal<string | null>(null);

  protected readonly reviewProblem = signal('');
  private readonly refreshedPrivateImageReports = new Set<string>();


  protected readonly toReview = computed(() => this.queue.data().filter((r) => r.source === 'guest' && r.reporter));

  protected readonly pending = signal<string | null>(null);

  protected readonly problem = signal('');


  protected readonly statusOptions = (Object.keys(STATUS_LABELS) as ReportStatus[]).map((value) => ({ value, label: STATUS_LABELS[value] }));

  protected readonly filters: { value: StatusFilter; label: string }[] = [{ value: '', label: 'All' }, ...this.statusOptions];
  protected readonly sortOptions: FilterOption[] = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'urgency', label: 'Most urgent first' },
    { value: 'status', label: 'By status' },
  ];


  protected readonly stats = computed(() => {
    const c = this.counts();
    return c
      ? [
          { label: 'Reports', value: c.total },
          { label: 'Open', value: c.open },
          { label: 'In progress', value: c.inProgress },
          { label: 'Resolved', value: c.resolved },
        ]
      : [];
  });


  protected readonly remaining = computed(() => Math.max(0, this.list.total() - this.list.items().length));

  constructor() {
    void this.refreshStats();
  }


  private async refreshStats(): Promise<void> {
    try {
      this.counts.set(await this.api.stats());
    } catch {

    }
  }

  protected async refreshPrivateImageLinks(id: string): Promise<void> {
    if (this.refreshedPrivateImageReports.has(id)) return;
    this.refreshedPrivateImageReports.add(id);

    try {
      const fresh = await this.api.get(id);
      this.queue.data.update((all) => all.map((report) => (report.id === id ? fresh : report)));
    } catch {
      this.reviewProblem.set('The private photos could not be refreshed. Reload the review queue and try again.');
    }
  }



  protected async decide(r: Report, decision: 'approve' | 'reject'): Promise<void> {
    const approve = decision === 'approve';
    const sure = await this.confirmer.confirm({
      title: approve ? 'Approve this report?' : 'Reject this report?',
      message: approve
        ? 'It becomes public as an open request and volunteers can accept it. Its problem photo becomes public too; the selfie stays private.'
        : 'It stays hidden from everyone. The reporter’s details and pictures are deleted after 30 days.',
      details: [`#${r.number} · ${r.title}`, r.reporter ? `${r.reporter.firstName} ${r.reporter.lastName} · ${r.reporter.phone}` : ''].filter(Boolean),
      confirmLabel: approve ? 'Approve' : 'Reject',
      tone: approve ? 'normal' : 'danger',
    });
    if (!sure) return;
    this.reviewing.set(r.id);
    this.reviewProblem.set('');
    try {



      await this.api.review(r.id, decision);
      this.queue.data.update((all) => all.filter((x) => x.id !== r.id));
      if (approve) void this.list.reload();
      void this.refreshStats();
    } catch (e) {
      this.reviewProblem.set(errorMessage(e));
    } finally {
      this.reviewing.set(null);
    }
  }



  protected async setStatus(r: Report, event: Event): Promise<void> {
    const select = event.target as HTMLSelectElement;
    const status = select.value as ReportStatus;
    if (status === r.status) return;

    const cancelling = status === 'cancelled';
    const freeingVolunteer = status === 'open' && r.volunteerId && r.taskStatus !== 'complete';
    if (cancelling || freeingVolunteer) {
      const sure = await this.confirmer.confirm({
        title: cancelling ? 'Cancel this report?' : 'Reopen this request?',
        message: cancelling
          ? 'It will be marked as cancelled. Use this for duplicates or reports that should not have been filed.'
          : `${r.volunteerName ?? 'The volunteer'} will lose this task and any volunteer can accept the request again.`,
        details: [`#${r.number} · ${r.title}`],
        confirmLabel: cancelling ? 'Cancel report' : 'Reopen request',
        cancelLabel: 'Keep as it is',
        tone: cancelling ? 'danger' : 'normal',
      });
      if (!sure) {
        select.value = r.status;
        return;
      }
    }

    this.pending.set(r.id);
    this.problem.set('');
    try {



      const updated = await this.api.setStatus(r.id, status);
      this.list.items.update((all) => all.map((x) => (x.id === r.id ? { ...x, ...updated, status: updated.status ?? status } : x)));
      void this.refreshStats();
    } catch (e) {

      select.value = r.status;
      this.problem.set(errorMessage(e));
    } finally {
      this.pending.set(null);
    }
  }


  protected async remove(r: Report): Promise<void> {
    const sure = await this.confirmer.confirm({
      title: 'Delete this report?',
      message: 'This cannot be undone. The report, its volunteer task and every photo are removed for good.',
      details: [`#${r.number} · ${r.title}`, r.volunteerName ? `Volunteer: ${r.volunteerName}` : ''].filter(Boolean),
      confirmLabel: 'Delete report',
      tone: 'danger',
    });
    if (!sure) return;
    this.pending.set(r.id);
    this.problem.set('');
    try {


      await this.api.remove(r.id);
      this.list.removeLocal((x) => x.id === r.id);
      void this.refreshStats();
    } catch (e) {
      this.problem.set(errorMessage(e));
    } finally {
      this.pending.set(null);
    }
  }
}
