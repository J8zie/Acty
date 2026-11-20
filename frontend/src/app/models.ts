

export type Role = 'resident' | 'volunteer' | 'admin';

export type ReportStatus = 'open' | 'in_progress' | 'resolved' | 'cancelled';

export type TaskStatus = 'accepted' | 'in_progress' | 'complete';

export type Urgency = 'low' | 'medium' | 'high';

export type AssistanceType = 'cleanup' | 'repair' | 'supplies' | 'manpower' | 'awareness' | 'other';

export interface User {
  id: string;
  username: string;
  firstName: string;
  lastName: string;
  email: string;
  role: Role;

  createdAt: string;

  photoUrl: string | null;
}

export interface Category {
  id: string;
  name: string;
}

export type CaptureMethod = 'live' | 'camera-app';

export interface GuestReporter {

  firstName: string;
  lastName: string;
  phone: string;
  email: string;
  barangay: string;

  selfieUrl: string | null;
  livenessPrompt: string;

  photoCapturedAt: string;
  selfieCapturedAt: string;
  photoMethod: CaptureMethod | '';
  selfieMethod: CaptureMethod | '';
}

export interface Report {
  id: string;

  number: number;

  userId: string;

  fullName: string;
  title: string;
  description: string;

  category: string;
  categoryId: string;
  assistanceType: AssistanceType;
  urgency: Urgency;
  status: ReportStatus;

  barangay: string;
  latitude: number;
  longitude: number;

  imageUrl: string | null;

  videoUrl: string | null;

  createdAt: string;

  source: 'guest' | 'account';

  reporter?: GuestReporter;

  volunteerId?: string;
  volunteerName?: string;
  taskStatus?: TaskStatus;
  acceptedAt?: string;
  completedAt?: string;

  rating?: number;

  outcomeNote?: string;
  outcomeImageUrl?: string;
}

export interface RankedVolunteer {
  id: string;
  name: string;

  completed: number;

  average: number;
  ratings: number;
  barangay: string;
}

export interface Stats {
  total: number;
  open: number;
  inProgress: number;
  resolved: number;
}

export const ASSISTANCE_LABELS: Record<AssistanceType, string> = {
  cleanup: 'Clean-up',
  repair: 'Repair',
  supplies: 'Supplies',
  manpower: 'Extra hands',
  awareness: 'Awareness',
  other: 'Other',
};

export const STATUS_LABELS: Record<ReportStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  resolved: 'Resolved',
  cancelled: 'Cancelled',
};

export const TASK_STATUS_LABELS: Record<TaskStatus, string> = {
  accepted: 'Accepted',
  in_progress: 'In progress',
  complete: 'Complete',
};

export const URGENCY_LABELS: Record<Urgency, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
};
