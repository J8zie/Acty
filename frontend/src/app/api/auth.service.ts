
import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { firstValueFrom, timeout } from 'rxjs';
import { AuthResponseDto, UserDto } from './api.types';
import { config } from '../config';
import { toUser } from './mappers';
import { Role, User } from '../models';

const TOKEN_KEY = 'actify-token';

export interface RegisterInput {
  username: string;
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  role: Exclude<Role, 'admin'>;
}

// Remembers who is signed in and talks to the login endpoints.
@Injectable({ providedIn: 'root' })
export class AuthService {

  private readonly http = inject(HttpClient);




  // The signed-in person, or null when nobody is logged in.
  readonly user = signal<User | null>(null);


  // The signed-in person's role.
  readonly role = computed(() => this.user()?.role ?? null);

  // Whether somebody is signed in right now.
  readonly isLoggedIn = computed(() => this.user() !== null);


  // Reads the saved login token from the browser.
  token(): string | null {
    try {
      return localStorage.getItem(TOKEN_KEY);
    } catch {

      return null;
    }
  }





  // Checks the saved token still works when the app opens.
  async restore(): Promise<void> {

    if (!this.token()) return;
    try {

      const me = await firstValueFrom(this.http.get<UserDto | { data: UserDto }>(`${config.apiUrl}/auth/me`).pipe(timeout(6000)));

      this.user.set(toUser('data' in me ? me.data : me));
    } catch {

      this.user.set(null);
    }
  }




  // Sends the username and password and remembers the new session.
  async login(username: string, password: string): Promise<User> {
    const res = await firstValueFrom(this.http.post<AuthResponseDto>(`${config.apiUrl}/auth/login`, { username, password }));
    return this.start(res);
  }



  // Sends the sign-up details and remembers the new session.
  async register(input: RegisterInput): Promise<User> {
    const res = await firstValueFrom(
      this.http.post<AuthResponseDto>(`${config.apiUrl}/auth/register`, {
        username: input.username,
        first_name: input.firstName,
        last_name: input.lastName,
        email: input.email,
        password: input.password,
        role: input.role,
      }),
    );
    return this.start(res);
  }



  // Tells the server to forget the token, then clears the local session.
  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.http.post(`${config.apiUrl}/auth/logout`, {}));
    } catch {

    }
    this.clearSession();
  }


  // Forgets the signed-in person and removes the saved token.
  clearSession(): void {
    this.user.set(null);
    try {
      localStorage.removeItem(TOKEN_KEY);
    } catch {

    }
  }


  // Picks the first page a role should land on.
  homeFor(role: Role | null): string {
    switch (role) {
      case 'resident': return '/resident/reports';
      case 'volunteer': return '/volunteer/nearby';
      case 'admin': return '/admin';
      default: return '/';
    }
  }


  // Saves the token and user after a successful login or register.
  private start(res: AuthResponseDto): User {
    try {
      localStorage.setItem(TOKEN_KEY, res.token);
    } catch {

    }
    const user = toUser(res.user);
    this.user.set(user);
    return user;
  }
}
