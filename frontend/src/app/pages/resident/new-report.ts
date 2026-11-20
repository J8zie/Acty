
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { FileDrop } from '../../components/media/file-drop/file-drop';
import { LatLng, MapView } from '../../components/map/map-view';
import { PageHead } from '../../components/layout/page-head/page-head';
import { FieldError } from '../../components/ui/field-error/field-error';
import { config } from '../../config';
import { BARANGAYS } from '../../data/barangays';
import { MagneticDirective } from '../../directives';
import { errorMessage, fieldErrors } from '../../api/http';
import { loader } from '../../lib/loader';
import { ASSISTANCE_LABELS, AssistanceType, URGENCY_LABELS, Urgency } from '../../models';
import { PageTransitionService } from '../../services/page-transition.service';
import { ReportsService } from '../../api/reports.service';

type Field = 'title' | 'categoryId' | 'barangay' | 'description';

const API_FIELD: Record<string, Field> = { title: 'title', category_id: 'categoryId', barangay: 'barangay', description: 'description' };

@Component({
  selector: 'app-new-report',
  imports: [ReactiveFormsModule, PageHead, MapView, FileDrop, MagneticDirective, FieldError],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './new-report.html',
  styleUrl: './new-report.css',
})
export class NewReport {

  private readonly api = inject(ReportsService);

  private readonly transition = inject(PageTransitionService);

  private readonly fb = inject(NonNullableFormBuilder);



  protected readonly categories = loader(() => this.api.categories(), []);


  protected readonly barangays = BARANGAYS;



  protected readonly position = signal<LatLng | null>(null);

  protected readonly photo = signal<File | null>(null);


  protected readonly submitted = signal(false);

  protected readonly busy = signal(false);

  protected readonly geoError = signal('');

  private readonly serverError = signal('');

  protected readonly server = signal<Partial<Record<Field, string>>>({});


  protected readonly assistance = (Object.keys(ASSISTANCE_LABELS) as AssistanceType[]).map((value) => ({ value, label: ASSISTANCE_LABELS[value] }));
  protected readonly urgencies = (Object.keys(URGENCY_LABELS) as Urgency[]).map((value) => ({ value, label: URGENCY_LABELS[value] }));



  protected readonly form = this.fb.group({

    title: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(120)]],
    categoryId: ['', Validators.required],
    assistanceType: this.fb.control<AssistanceType>('cleanup'),
    urgency: this.fb.control<Urgency>('medium'),
    barangay: ['', Validators.required],
    description: ['', [Validators.required, Validators.minLength(15), Validators.maxLength(2000)]],
  });



  private readonly formStatus = toSignal(this.form.statusChanges, { initialValue: this.form.status });



  protected readonly problem = computed(() => {
    if (this.serverError()) return this.serverError();
    if (!this.submitted()) return '';
    const fields = this.formStatus() !== 'VALID';
    const pin = !this.position();
    if (fields && pin) return 'Please complete the highlighted fields and drop a pin on the map.';
    if (fields) return 'Please complete the highlighted fields.';
    return pin ? 'Please drop a pin on the map.' : '';
  });


  protected bad(name: Field): 'true' | null {
    const c = this.form.controls[name];
    return (c.touched && c.invalid) || this.server()[name] ? 'true' : null;
  }



  protected locate(): void {
    this.geoError.set('');
    if (!navigator.geolocation) {
      this.geoError.set('Location is not available in this browser. Tap the map instead.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => this.position.set({ lat: +pos.coords.latitude.toFixed(6), lng: +pos.coords.longitude.toFixed(6) }),
      () => this.geoError.set('Could not get your location. Tap the map instead.'),
      { enableHighAccuracy: true, timeout: 8000 },
    );
  }



  protected async submit(): Promise<void> {
    if (this.busy()) return;
    this.submitted.set(true);
    this.serverError.set('');
    this.server.set({});
    const pos = this.position();
    if (this.form.invalid || !pos) {
      this.form.markAllAsTouched();
      return;
    }
    const v = this.form.getRawValue();
    this.busy.set(true);
    try {



      const report = await this.api.create({
        ...v,
        title: v.title.trim(),
        description: v.description.trim(),
        latitude: pos.lat,
        longitude: pos.lng,
        photo: this.photo(),
      });
      void this.transition.go('/reports/' + report.id, 'Report');
    } catch (e) {


      const errors = fieldErrors(e);
      const mapped: Partial<Record<Field, string>> = {};
      for (const [k, msg] of Object.entries(errors)) {
        const f = API_FIELD[k];
        if (f) mapped[f] = msg;
      }
      this.server.set(mapped);

      const other = Object.entries(errors).find(([k]) => !API_FIELD[k])?.[1];
      this.serverError.set(other ?? (Object.keys(mapped).length ? 'Please fix the highlighted fields.' : errorMessage(e)));
    } finally {
      this.busy.set(false);
    }
  }
}
