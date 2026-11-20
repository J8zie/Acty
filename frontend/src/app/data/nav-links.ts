
import { Role } from '../models';

export interface NavLink {

  to: string;

  label: string;

  exact?: boolean;
}

const INFO_LINKS: NavLink[] = [
  { to: '/#about', label: 'About' },
  { to: '/#how', label: 'How it works' },
];

export const NAV_LINKS: Record<Role | 'visitor', NavLink[]> = {

  visitor: [...INFO_LINKS, { to: '/reports', label: 'Reports' }, { to: '/rankings', label: 'Volunteers' }],
  resident: [
    { to: '/reports', label: 'Reports', exact: true },
    { to: '/resident/reports', label: 'My reports', exact: true },
    { to: '/resident/reports/new', label: 'New report' },
    { to: '/rankings', label: 'Volunteers' },
    ...INFO_LINKS,
  ],
  volunteer: [
    { to: '/volunteer/nearby', label: 'Nearby' },
    { to: '/volunteer/tasks', label: 'My tasks' },
    { to: '/reports', label: 'All reports', exact: true },
    { to: '/rankings', label: 'Rankings' },
    ...INFO_LINKS,
  ],
  admin: [
    { to: '/admin', label: 'Dashboard', exact: true },
    { to: '/admin/review', label: 'Review' },
    { to: '/admin/users', label: 'Users' },
    { to: '/reports', label: 'Reports', exact: true },
    { to: '/rankings', label: 'Rankings' },
    ...INFO_LINKS,
  ],
};
