
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../api/auth.service';
import { Role } from '../models';

export const roleGuard =
  (...roles: Role[]): CanActivateFn =>
  (_route, state) => {

    const auth = inject(AuthService);
    const router = inject(Router);
    const role = auth.role();

    if (!role) return router.createUrlTree(['/login'], { queryParams: { next: state.url } });

    if (!roles.includes(role)) return router.parseUrl(auth.homeFor(role));
    return true;
  };

export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return auth.isLoggedIn() ? router.parseUrl(auth.homeFor(auth.role())) : true;
};
