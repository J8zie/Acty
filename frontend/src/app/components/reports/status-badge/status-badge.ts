
import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { ReportStatus, STATUS_LABELS, TASK_STATUS_LABELS, TaskStatus } from '../../../models';

@Component({
  selector: 'app-status',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './status-badge.html',
  styleUrl: './status-badge.css',
})
export class StatusBadge {

  readonly status = input<ReportStatus | TaskStatus>('open');


  protected readonly shape = computed(() => {
    switch (this.status()) {
      case 'in_progress':
      case 'accepted': return 'half';
      case 'resolved':
      case 'complete': return 'solid';
      case 'cancelled': return 'cross';
      default: return 'hollow';
    }
  });



  protected readonly text = computed(() => {
    const s = this.status();
    return (STATUS_LABELS as Record<string, string>)[s] ?? (TASK_STATUS_LABELS as Record<string, string>)[s] ?? s;
  });
}
