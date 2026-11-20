
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { CameraCapture } from '../../components/media/camera-capture/camera-capture';
import { FileDrop } from '../../components/media/file-drop/file-drop';
import { LatLng, MapView } from '../../components/map/map-view';
import { PageHead } from '../../components/layout/page-head/page-head';
import { FieldError } from '../../components/ui/field-error/field-error';
import { config } from '../../config';
import { BARANGAYS } from '../../data/barangays';
import { SELFIE_GESTURES } from '../../data/selfie-gestures';
import { LinkDirective, MagneticDirective } from '../../directives';
import { errorMessage, fieldErrors } from '../../api/http';
import { loader } from '../../lib/loader';
import { pickRandom } from '../../lib/live-photo';
import { emailAddress, personName, phoneNumber } from '../../lib/validators';
import { ASSISTANCE_LABELS, AssistanceType, URGENCY_LABELS, Urgency } from '../../models';
import { ConfirmService } from '../../services/confirm.service';
import { MotionService } from '../../services/motion.service';
import { LivePhoto, ReportsService } from '../../api/reports.service';

type Control = 'firstName' | 'lastName' | 'phone' | 'email' | 'homeBarangay' | 'title' | 'categoryId' | 'barangay' | 'description';

const STEP_CONTROLS: Control[][] = [
  ['firstName', 'lastName', 'phone', 'email', 'homeBarangay'],
  ['title', 'categoryId', 'barangay', 'description'],
  [],
];

const SERVER_STEP: Record<string, number> = {
  first_name: 0, last_name: 0, phone: 0, email: 0, reporter_barangay: 0,
  title: 1, description: 1, category_id: 1, barangay: 1, latitude: 1, longitude: 1,
  selfie: 0, liveness_prompt: 0,
  photo: 2, video: 2, consent: 2,
};

const API_CONTROL: Record<string, Control> = {
  first_name: 'firstName', last_name: 'lastName', phone: 'phone', email: 'email', reporter_barangay: 'homeBarangay',
  title: 'title', category_id: 'categoryId', barangay: 'barangay', description: 'description',
};

@Component({
  selector: 'app-guest-report',
  imports: [ReactiveFormsModule, PageHead, MapView, CameraCapture, FileDrop, MagneticDirective, LinkDirective, FieldError],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './guest-report.html',
  styleUrl: './guest-report.css',
})
export class GuestReport {


  private readonly api = inject(ReportsService);

  private readonly motion = inject(MotionService);


  private readonly fb = inject(NonNullableFormBuilder);

  private readonly confirmer = inject(ConfirmService);


  protected readonly server = signal<Partial<Record<Control, string>>>({});


  protected readonly steps = ['About you', 'The problem', 'Photo & send'];


  protected readonly step = signal(0);



  protected readonly categories = loader(() => this.api.categories(), []);



  protected readonly barangays = BARANGAYS;



  protected readonly position = signal<LatLng | null>(null);


  protected readonly problemShot = signal<LivePhoto | null>(null);

  protected readonly selfieShot = signal<LivePhoto | null>(null);

  protected readonly videoFile = signal<File | null>(null);




  protected readonly prompt = pickRandom(SELFIE_GESTURES);

  protected readonly tried = signal(false);

  protected readonly busy = signal(false);

  protected readonly geoError = signal('');


  protected readonly receipt = signal<{ reference: string; message: string } | null>(null);

  private readonly serverError = signal('');

  private readonly clientError = signal('');



  protected readonly assistance = (Object.keys(ASSISTANCE_LABELS) as AssistanceType[]).map((value) => ({ value, label: ASSISTANCE_LABELS[value] }));
  protected readonly urgencies = (Object.keys(URGENCY_LABELS) as Urgency[]).map((value) => ({ value, label: URGENCY_LABELS[value] }));









