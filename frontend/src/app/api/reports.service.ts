
import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { CategoryDto, GuestReceiptDto, ListDto, RankingDto, ReportDto, StatsDto } from './api.types';
import { config } from '../config';
import { toCategory, toRanking, toReport, toStats, unwrap } from './mappers';
import { Page } from '../lib/list-query';
import { AssistanceType, CaptureMethod, Category, RankedVolunteer, Report, ReportStatus, Stats, Urgency } from '../models';

export interface NewReportInput {
  title: string;
  description: string;
  categoryId: string;
  assistanceType: AssistanceType;
  urgency: Urgency;
  barangay: string;
  latitude: number;
  longitude: number;

  photo?: File | null;
}

export interface LivePhoto {
  file: File;

  capturedAt: string;

  method: CaptureMethod;
}

export interface GuestReportInput {

  firstName: string;
  lastName: string;
  phone: string;
  email: string;
  homeBarangay: string;

  title: string;
  description: string;
  categoryId: string;
  assistanceType: AssistanceType;
  urgency: Urgency;
  barangay: string;
  latitude: number;
  longitude: number;

  problemPhoto: LivePhoto;
  selfie: LivePhoto;

  livenessPrompt: string;
}

export interface ReportQuery {

  status?: ReportStatus | ReportStatus[];

  review?: 'pending';

  scope?: 'mine' | 'tasks';

  limit?: number;

  q?: string;

  categoryId?: string;

  urgency?: Urgency | Urgency[];

  assistanceType?: AssistanceType | AssistanceType[];

  barangay?: string;

  from?: string;

  to?: string;

  sort?: 'newest' | 'oldest' | 'urgency' | 'status';

  page?: number;
}

const url = (path: string) => `${config.apiUrl}${path}`;

const one = (b: ReportDto | { data: ReportDto }) => toReport('data' in b ? b.data : b);

function toParams(query: ReportQuery): HttpParams {
  const put: [string, string | number | string[] | undefined][] = [
    ['status', query.status],
    ['scope', query.scope],
    ['review', query.review],
    ['limit', query.limit],
    ['q', query.q?.trim()],
    ['category_id', query.categoryId],
    ['urgency', query.urgency],
    ['assistance_type', query.assistanceType],
    ['barangay', query.barangay],
    ['from', query.from],
    ['to', query.to],
    ['sort', query.sort],
    ['page', query.page],
  ];
  let params = new HttpParams();
  for (const [key, value] of put) {
    const text = Array.isArray(value) ? value.join(',') : value === undefined ? '' : String(value);
    if (text !== '') params = params.set(key, text);
  }
  return params;
}

@Injectable({ providedIn: 'root' })
export class ReportsService {



  private readonly http = inject(HttpClient);


  private categoriesCache?: Promise<Category[]>;







  async list(query: ReportQuery = {}): Promise<Report[]> {
    const body = await firstValueFrom(this.http.get<ListDto<ReportDto>>(url('/reports'), { params: toParams(query) }));
    return unwrap(body).map(toReport);
  }






  async listPage(query: ReportQuery = {}): Promise<Page<Report>> {
    const res = await firstValueFrom(this.http.get<ListDto<ReportDto>>(url('/reports'), { params: toParams(query), observe: 'response' }));
    const items = unwrap(res.body ?? []).map(toReport);
    const header = Number(res.headers.get('X-Total-Count'));
    return { items, total: Number.isFinite(header) && res.headers.has('X-Total-Count') ? header : items.length };
  }





  async get(id: string): Promise<Report> {
    return one(await firstValueFrom(this.http.get<ReportDto | { data: ReportDto }>(url(`/reports/${id}`))));
  }






  async create(input: NewReportInput): Promise<Report> {

    const form = new FormData();
    form.set('title', input.title);
    form.set('description', input.description);
    form.set('category_id', input.categoryId);
    form.set('assistance_type', input.assistanceType);
    form.set('urgency', input.urgency);
    form.set('barangay', input.barangay);
    form.set('latitude', String(input.latitude));
    form.set('longitude', String(input.longitude));
    if (input.photo) form.set('photo', input.photo);
    return one(await firstValueFrom(this.http.post<ReportDto | { data: ReportDto }>(url('/reports'), form)));
  }










