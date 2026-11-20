
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { LoadStateView } from '../../components/ui/load-state/load-state';
import { PageHead } from '../../components/layout/page-head/page-head';
import { FilterOption, FilterSelect } from '../../components/ui/filter-select/filter-select';
import { ResultBar } from '../../components/ui/result-bar/result-bar';
import { SearchBox } from '../../components/ui/search-box/search-box';
import { AdminService } from '../../api/admin.service';
import { AuthService } from '../../api/auth.service';
import { LinkDirective, MagneticDirective } from '../../directives';
import { errorMessage } from '../../api/http';
import { listQuery } from '../../lib/list-query';
import { Role, User } from '../../models';
import { ConfirmService } from '../../services/confirm.service';

@Component({
  selector: 'app-users',
  imports: [PageHead, LoadStateView, LinkDirective, MagneticDirective, SearchBox, FilterSelect, ResultBar],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './users.html',
  styleUrl: './users.css',
})
export class Users {

  private readonly admin = inject(AdminService);

  private readonly auth = inject(AuthService);

  private readonly confirmer = inject(ConfirmService);



  protected readonly list = listQuery({
    defaults: { q: '', role: '', sort: '' },
    perPage: 30,
    fetch: (f, page, perPage) => this.admin.usersPage({ q: f.q, role: f.role, sort: f.sort === 'newest' ? '' : f.sort, page, limit: perPage }),
  });

  protected readonly me = () => this.auth.user()?.id;

  protected readonly roles: Role[] = ['resident', 'volunteer', 'admin'];
  protected readonly roleOptions: FilterOption[] = this.roles.map((value) => ({ value, label: value[0].toUpperCase() + value.slice(1) }));
  protected readonly sortOptions: FilterOption[] = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name', label: 'Name A–Z' },
    { value: 'role', label: 'By role' },
  ];

  protected readonly pending = signal<string | null>(null);

  protected readonly problem = signal('');
  protected readonly remaining = computed(() => Math.max(0, this.list.total() - this.list.items().length));



  protected async setRole(u: User, event: Event): Promise<void> {
    const select = event.target as HTMLSelectElement;
    const role = select.value as Role;
    if (role === u.role) return;
    const sure = await this.confirmer.confirm({
      title: `Change ${u.firstName}’s role to ${role}?`,
      message:
        role === 'admin'
          ? 'An admin can review reports, change any status and remove reports and people.'
          : u.role === 'volunteer'
            ? 'Tasks they have not finished go back to the open list for other volunteers.'
            : `They will use Actify as a ${role} from their next action.`,
      details: [`${u.firstName} ${u.lastName} · ${u.username}`, `${u.role} → ${role}`],
      confirmLabel: 'Change role',
      cancelLabel: 'Keep as it is',
      tone: role === 'admin' ? 'danger' : 'normal',
    });
    if (!sure) {
      select.value = u.role;
      return;
    }
    this.pending.set(u.id);
    this.problem.set('');
    try {


      const updated = await this.admin.setRole(u.id, role);
      this.list.items.update((all) => all.map((x) => (x.id === u.id ? updated : x)));
    } catch (e) {

      select.value = u.role;
      this.problem.set(errorMessage(e));
    } finally {
      this.pending.set(null);
    }
  }


  protected async remove(u: User): Promise<void> {
    const sure = await this.confirmer.confirm({
      title: `Remove ${u.firstName} ${u.lastName}?`,
      message:
        'The account is deleted and they are signed out everywhere. Reports they filed stay on the site, shown as “A neighbour”. Tasks they have not finished go back to the open list.',
      details: [`${u.username} · ${u.email}`, `Role: ${u.role}`],
      confirmLabel: 'Remove account',
      tone: 'danger',
    });
    if (!sure) return;
    this.pending.set(u.id);
    this.problem.set('');
    try {


      await this.admin.remove(u.id);
      this.list.removeLocal((x) => x.id === u.id);
    } catch (e) {
      this.problem.set(errorMessage(e));
    } finally {
      this.pending.set(null);
    }
  }
}
