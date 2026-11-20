
import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { MapView } from '../../components/map/map-view';
import { StatusBadge } from '../../components/reports/status-badge/status-badge';
import { AuthService } from '../../api/auth.service';
import { MOTION } from '../../directives';
import { errorMessage } from '../../api/http';
import { loader } from '../../lib/loader';
import { ASSISTANCE_LABELS, Report, URGENCY_LABELS } from '../../models';
import { categoryImage, reportImage } from '../../lib/report-image';
import { ConfirmService } from '../../services/confirm.service';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-report-detail',




  imports: [...MOTION, MapView, StatusBadge, LoadStateView],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-detail.html',
  styleUrl: './report-detail.css',
})
export class ReportDetail {



  readonly id = input.required<string>();




  protected readonly api = inject(ReportsService);
  protected readonly auth = inject(AuthService);

  private readonly confirmer = inject(ConfirmService);









  protected readonly item = loader(() => this.api.get(this.id()), null as unknown as Report, false);



  private readonly others = loader(() => this.api.list({ limit: 6 }), [] as Report[]);





  protected readonly imgStage = signal(0);



  protected readonly accepted = signal(false);


  protected readonly busy = signal(false);

  protected readonly problem = signal('');




  protected readonly src = computed(() => {
    const r = this.item.data();
    if (!r || this.imgStage() >= 2) return '';
    return this.imgStage() === 1 ? categoryImage(r) : reportImage(r);
  });



  protected readonly more = computed(() => this.others.data().filter((r) => r.id !== this.id()).slice(0, 2));

  constructor() {



    effect(() => {
      this.id();

      untracked(() => {


        this.imgStage.set(0);
        this.accepted.set(false);
        this.problem.set('');

        void this.item.reload();
      });
    });
  }




  protected readonly facts = computed(() => {
    const r = this.item.data();
    if (!r) return [];
    return [
      { k: 'Reported by', v: r.fullName || '—' },
      { k: 'Date', v: r.createdAt },
      { k: 'Barangay', v: r.barangay },
      { k: 'Urgency', v: URGENCY_LABELS[r.urgency] },
      { k: 'Help needed', v: ASSISTANCE_LABELS[r.assistanceType] },
      { k: 'Volunteer', v: r.volunteerName ?? 'Not yet matched' },
    ];
  });





  protected readonly timeline = computed(() => {
    const r = this.item.data();
    if (!r) return [];
    const started = r.taskStatus === 'in_progress' || r.taskStatus === 'complete';
    return [
      { label: 'Reported', done: true, date: r.createdAt, note: r.fullName },
      { label: 'Accepted by a volunteer', done: !!r.volunteerId, date: r.acceptedAt ?? '', note: r.volunteerName ?? '' },
      { label: 'Work in progress', done: started, date: '', note: '' },
      { label: 'Complete', done: r.taskStatus === 'complete', date: r.completedAt ?? '', note: '' },
    ];
  });


  protected pad(n: number): string {
    return String(n).padStart(3, '0');
  }


  protected stars(n: number): string {
    return '★'.repeat(n) + '☆'.repeat(5 - n);
  }




  protected canAccept(r: Report): boolean {
    return this.auth.role() === 'volunteer' && r.status === 'open';
  }




  protected isOwner(r: Report): boolean {
    const u = this.auth.user();
    return !!u && u.id === r.userId;
  }












  protected canRate(r: Report): boolean {
    return this.isOwner(r) && r.status === 'resolved' && !r.rating && !!r.volunteerId;
  }




  protected ratingSoon(r: Report): boolean {
    return this.isOwner(r) && !!r.volunteerId && (r.status === 'open' || r.status === 'in_progress');
  }







  protected async accept(r: Report): Promise<void> {
    const sure = await this.confirmer.confirm({
      title: 'Accept this request?',
      message: 'It moves to your tasks and other volunteers will no longer see it. Only accept it if you can really help.',
      details: [r.title, r.barangay],
      confirmLabel: 'Accept request',
    });
    if (!sure) return;
    await this.act(async () => {

      this.item.data.set(await this.api.accept(r.id));
      this.accepted.set(true);
    });
  }







  protected async rate(r: Report, n: number): Promise<void> {

    const sure = await this.confirmer.confirm({
      title: `Send ${n} ${n === 1 ? 'star' : 'stars'}?`,
      message: 'The volunteer gets this rating. You cannot change it afterwards.',
      details: [`${'★'.repeat(n)}${'☆'.repeat(5 - n)}  ·  ${r.title}`],
      confirmLabel: 'Send rating',
      cancelLabel: 'Not yet',
    });
    if (!sure) return;
    await this.act(async () => {
      this.item.data.set(await this.api.rate(r.id, n));
    });
  }




  private async act(fn: () => Promise<void>): Promise<void> {
    this.busy.set(true);
    this.problem.set('');
    try {
      await fn();
    } catch (e) {
      this.problem.set(errorMessage(e));
    } finally {
      this.busy.set(false);
    }
  }
}
