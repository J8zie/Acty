
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { UserDto } from './api.types';
import { AuthService } from './auth.service';
import { config } from '../config';
import { toUser } from './mappers';
import { User } from '../models';

export interface ProfileInput {
  firstName: string;
  lastName: string;
  email: string;
}

type UserBody = UserDto | { data: UserDto };

// Changes the signed-in person's profile, photo and password.
@Injectable({ providedIn: 'root' })
export class AccountService {

  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);



  // Saves name and email changes, asking for the password if the email changed.
  async updateProfile(input: ProfileInput, currentPassword?: string): Promise<User> {
    return this.keep(
      await firstValueFrom(
        this.http.patch<UserBody>(`${config.apiUrl}/auth/me`, {
          first_name: input.firstName,
          last_name: input.lastName,
          email: input.email,

          ...(currentPassword ? { current_password: currentPassword } : {}),
        }),
      ),
    );
  }



  // Uploads a new profile picture.
  async setPhoto(file: File): Promise<User> {
    const form = new FormData();
    form.append('photo', file, file.name);
    return this.keep(await firstValueFrom(this.http.post<UserBody>(`${config.apiUrl}/auth/me/photo`, form)));
  }



  // Removes the profile picture.
  async removePhoto(): Promise<User> {
    return this.keep(await firstValueFrom(this.http.delete<UserBody>(`${config.apiUrl}/auth/me/photo`)));
  }


  // Changes the password.
  async changePassword(current: string, next: string): Promise<void> {
    await firstValueFrom(
      this.http.put(`${config.apiUrl}/auth/me/password`, { current_password: current, password: next, password_confirmation: next }),
    );
  }


  // Updates the remembered user after the server sends a fresh copy.
  private keep(res: UserBody): User {
    const user = toUser('data' in res ? res.data : res);
    this.auth.user.set(user);
    return user;
  }
}
