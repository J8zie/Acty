
import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';
import { config } from '../config';

// Adds the login token to API calls and logs the person out on a 401.
export const authInterceptor: HttpInterceptorFn = (req, next) => {


  if (!req.url.startsWith(config.apiUrl)) return next(req);
  const auth = inject(AuthService);
  const router = inject(Router);

  const token = auth.token();

  const authed = token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' } }) : req.clone({ setHeaders: { Accept: 'application/json' } });

  return next(authed).pipe(

    catchError((err: unknown) => {
      if (err instanceof HttpErrorResponse && (err.status === 404 || err.status === 0) && req.method === 'GET') {


        console.warn(`[Actify] ${req.method} ${req.url} -> ${err.status === 0 ? 'no answer' : '404'}. Is the Laravel API running and is this route defined? See API-CONTRACT.md.`);
      }

      const isAuthCall = req.url.includes('/auth/login') || req.url.includes('/auth/register');
      const isStartupCheck = req.method === 'GET' && req.url.endsWith('/auth/me');

      if (err instanceof HttpErrorResponse && err.status === 401 && token && !isAuthCall) {
        auth.clearSession();

        if (!isStartupCheck) void router.navigate(['/login'], { queryParams: { next: router.url } });
      }

      return throwError(() => err);
    }),
  );
};

// Turns any failed request into a sentence a person can read.
export function errorMessage(err: unknown): string {
  if (err instanceof HttpErrorResponse) {
    if (err.status === 0) return 'Cannot reach the server. Check your connection and try again.';
    if (err.status === 429) return 'Too many tries from this connection. Please wait a few minutes and try again.';

    const body = err.error as { message?: string; errors?: Record<string, string[]> } | null;
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    if (first) return first;
    if (body?.message) return body.message;
    if (err.status === 401) return 'Your session has expired. Please log in again.';
    if (err.status === 403) return 'You are not allowed to do that.';
    if (err.status === 404) return 'We could not find that.';
    if (err.status === 422) return 'Some of the information is not valid.';
    if (err.status >= 500) return 'The server had a problem. Please try again in a moment.';
  }
  return 'Something went wrong. Please try again.';
}

// Pulls the per-field error messages out of a 422 answer.
export function fieldErrors(err: unknown): Record<string, string> {
  const out: Record<string, string> = {};
  if (err instanceof HttpErrorResponse && err.status === 422) {
    const errors = (err.error as { errors?: Record<string, string[]> } | null)?.errors ?? {};

    for (const [k, v] of Object.entries(errors)) out[k] = v[0];
  }
  return out;
}
