
import { Routes } from '@angular/router';
import { guestGuard, roleGuard } from './guards/role-guards';

const SUFFIX = ' | Actify';

export const routes: Routes = [

  {
    path: '',
    title: 'Actify | Community reports, volunteer action',
    loadComponent: () => import('./pages/home/home').then((m) => m.Home),
  },



  { path: 'reports', title: 'Reports' + SUFFIX, loadComponent: () => import('./pages/reports/reports').then((m) => m.Reports) },

  { path: 'reports/:id', title: 'Report' + SUFFIX, loadComponent: () => import('./pages/reports/report-detail').then((m) => m.ReportDetail) },

  { path: 'rankings', title: 'Volunteer rankings' + SUFFIX, loadComponent: () => import('./pages/rankings/rankings').then((m) => m.Rankings) },



  { path: 'report', title: 'Report a problem' + SUFFIX, canActivate: [guestGuard], loadComponent: () => import('./pages/guest-report/guest-report').then((m) => m.GuestReport) },

  { path: 'login', title: 'Log in' + SUFFIX, canActivate: [guestGuard], loadComponent: () => import('./pages/auth/login').then((m) => m.Login) },

  { path: 'register', title: 'Join' + SUFFIX, canActivate: [guestGuard], loadComponent: () => import('./pages/auth/register').then((m) => m.Register) },



  {
    path: 'account',
    title: 'Your account' + SUFFIX,
    canActivate: [roleGuard('resident', 'volunteer', 'admin')],
    loadComponent: () => import('./pages/account/account').then((m) => m.Account),
  },



  {
    path: 'resident/reports',
    title: 'My reports' + SUFFIX,
    canActivate: [roleGuard('resident')],
    loadComponent: () => import('./pages/resident/my-reports').then((m) => m.MyReports),
  },

  {
    path: 'resident/reports/new',
    title: 'New report' + SUFFIX,
    canActivate: [roleGuard('resident')],
    loadComponent: () => import('./pages/resident/new-report').then((m) => m.NewReport),
  },



  {
    path: 'volunteer/nearby',
    title: 'Requests near you' + SUFFIX,
    canActivate: [roleGuard('volunteer')],
    loadComponent: () => import('./pages/volunteer/nearby').then((m) => m.Nearby),
  },

  {
    path: 'volunteer/tasks',
    title: 'My tasks' + SUFFIX,
    canActivate: [roleGuard('volunteer')],
    loadComponent: () => import('./pages/volunteer/my-tasks').then((m) => m.MyTasks),
  },



  {
    path: 'admin',
    title: 'Dashboard' + SUFFIX,
    canActivate: [roleGuard('admin')],
    loadComponent: () => import('./pages/admin/dashboard').then((m) => m.Dashboard),
  },

  {
    path: 'admin/review',
    title: 'Review' + SUFFIX,
    canActivate: [roleGuard('admin')],
    loadComponent: () => import('./pages/admin/review').then((m) => m.Review),
  },

  {
    path: 'admin/users',
    title: 'Users' + SUFFIX,
    canActivate: [roleGuard('admin')],
    loadComponent: () => import('./pages/admin/users').then((m) => m.Users),
  },


  { path: '**', title: 'Not found' + SUFFIX, loadComponent: () => import('./pages/not-found/not-found').then((m) => m.NotFound) },
];
