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

// Tanggal/jam dalam zona game (WIB).
export const fmtDate = (iso, opts = {}) => new Date(iso).toLocaleString('id-ID', {
  day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: window.GAME?.brand?.timezone, ...opts,
});

export const RESULT = {
  win: ['M', 'Menang', 'win'],
  lose: ['K', 'Kalah', 'lose'],
  draw: ['S', 'Seri', 'draw'],
};

export const KIND = { night: 'Fight Night', challenge: 'Tantangan', cup: 'Piala' };

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
