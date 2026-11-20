
import { signal } from '@angular/core';
import { errorMessage } from '../api/http';

export type LoadState = 'loading' | 'ready' | 'error';

export function loader<T>(fetcher: () => Promise<T>, initial: T, auto = true) {



  const data = signal<T>(initial);

  const state = signal<LoadState>('loading');

  const error = signal('');

  let run = 0;



  const reload = async (): Promise<void> => {
    const mine = ++run;
    state.set('loading');
    error.set('');
    try {
      const result = await fetcher();

      if (mine !== run) return;
      data.set(result);
      state.set('ready');
    } catch (e) {
      if (mine !== run) return;

      error.set(errorMessage(e));
      state.set('error');
    }
  };


  if (auto) void reload();
  return { data, state, error, reload };
}
