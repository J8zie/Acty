
import { Location } from '@angular/common';
import { computed, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { errorMessage } from '../api/http';
import { LoadState } from './loader';

export type Filters = Record<string, string>;

export interface Page<T> {
  items: T[];

  total: number;
}

export interface ListQueryOptions<F extends Filters, T> {

  defaults: F;

  fetch: (filters: F, page: number, perPage: number) => Promise<Page<T>>;

  perPage?: number;

  urlKeys?: (keyof F & string)[];

  debounceMs?: number;

  typingKeys?: string[];
}

export function listQuery<F extends Filters, T>(options: ListQueryOptions<F, T>) {
  const location = inject(Location);
  const route = inject(ActivatedRoute);
  const perPage = options.perPage ?? 30;
  const debounceMs = options.debounceMs ?? 300;
  const typing = new Set(options.typingKeys ?? ['q']);
  const keys = (options.urlKeys ?? (Object.keys(options.defaults) as (keyof F & string)[])) as string[];


  const initial = { ...options.defaults } as Record<string, string>;
  for (const k of keys) {
    const v = route.snapshot.queryParamMap.get(k);
    if (v !== null) initial[k] = v;
  }


  const filters = signal(initial as F);

  const items = signal<T[]>([]);

  const total = signal(0);

  const state = signal<LoadState>('loading');
  const error = signal('');

  const busy = signal(false);
  let page = 1;
  let run = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;

  const hasMore = computed(() => items().length < total());

  const active = computed(() => Object.keys(options.defaults).some((k) => (filters()[k] ?? '') !== (options.defaults[k] ?? '')));


  const writeUrl = (): void => {
    const params = new URLSearchParams();
    for (const k of keys) {
      const v = filters()[k];
      if (v && v !== options.defaults[k]) params.set(k, v);
    }
    location.replaceState(location.path().split('?')[0], params.toString());
  };


  const fetchPage = async (append: boolean): Promise<void> => {
    const mine = ++run;
    busy.set(true);
    if (!append) page = 1;
    const wanted = append ? page + 1 : 1;
    try {
      const result = await options.fetch(filters(), wanted, perPage);
      if (mine !== run) return;
      page = wanted;
      items.set(append ? [...items(), ...result.items] : result.items);
      total.set(result.total);
      state.set('ready');
      error.set('');
    } catch (e) {
      if (mine !== run) return;
      error.set(errorMessage(e));

      if (!append || state() !== 'ready') state.set('error');
    } finally {
      if (mine === run) busy.set(false);
    }
  };

  const schedule = (immediate: boolean): void => {
    clearTimeout(timer);
    busy.set(true);
    timer = setTimeout(() => void fetchPage(false), immediate ? 0 : debounceMs);
  };

  void fetchPage(false);

  return {
    filters,
    items,
    total,
    state,
    error,
    busy,
    hasMore,
    active,

    setFilter(key: keyof F & string, value: string): void {
      if (filters()[key] === value) return;
      filters.update((f) => ({ ...f, [key]: value }));
      writeUrl();
      schedule(!typing.has(key));
    },

    reset(): void {
      filters.set({ ...options.defaults });
      writeUrl();
      schedule(true);
    },

    loadMore(): Promise<void> {
      return hasMore() && !busy() ? fetchPage(true) : Promise.resolve();
    },

    reload(): Promise<void> {
      clearTimeout(timer);
      state.set(items().length ? state() : 'loading');
      return fetchPage(false);
    },

    removeLocal(match: (item: T) => boolean): void {
      const before = items().length;
      items.update((all) => all.filter((x) => !match(x)));
      total.update((n) => Math.max(0, n - (before - items().length)));
    },
  };
}