  protected readonly form = this.fb.group({

    firstName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(60), personName]],
    lastName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(60), personName]],
    phone: ['', [Validators.required, Validators.maxLength(30), phoneNumber]],
    email: ['', [Validators.maxLength(255), emailAddress]],
    homeBarangay: ['', Validators.required],
    title: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(120)]],
    categoryId: ['', Validators.required],
    assistanceType: this.fb.control<AssistanceType>('cleanup'),
    urgency: this.fb.control<Urgency>('medium'),
    barangay: ['', Validators.required],
    description: ['', [Validators.required, Validators.minLength(15), Validators.maxLength(2000)]],
    consent: [false],
  });




  protected readonly problemStamp = computed(() => {
    const p = this.position();
    return p ? `PROBLEM · ${p.lat}, ${p.lng}` : 'PROBLEM';
  });



  protected readonly problem = computed(() => this.serverError() || this.clientError());



  protected review(): { k: string; v: string }[] {
    const v = this.form.getRawValue();
    return [
      { k: 'Name', v: `${v.firstName} ${v.lastName}`.trim() },
      { k: 'Mobile', v: v.phone },
      { k: 'Problem', v: v.title },
      { k: 'Barangay', v: v.barangay },
    ];
  }



  protected bad(name: Control): 'true' | null {
    const c = this.form.controls[name];
    return (c.touched && c.invalid) || this.server()[name] ? 'true' : null;
  }



  protected back(): void {
    this.clearErrors();
    this.step.update((s) => Math.max(0, s - 1));
  }




  protected async next(): Promise<void> {

    if (this.busy()) return;
    this.clearErrors();
    const s = this.step();
    if (s < 2) {

      this.tried.set(true);
      const controls = STEP_CONTROLS[s].map((n) => this.form.controls[n]);
      controls.forEach((c) => c.markAsTouched());
      if (controls.some((c) => c.invalid)) {
        this.clientError.set('Please complete the highlighted fields.');
        return;
      }

      if (s === 0 && !this.selfieShot()) {
        this.clientError.set('Take your selfie to continue.');
        return;
      }
      if (s === 1 && !this.position()) {
        this.clientError.set('Please drop a pin on the map where the problem is.');
        return;
      }

      this.tried.set(false);
      this.step.set(s + 1);
      this.motion.scrollTo(0);
      return;
    }
    await this.submit();
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



  protected another(): void {
    this.form.reset({ assistanceType: 'cleanup', urgency: 'medium', consent: false });
    this.position.set(null);
    this.problemShot.set(null);
    this.selfieShot.set(null);
    this.step.set(0);
    this.receipt.set(null);
    this.motion.scrollTo(0);
  }


  private clearErrors(): void {
    this.clientError.set('');
    this.serverError.set('');
    this.server.set({});
  }





  private async submit(): Promise<void> {
    const pos = this.position();
    const photo = this.problemShot();
    const selfie = this.selfieShot();
    if (!selfie) {

      this.step.set(0);
      this.clientError.set('Take your selfie first.');
      return;
    }
    if (!photo) {
      this.clientError.set('Take a photo of the problem first.');
      return;
    }
    if (!this.form.controls.consent.value) {
      this.clientError.set('Please tick the box to confirm.');
      return;
    }
    if (this.form.invalid || !pos) {
      this.clientError.set('Some details are missing. Go back and check the earlier steps.');
      return;
    }
    const v = this.form.getRawValue();

    const sure = await this.confirmer.confirm({
      title: 'Send this report?',
      message: 'An admin will check your details and photos before it is shown. You can send only a few reports per day.',
      details: [v.title.trim(), `${v.barangay} · ${v.firstName.trim()} ${v.lastName.trim()} · ${v.phone.trim()}`],
      confirmLabel: 'Send report',
      cancelLabel: 'Keep editing',
    });
    if (!sure) return;
    this.busy.set(true);
    try {








      const receipt = await this.api.createGuest({
        firstName: v.firstName.trim(),
        lastName: v.lastName.trim(),
        phone: v.phone.trim(),
        email: v.email.trim(),
        homeBarangay: v.homeBarangay,
        title: v.title.trim(),
        description: v.description.trim(),
        categoryId: v.categoryId,
        assistanceType: v.assistanceType,
        urgency: v.urgency,
        barangay: v.barangay,
        latitude: pos.lat,
        longitude: pos.lng,
        problemPhoto: photo,
        selfie,
        video: this.videoFile(),
        livenessPrompt: this.prompt,
      });
      this.receipt.set(receipt);
      this.motion.scrollTo(0);
    } catch (e) {



      const errs = fieldErrors(e);
      const key = Object.keys(errs)[0];
      const mapped: Partial<Record<Control, string>> = {};
      for (const [k, msg] of Object.entries(errs)) {
        const c = API_CONTROL[k];
        if (c) mapped[c] = msg;
      }
      this.server.set(mapped);
      this.serverError.set(key ? errs[key] : errorMessage(e));
      if (key && key in SERVER_STEP) this.step.set(SERVER_STEP[key]);
    } finally {

      this.busy.set(false);
    }
  }
}
