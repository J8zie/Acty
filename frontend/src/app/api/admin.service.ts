
import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ListDto, UserDto } from './api.types';
import { config } from '../config';
import { toUser, unwrap } from './mappers';
import { Role, User } from '../models';
import { Page } from '../lib/list-query';

// The admin's user management calls.
@Injectable({ providedIn: 'root' })
export class AdminService {

  private readonly http = inject(HttpClient);



  // Fetches every user at once (older callers).
  async users(): Promise<User[]> {
    const b = await firstValueFrom(this.http.get<ListDto<UserDto>>(`${config.apiUrl}/admin/users`));
    return unwrap(b).map(toUser);
  }



  // Fetches one page of users with search, role filter and sorting.
  async usersPage(query: { q?: string; role?: string; sort?: string; page?: number; limit?: number } = {}): Promise<Page<User>> {
    let params = new HttpParams();
    for (const [k, v] of Object.entries(query)) if (v !== undefined && String(v).trim() !== '') params = params.set(k, String(v).trim());
    const res = await firstValueFrom(this.http.get<ListDto<UserDto>>(`${config.apiUrl}/admin/users`, { params, observe: 'response' }));
    const items = unwrap(res.body ?? []).map(toUser);
    const header = Number(res.headers.get('X-Total-Count'));
    return { items, total: res.headers.has('X-Total-Count') && Number.isFinite(header) ? header : items.length };
  }



  // Changes a user's role.
  async setRole(id: string, role: Role): Promise<User> {
    const b = await firstValueFrom(this.http.patch<UserDto | { data: UserDto }>(`${config.apiUrl}/admin/users/${id}`, { role, confirm: true }));
    return toUser('data' in b ? b.data : b);
  }



  // Deletes a user account.
  async remove(id: string): Promise<void> {
    await firstValueFrom(this.http.delete(`${config.apiUrl}/admin/users/${id}`, { body: { confirm: true } }));
  }
}
