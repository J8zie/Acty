
import {
  ListDto, RankingDto, ReportDto, ReporterDto, StatsDto, UserDto, CategoryDto,
} from './api.types';
import { Category, GuestReporter, RankedVolunteer, Report, Stats, User } from '../models';

// Pulls the list out of an answer that may be a plain array or wrapped in data.
export function unwrap<T>(body: ListDto<T>): T[] {
  return Array.isArray(body) ? body : (body?.data ?? []);
}

// Turns the server's user shape into the app's User shape.
export function toUser(d: UserDto): User {
  return {
    id: String(d.id),
    username: d.username,
    firstName: d.first_name,
    lastName: d.last_name,
    email: d.email,
    role: d.role,

    createdAt: d.created_at?.slice(0, 10) ?? '',

    photoUrl: d.photo_url ?? null,
  };
}

// Turns a category answer into the app's shape.
export function toCategory(d: CategoryDto): Category {
  return { id: String(d.id), name: d.name };
}

// Turns a guest reporter answer into the app's shape.
export function toReporter(d: ReporterDto): GuestReporter {
  return {
    firstName: d.first_name,
    lastName: d.last_name,
    phone: d.phone,
    email: d.email ?? '',
    barangay: d.barangay ?? '',
    selfieUrl: d.selfie_url ?? null,
    livenessPrompt: d.liveness_prompt ?? '',
    photoCapturedAt: d.photo_captured_at ?? '',
    selfieCapturedAt: d.selfie_captured_at ?? '',
    photoMethod: d.photo_capture_method ?? '',
    selfieMethod: d.selfie_capture_method ?? '',
  };
}

// Turns a report answer into the app's Report shape.
export function toReport(d: ReportDto): Report {

  const cat = d.category;
  const category = typeof cat === 'string' ? cat : (cat?.name ?? '');
  const categoryId = String(d.category_id ?? (typeof cat === 'object' && cat ? cat.id : ''));

  const task = d.task ?? undefined;
  const volunteer = task?.volunteer ?? undefined;
  const owner = d.user ?? undefined;
  const id = String(d.id);

  return {
    id,

    number: Number(d.id) || 0,
    userId: String(d.user_id ?? owner?.id ?? ''),

    fullName: owner ? `${owner.first_name} ${owner.last_name}` : d.source === 'guest' ? 'A neighbour (no account)' : '',

    source: d.source === 'guest' ? 'guest' : 'account',

    reporter: d.reporter ? toReporter(d.reporter) : undefined,
    title: d.title,
    description: d.description,
    category,
    categoryId,
    assistanceType: d.assistance_type,
    urgency: d.urgency,
    status: d.status,
    barangay: d.barangay ?? '',

    latitude: Number(d.latitude),
    longitude: Number(d.longitude),

    imageUrl: d.image_url ?? null,
    videoUrl: d.video_url ?? null,
    createdAt: d.created_at?.slice(0, 10) ?? '',

    volunteerId: volunteer ? String(volunteer.id) : undefined,
    volunteerName: volunteer ? `${volunteer.first_name} ${volunteer.last_name}` : undefined,
    taskStatus: task?.status,
    acceptedAt: task?.accepted_at?.slice(0, 10) ?? undefined,
    completedAt: task?.completed_at?.slice(0, 10) ?? undefined,
    rating: task?.rating ?? undefined,
    outcomeNote: task?.outcome_note ?? undefined,
    outcomeImageUrl: task?.outcome_image_url ?? undefined,
  };
}

// Turns a ranking row into the app's shape.
export function toRanking(d: RankingDto): RankedVolunteer {
  return {
    id: String(d.id),
    name: `${d.first_name} ${d.last_name}`,
    completed: d.completed_count,

    average: d.average_rating ?? 0,
    ratings: d.ratings_count,
    barangay: d.barangay ?? '',
  };
}

// Turns the stats answer into the app's shape.
export function toStats(d: StatsDto): Stats {
  return { total: d.total, open: d.open, inProgress: d.in_progress, resolved: d.resolved };
}
