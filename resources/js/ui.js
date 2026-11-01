// Utilitas kecil untuk membangun HTML dengan aman.

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

export const $ = (sel, root = document) => root.querySelector(sel);

export const normalizeUsername = (u) => {
  const v = String(u ?? '').trim().replace(/^@+/, '').toLowerCase();
  return /^[a-z0-9._]{1,30}$/.test(v) ? v : null;
};

export async function api(path, opts = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const res = await fetch(path, {
    ...opts,
    headers: {
      Accept: 'application/json',
      ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      ...opts.headers,
    },
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw Object.assign(new Error(json?.error?.message || `HTTP ${res.status}`), { status: res.status });
  return json;
}

export const STAT_NAMES = {
  power: 'Pukulan',
  speed: 'Kecepatan',
  stamina: 'Stamina',
  defense: 'Pertahanan',
  chin: 'Daya tahan',
};
