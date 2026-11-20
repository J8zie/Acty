
import { AssistanceType, CaptureMethod, ReportStatus, Role, TaskStatus, Urgency } from '../models';

type Id = number | string;

export interface UserDto {
  id: Id;
  username: string;
  first_name: string;
  last_name: string;
  email: string;
  role: Role;
  created_at?: string;

  photo_url?: string | null;
}

export interface CategoryDto {
  id: Id;
  name: string;
}

export interface TaskDto {
  status: TaskStatus;
  volunteer?: { id: Id; first_name: string; last_name: string } | null;
  accepted_at?: string | null;
  completed_at?: string | null;
  outcome_note?: string | null;
  outcome_image_url?: string | null;
  outcome_video_url?: string | null;
  rating?: number | null;
}

export interface ReporterDto {
  first_name: string;
  last_name: string;
  phone: string;
  email?: string | null;
  barangay?: string | null;
  selfie_url?: string | null;
  liveness_prompt?: string | null;
  photo_captured_at?: string | null;
  selfie_captured_at?: string | null;
  photo_capture_method?: CaptureMethod | null;
  selfie_capture_method?: CaptureMethod | null;
}

export interface ReportDto {
  id: Id;
  title: string;
  description: string;

  category?: CategoryDto | string | null;
  category_id?: Id | null;
  assistance_type: AssistanceType;
  urgency: Urgency;
  status: ReportStatus;
  barangay?: string | null;

  latitude: number | string;
  longitude: number | string;
  image_url?: string | null;
  video_url?: string | null;
  created_at: string;
  user_id?: Id;

  user?: { id: Id; first_name: string; last_name: string } | null;

  source?: 'guest' | 'account';
  reporter?: ReporterDto | null;
  task?: TaskDto | null;
}

export interface GuestReceiptDto {
  reference?: string | null;
  message?: string | null;
}

export interface RankingDto {
  id: Id;
  first_name: string;
  last_name: string;
  barangay?: string | null;
  completed_count: number;
  average_rating: number | null;
  ratings_count: number;
}

export interface StatsDto {
  total: number;
  open: number;
  in_progress: number;
  resolved: number;
}

export interface AuthResponseDto {
  token: string;
  user: UserDto;
}

export type ListDto<T> = T[] | { data: T[] };
