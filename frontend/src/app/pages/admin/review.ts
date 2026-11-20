
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { PageHead } from '../../components/layout/page-head/page-head';
import { errorMessage } from '../../api/http';
import { loader } from '../../lib/loader';
import { Report } from '../../models';
import { ConfirmService } from '../../services/confirm.service';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-review',
  imports: [PageHead, LoadStateView],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './review.html',
  styleUrl: './review.css',
})
export class Review {

  private readonly api = inject(ReportsService);

  private readonly confirmer = inject(ConfirmService);



  protected readonly queue = loader(() => this.api.list({ review: 'pending' }), [] as Report[]);


  protected readonly reviewing = signal<string | null>(null);

  protected readonly reviewProblem = signal('');
  private readonly refreshedPrivateImageReports = new Set<string>();


  protected readonly toReview = computed(() => this.queue.data().filter((r) => r.source === 'guest' && r.reporter));


  protected readonly remaining = computed(() => Math.max(0, this.queue.data().length - this.toReview().length));


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
        ? 'It becomes public as an open request and volunteers can accept it. Its problem photo and video become public too; the selfie stays private.'
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
    } catch (e) {
      this.reviewProblem.set(errorMessage(e));
    } finally {
      this.reviewing.set(null);
    }
  }
}
