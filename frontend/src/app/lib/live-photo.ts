

const MAX_EDGE = 1280;
export const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

export function stampTime(d: Date): string {
  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
  })
    .format(d)
    .toUpperCase();
}

export function burnStamp(ctx: CanvasRenderingContext2D, w: number, h: number, text: string): void {

  const size = Math.max(13, Math.round(w * 0.027));
  const bar = Math.round(size * 2);
  ctx.fillStyle = 'rgba(0,0,0,0.62)';
  ctx.fillRect(0, h - bar, w, bar);
  ctx.fillStyle = '#fff';
  ctx.font = `600 ${size}px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`;
  ctx.textBaseline = 'middle';
  let line = text;
  while (ctx.measureText(line).width > w - size && line.length > 8) line = line.slice(0, -2);
  ctx.fillText(line === text ? line : line + '…', Math.round(size * 0.6), h - bar / 2);
}

export function toJpeg(canvas: HTMLCanvasElement, quality = 0.85): Promise<Blob | null> {
  return new Promise((res) => canvas.toBlob(res, 'image/jpeg', quality));
}

export function fit(w: number, h: number): { w: number; h: number } {
  const k = Math.min(1, MAX_EDGE / Math.max(w, h));
  return { w: Math.round(w * k), h: Math.round(h * k) };
}

export function isFresh(file: File, maxAgeMs = 3 * 60_000): boolean {
  if (!file.lastModified) return true;
  const age = Date.now() - file.lastModified;
  return age <= maxAgeMs && age >= -2 * 60_000;
}

export function cameraProblem(err: unknown): string {
  const name = (err as { name?: string } | null)?.name ?? '';
  switch (name) {
    case 'NotAllowedError':
    case 'SecurityError':
      return 'The camera is blocked. Allow camera access for this site in your browser settings (the camera icon in the address bar), then try again.';
    case 'NotFoundError':
    case 'OverconstrainedError':
      return 'No camera was found on this device.';
    case 'NotReadableError':
    case 'AbortError':
      return 'The camera is being used by another app. Close it and try again.';
    default:
      return 'The camera could not be started. Please try again.';
  }
}

export function pickRandom<T>(items: readonly T[]): T {
  const n = new Uint32Array(1);
  crypto.getRandomValues(n);
  return items[n[0] % items.length];
}
