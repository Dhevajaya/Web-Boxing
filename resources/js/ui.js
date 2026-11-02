import rulesV2 from '../game/rules.v2.json';

// Utilitas kecil untuk membangun HTML dengan aman.

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

export const $ = (sel, root = document) => root.querySelector(sel);

export const normalizeUsername = (u) => {
  const v = String(u ?? '').trim().replace(/^@+/, '').toLowerCase();
  return /^[a-z0-9._]{1,30}$/.test(v) ? v : null;
};

export const setCsrf = (token) => {
  const m = document.querySelector('meta[name="csrf-token"]');
  if (m && token) m.content = token;
};

const ERRORS = {
  401: 'Masuk dulu ya.',
  419: 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
  429: 'Terlalu sering. Tunggu sebentar lalu coba lagi.',
  500: 'Server sedang bermasalah. Coba lagi sebentar lagi.',
  503: 'Sedang perawatan. Coba lagi sebentar lagi.',
};

export async function api(path, opts = {}, retried = false) {
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
  // 419 = token CSRF di halaman sudah usang (sesi diganti setelah keluar/masuk, atau sesi
  // di server hilang). Ambil token baru lalu ulangi sekali.
  if (res.status === 419 && !retried) {
    try {
      const fresh = await fetch('/api/csrf', { headers: { Accept: 'application/json' }, cache: 'no-store' }).then((r) => r.json());
      setCsrf(fresh?.data?.token);
      return api(path, opts, true);
    } catch { /* jatuh ke pesan galat di bawah */ }
  }
  const json = await res.json().catch(() => ({}));
  if (json?.data?.csrf) setCsrf(json.data.csrf);
  // Sesi habis di tengah jalan (bukan sekadar belum masuk): beri tahu aplikasi agar membuka jendela Masuk.
  if (res.status === 401 && opts.method && opts.method !== 'GET') dispatchEvent(new CustomEvent('auth:expired'));
  if (!res.ok) throw Object.assign(new Error(json?.error?.message || ERRORS[res.status] || `Gagal (HTTP ${res.status})`), { status: res.status });
  return json;
}

// Nama atribut (10 atribut KAMPUNG TENDANG) + nama lama untuk arsip tinju.
export const STAT_NAMES = { ...rulesV2.statNames };
export const STAT_HELP = { ...rulesV2.statHelp };
export const LEGACY_STAT_NAMES = { power: 'Pukulan', chin: 'Dagu', speed: 'Kecepatan', stamina: 'Stamina', defense: 'Pertahanan' };

// Tanggal/jam dalam zona game (WIB).
export const fmtDate = (iso, opts = {}) => new Date(iso).toLocaleString('id-ID', {
  day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: window.GAME?.brand?.timezone, ...opts,
});

export const RESULT = {
  win: ['M', 'Menang', 'win'],
  lose: ['K', 'Kalah', 'lose'],
  draw: ['S', 'Seri', 'draw'],
};

export const KIND = { night: 'Malam Gelanggang', challenge: 'Ajak Duel', cup: 'Piala Kampung' };

// Hitung mundur "01:23:45" sampai iso; panggil fn tiap detik. Kembalikan fungsi berhenti.
export function countdown(iso, fn) {
  const target = new Date(iso).getTime();
  const tick = () => {
    const s = Math.max(0, Math.round((target - Date.now()) / 1000));
    const p = (n) => String(n).padStart(2, '0');
    fn(`${p(Math.floor(s / 3600))}:${p(Math.floor((s % 3600) / 60))}:${p(s % 60)}`, s);
  };
  tick();
  const t = setInterval(tick, 1000);
  return () => clearInterval(t);
}

// Label lencana dari kodenya (lihat App\Models\Badge).
export function badgeLabel(code, meta = {}) {
  const [kind, arg] = String(code).split(':');
  switch (kind) {
    case 'juara': return ['🏆', meta?.rank === 1 ? `Juara Season ${meta?.season ?? arg.slice(1)}` : `Top ${meta?.rank ?? ''} Season ${meta?.season ?? arg.slice(1)}`];
    case 'perintis': return ['🎖️', 'Perintis Season 0'];
    case 'veteran': return ['🎗️', 'Veteran Gelanggang'];
    case 'piala': return ['🥇', `Juara Piala ${new Date(`${arg}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}`];
    case 'pendukung': return ['♥', `Pendukung ${arg ? arg[0].toUpperCase() + arg.slice(1) : ''}`];
    default: return ['⭐', code];
  }
}

// Kirim tantangan ke @username lalu buka arenanya. Mengembalikan pesan galat atau null.
export async function sendChallenge(username, navigate) {
  try {
    const { data } = await api('/api/challenges', { method: 'POST', body: JSON.stringify({ username }) });
    navigate(`/t/${data.code}`);
    return null;
  } catch (e) {
    return e.message;
  }
}
