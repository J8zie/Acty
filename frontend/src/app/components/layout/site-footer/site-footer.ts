
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { AuthService } from '../../../api/auth.service';
import { LinkDirective, MagneticDirective } from '../../../directives';
import { MotionService } from '../../../services/motion.service';

@Component({
  selector: 'app-site-footer',

  imports: [LinkDirective, MagneticDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './site-footer.html',
  styleUrl: './site-footer.css',
})
export class SiteFooter {


  protected readonly auth = inject(AuthService);

  private readonly motion = inject(MotionService);





  protected readonly reportLink = computed(() => {
    switch (this.auth.role()) {
      case null: return '/report';
      case 'resident': return '/resident/reports/new';
      default: return null;
    }
  });


  top(): void {
    this.motion.scrollTo(0);
  }
}
