
import { ReportStatus } from '../models';

export interface HomeAudience {

  who: string;

  text: string;
}

export interface HomeStep {

  n: string;
  title: string;

  status: ReportStatus;
  text: string;
}

export const HOME_AUDIENCES: readonly HomeAudience[] = [
  { who: 'Neighbours', text: 'Report a problem in minutes with no account: your details, a live photo of it, a selfie and a map pin.' },
  { who: 'Volunteers', text: 'Browse open requests near you, accept what you can handle and post proof when the work is done.' },
  { who: 'Admins', text: 'Check new reports and their photos before they go public, then keep statuses and accounts in order.' },
];

export const HOME_STEPS: readonly HomeStep[] = [
  { n: '01', title: 'Report', status: 'open', text: 'A neighbour pins the problem and sends a live photo of it plus a selfie. No account needed. An admin checks it before it goes public.' },
  { n: '02', title: 'Match', status: 'in_progress', text: 'A volunteer nearby sees the request and accepts it. The report moves to in progress.' },
  { n: '03', title: 'Resolve', status: 'resolved', text: 'The volunteer posts proof of the fix and the report closes. Reporters with an account can rate the help.' },
];
