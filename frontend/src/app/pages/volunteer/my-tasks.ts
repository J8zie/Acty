
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FileDrop } from '../../components/media/file-drop/file-drop';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { PageHead } from '../../components/layout/page-head/page-head';
import { StatusBadge } from '../../components/reports/status-badge/status-badge';
import { FadeUpDirective, LinkDirective, MagneticDirective } from '../../directives';
import { errorMessage } from '../../api/http';
import { loader } from '../../lib/loader';
import { Report } from '../../models';
import { ConfirmService } from '../../services/confirm.service';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-my-tasks',
  imports: [PageHead, StatusBadge, FileDrop, LoadStateView, FadeUpDirective, LinkDirective, MagneticDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './my-tasks.html',
  styleUrl: './my-tasks.css',
})
export class MyTasks {

  private readonly api = inject(ReportsService);

  private readonly confirmer = inject(ConfirmService);



  protected readonly list = loader(() => this.api.list({ scope: 'tasks' }), []);


  protected readonly completing = signal<string | null>(null);

  protected readonly pending = signal<string | null>(null);

  protected readonly problem = signal('');
  protected readonly problemFor = signal('');

  protected readonly note = signal('');
  protected readonly photo = signal<File | null>(null);
  protected readonly video = signal<File | null>(null);

  protected readonly steps = [
    { key: 'accepted', label: 'Accepted' },
    { key: 'in_progress', label: 'Working' },
    { key: 'complete', label: 'Complete' },
  ] as const;



  protected readonly active = computed(() => this.list.data().filter((r) => r.taskStatus !== 'complete'));
  protected readonly done = computed(() => this.list.data().filter((r) => r.taskStatus === 'complete'));


  protected reached(r: Report, key: 'accepted' | 'in_progress' | 'complete'): boolean {
    const order = ['accepted', 'in_progress', 'complete'];
    return order.indexOf(r.taskStatus ?? 'accepted') >= order.indexOf(key);
  }


  protected openForm(id: string): void {
    this.note.set('');
    this.photo.set(null);
    this.video.set(null);
    this.completing.set(id);
  }



  protected start(r: Report): Promise<void> {
    return this.run(r, () => this.api.start(r.id));
  }





  protected async finish(r: Report): Promise<void> {
    const note = this.note().trim();
    if (note.length > 0 && note.length < 3) {
      this.problem.set('Write at least 3 characters, or leave the note blank.');
      this.problemFor.set(r.id);
      return;
    }
    if (note.length > 1000) {
      this.problem.set('The note is too long (1000 characters at most).');
      this.problemFor.set(r.id);
      return;
    }
    const sure = await this.confirmer.confirm({
      title: 'Mark this task as finished?',
      message: 'The request becomes resolved and the resident can rate your work. This cannot be undone.',
      details: [r.title, this.photo() ? 'With a photo of the result' : this.video() ? 'With a video of the result' : 'Without photo or video'],
      confirmLabel: 'Finish task',
      cancelLabel: 'Not yet',
    });
    if (!sure) return;
    await this.run(r, () => this.api.complete(r.id, note || 'Work completed.', this.photo(), this.video()));
    if (!this.problem()) this.completing.set(null);
  }



  private async run(r: Report, call: () => Promise<Report>): Promise<void> {
    if (this.pending()) return;
    this.pending.set(r.id);
    this.problem.set('');
    this.problemFor.set(r.id);
    try {
      const updated = await call();
      this.list.data.update((all) => all.map((x) => (x.id === r.id ? updated : x)));
    } catch (e) {
      this.problem.set(errorMessage(e));
    } finally {
      this.pending.set(null);
    }
  }
}
