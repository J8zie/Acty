
import { ChangeDetectionStrategy, Component, afterNextRender, computed, inject, signal } from '@angular/core';
import { HOME_AUDIENCES, HOME_STEPS } from '../../data/home-content';
import { StatusBadge } from '../../components/reports/status-badge/status-badge';
import { AuthService } from '../../api/auth.service';
import { MOTION } from '../../directives';
import { displayFontReady } from '../../lib/fonts';
import { loader } from '../../lib/loader';
import { PageTransitionService } from '../../services/page-transition.service';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-home',

  imports: [...MOTION, StatusBadge],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './home.html',
  styleUrl: './home.css',
})
export class Home {


  private readonly api = inject(ReportsService);

  private readonly auth = inject(AuthService);

  private readonly transition = inject(PageTransitionService);

  constructor() {


    afterNextRender(() => {
      const id = window.location.hash.slice(1);
      if (id !== 'about' && id !== 'how') return;

      void Promise.race([displayFontReady, new Promise((r) => setTimeout(r, 1500))]).then(() =>
        setTimeout(() => this.transition.scrollToAnchor(id, true), 150),
      );
    });
  }



  protected readonly heroFailed = signal(false);

  protected readonly volFailed = signal(false);





  private readonly counts = loader(() => this.api.stats(), { total: 0, open: 0, inProgress: 0, resolved: 0 });





  protected readonly stats = computed(() => {
    const ok = this.counts.state() === 'ready';
    const c = this.counts.data();
    return [
      { label: 'Reports filed', value: ok ? c.total : null },
      { label: 'Waiting for help', value: ok ? c.open : null },
      { label: 'In progress', value: ok ? c.inProgress : null },
      { label: 'Resolved', value: ok ? c.resolved : null },
    ];
  });



  protected readonly primary = computed(() => {
    switch (this.auth.role()) {
      case 'resident': return { to: '/resident/reports/new', label: 'Report a problem' };
      case 'volunteer': return { to: '/volunteer/nearby', label: 'Find requests near you' };
      case 'admin': return { to: '/admin', label: 'Open dashboard' };
      default: return { to: '/report', label: 'Report a problem' };
    }
  });


  protected readonly secondary = computed(() =>
    this.auth.isLoggedIn() ? { to: '/reports', label: 'Browse reports' } : { to: '/register', label: 'Become a volunteer' },
  );



  protected readonly roles = HOME_AUDIENCES;
  protected readonly steps = HOME_STEPS;
}
