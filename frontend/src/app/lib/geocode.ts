import { BARANGAYS } from '../data/barangays';

const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

const norm = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '');

const KNOWN = new Map(BARANGAYS.map((b) => [norm(b), b]));

// Asks OpenStreetMap which barangay a map point is in.
export async function reverseBarangay(lat: number, lng: number): Promise<string | null> {
  try {
    const res = await fetch(`${ENDPOINT}?format=jsonv2&zoom=16&lat=${lat}&lon=${lng}`, {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) return null;

    const body = (await res.json()) as { address?: Record<string, string>; name?: string };
    const candidates = [
      ...(body.address ? Object.values(body.address) : []),
      body.name ?? '',
    ];

    for (const raw of candidates) {
      const candidate = raw.replace(/^(brgy\.?|barangay)\s*/i, '');
      const hit = KNOWN.get(norm(candidate));
      if (hit) return hit;
    }
  } catch {

  }
  return null;
}