  async createGuest(input: GuestReportInput): Promise<{ reference: string; message: string }> {
    const form = new FormData();
    form.set('first_name', input.firstName);
    form.set('last_name', input.lastName);
    form.set('phone', input.phone);
    if (input.email) form.set('email', input.email);
    form.set('reporter_barangay', input.homeBarangay);
    form.set('title', input.title);
    form.set('description', input.description);
    form.set('category_id', input.categoryId);
    form.set('assistance_type', input.assistanceType);
    form.set('urgency', input.urgency);
    form.set('barangay', input.barangay);
    form.set('latitude', String(input.latitude));
    form.set('longitude', String(input.longitude));
    form.set('photo', input.problemPhoto.file);
    form.set('photo_captured_at', input.problemPhoto.capturedAt);
    form.set('photo_capture_method', input.problemPhoto.method);
    form.set('selfie', input.selfie.file);
    form.set('selfie_captured_at', input.selfie.capturedAt);
    form.set('selfie_capture_method', input.selfie.method);
    form.set('liveness_prompt', input.livenessPrompt);
    form.set('consent', '1');
    const body = await firstValueFrom(this.http.post<GuestReceiptDto | { data: GuestReceiptDto }>(url('/reports/guest'), form));
    const r = 'data' in body ? body.data : body;
    return { reference: r?.reference ?? '', message: r?.message ?? '' };
  }






  async review(id: string, decision: 'approve' | 'reject'): Promise<void> {
    await firstValueFrom(this.http.post(url(`/reports/${id}/review`), { decision, confirm: true }));
  }






  async accept(id: string): Promise<Report> {
    return one(await firstValueFrom(this.http.post<ReportDto | { data: ReportDto }>(url(`/reports/${id}/accept`), {})));
  }




  async start(id: string): Promise<Report> {
    return one(await firstValueFrom(this.http.post<ReportDto | { data: ReportDto }>(url(`/reports/${id}/start`), {})));
  }







  async complete(id: string, note: string, photo?: File | null): Promise<Report> {
    const form = new FormData();
    form.set('note', note);
    form.set('confirm', '1');
    if (photo) form.set('photo', photo);
    return one(await firstValueFrom(this.http.post<ReportDto | { data: ReportDto }>(url(`/reports/${id}/complete`), form)));
  }






  async rate(id: string, rating: number): Promise<Report> {
    return one(await firstValueFrom(this.http.post<ReportDto | { data: ReportDto }>(url(`/reports/${id}/rating`), { rating, confirm: true })));
  }





  async setStatus(id: string, status: ReportStatus): Promise<Report> {
    return one(await firstValueFrom(this.http.patch<ReportDto | { data: ReportDto }>(url(`/reports/${id}`), status === 'cancelled' ? { status, confirm: true } : { status })));
  }





  async remove(id: string): Promise<void> {
    await firstValueFrom(this.http.delete(url(`/reports/${id}`), { body: { confirm: true } }));
  }




  categories(): Promise<Category[]> {

    this.categoriesCache ??= firstValueFrom(this.http.get<ListDto<CategoryDto>>(url('/categories'))).then(
      (b) => unwrap(b).map(toCategory),
      (e) => {
        this.categoriesCache = undefined;
        throw e;
      },
    );
    return this.categoriesCache;
  }




  async stats(): Promise<Stats> {
    const b = await firstValueFrom(this.http.get<StatsDto | { data: StatsDto }>(url('/stats')));
    return toStats('data' in b ? b.data : b);
  }




  async rankings(): Promise<RankedVolunteer[]> {
    const b = await firstValueFrom(this.http.get<ListDto<RankingDto>>(url('/volunteers/rankings')));
    return unwrap(b).map(toRanking);
  }
}
