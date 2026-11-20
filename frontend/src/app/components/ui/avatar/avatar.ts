
import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';
import { User } from '../../../models';

@Component({
  selector: 'app-avatar',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './avatar.html',
  styleUrl: './avatar.css',


  host: {
    '[style.width.px]': 'size()',
    '[style.height.px]': 'size()',
    '[style.font-size.px]': 'size() * 0.38',
  },
})
export class Avatar {



  readonly user = input.required<Pick<User, 'firstName' | 'lastName' | 'photoUrl'>>();

  readonly size = input(40);



  private readonly failedUrl = signal<string | null>(null);


  protected readonly src = computed(() => {
    const url = this.user().photoUrl;
    return url && url !== this.failedUrl() ? url : null;
  });

  protected readonly initials = computed(() => {
    const u = this.user();
    return ((u.firstName?.[0] ?? '') + (u.lastName?.[0] ?? '')).toUpperCase() || '?';
  });



  protected broken(): void {
    this.failedUrl.set(this.user().photoUrl);
  }
}
