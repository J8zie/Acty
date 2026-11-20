
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FilterOption, FilterSelect } from '../../components/ui/filter-select/filter-select';
import { ResultBar } from '../../components/ui/result-bar/result-bar';
import { SearchBox } from '../../components/ui/search-box/search-box';
import { ConfirmService } from '../../services/confirm.service';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { LatLng, MapView } from '../../components/map/map-view';
import { PageHead } from '../../components/layout/page-head/page-head';
import { config } from '../../config';
import { FadeUpDirective, LinkDirective, MagneticDirective, PreviewDirective } from '../../directives';
import { distanceKm } from '../../lib/geo';
import { errorMessage } from '../../api/http';
import { listQuery } from '../../lib/list-query';
import { loader } from '../../lib/loader';
import { ASSISTANCE_LABELS, Report, URGENCY_LABELS, Urgency } from '../../models';
import { PageTransitionService } from '../../services/page-transition.service';
import { reportImage } from '../../lib/report-image';
import { ReportsService } from '../../api/reports.service';

@Component({
  selector: 'app-nearby',
  imports: [PageHead, MapView, LoadStateView, FadeUpDirective, LinkDirective, MagneticDirective, PreviewDirective, SearchBox, FilterSelect, ResultBar],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './nearby.html',
  styleUrl: './nearby.css',
})
export class Nearby {

  private readonly api = inject(ReportsService);

  private readonly confirmer = inject(ConfirmService);

  private readonly transition = inject(PageTransitionService);





  protected readonly list = listQuery({
    defaults: { q: '', category: '', urgency: '' },
    perPage: 200,
    fetch: (f, page, perPage) =>
      this.api.listPage({ status: 'open', q: f.q, categoryId: f.category || undefined, urgency: (f.urgency || undefined) as Urgency | undefined, page, limit: perPage }),
  });

  private readonly categories = loader(() => this.api.categories(), []);
  protected readonly categoryOptions = computed<FilterOption[]>(() => this.categories.data().map((c) => ({ value: c.id, label: c.name })));
  protected readonly urgencyOptions: FilterOption[] = (Object.keys(URGENCY_LABELS) as Urgency[]).map((value) => ({ value, label: URGENCY_LABELS[value] }));



  protected readonly me = signal<LatLng>(config.mapCenter);

  protected readonly located = signal(false);

  protected readonly geoError = signal('');

  protected readonly problem = signal('');

  protected readonly radius = signal(0);

  protected readonly accepting = signal<string | null>(null);

  protected readonly justAccepted = signal<Report | null>(null);


  protected readonly urgency = URGENCY_LABELS;
  protected readonly assistance = ASSISTANCE_LABELS;

  protected readonly radii = [
    { km: 2, label: '2 km' },
    { km: 5, label: '5 km' },
    { km: 10, label: '10 km' },
    { km: 0, label: 'Any distance' },
  ];



  protected readonly rows = computed(() => {
    const me = this.me();
    const limit = this.radius();
    return this.list
      .items()
      .filter((r) => r.status === 'open')
      .map((report) => ({ report, km: distanceKm(me.lat, me.lng, report.latitude, report.longitude) }))
      .filter((x) => !limit || x.km <= limit)
      .sort((a, b) => a.km - b.km);
  });


  protected readonly mapReports = computed(() => this.rows().map((x) => x.report));


  protected image(r: Report): string {
    return reportImage(r);
  }


  protected pad(n: number): string {
    return String(n).padStart(3, '0');
  }



  protected locate(): void {
    this.geoError.set('');
    if (!navigator.geolocation) {
      this.geoError.set('Location is not available in this browser. Showing requests around Tagum City.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        this.me.set({ lat: pos.coords.latitude, lng: pos.coords.longitude });
        this.located.set(true);
      },
      () => this.geoError.set('Could not get your location. Showing requests around Tagum City.'),
      { enableHighAccuracy: true, timeout: 8000 },
    );
  }


  protected async accept(r: Report): Promise<void> {
    if (this.accepting()) return;

    const sure = await this.confirmer.confirm({
      title: 'Accept this request?',
      message: 'It will move to your tasks and other volunteers will no longer see it. Only accept it if you can really help.',
      details: [`#${this.pad(r.number)} · ${r.title}`, r.barangay],
      confirmLabel: 'Accept request',
    });
    if (!sure) return;
    this.accepting.set(r.id);
    this.problem.set('');
    try {



      await this.api.accept(r.id);
      this.justAccepted.set(r);

      this.list.removeLocal((x) => x.id === r.id);
    } catch (e) {
      this.problem.set(errorMessage(e));
    } finally {
      this.accepting.set(null);
    }
  }


  protected open(id: string): void {
    void this.transition.go('/reports/' + id, 'Report');
  }
}
