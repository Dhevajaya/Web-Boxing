import { route, start, navigate } from './router.js';
import { session, loadSession, onAuthChange, openLogin } from './auth.js';
import { homeView } from './views/home.js';
import { fighterView } from './views/fighter.js';
import { soonView } from './views/soon.js';
import { editorView } from './views/editor.js';
import { arenaView } from './views/arena.js';
import { rankingView } from './views/ranking.js';
import { madingView } from './views/mading.js';
import { pialaView } from './views/piala.js';
import { latihanView } from './views/latihan.js';
import { markasView } from './views/markas.js';
import { gelanggangView } from './views/gelanggang.js';
import { dukungView } from './views/dukung.js';
import { privasiView } from './views/privasi.js';
import { sendChallenge, esc } from './ui.js';

// Modul yang dimatikan admin (Pengaturan → Fitur) tampil sebagai halaman 'nonaktif'.
const on = (f) => window.GAME?.features?.[f] !== false;
const gated = (f, fn) => (...a) => (on(f) ? fn(...a) : offView(f));
function offView() {
  view.innerHTML = '<section class="soon"><span class="eyebrow">Sedang istirahat</span><h1>Fitur nonaktif</h1><p>Fitur ini sedang dimatikan admin. Coba lagi nanti.</p></section>';
}

const view = document.getElementById('view');

route(/^\/$/, () => homeView(view), 'home');
route(/^\/profil\/?$/, () => {
  if (session.me) navigate(`/${session.me.username}`, { replace: true });
  else {
    soonView(view, 'profil');
    openLogin();
  }
}, 'profil');
route(/^\/profil\/(tampilan|identitas|stat|gaya|sasana)\/?$/, (section) => {
  if (session.me) return editorView(view, section);
  soonView(view, 'profil');
  openLogin();
  return null;
}, 'profil');
route(/^\/laga\/(\d+)\/?$/, (id) => arenaView(view, id), null);
route(/^\/gelanggang\/?$/, () => gelanggangView(view), 'gelanggang');
route(/^\/(?:ranking|papan-jagoan)\/?$/, () => rankingView(view, new URLSearchParams(location.search).get('papan') || 'season'), 'gelanggang');
route(/^\/(?:mading|papan-kabar)\/?$/, () => madingView(view), 'home');
route(/^\/piala\/?$/, gated('piala', () => pialaView(view)), 'gelanggang');
route(/^\/(?:dukung|saweran)\/?$/, gated('dukung', () => dukungView(view)), null);
route(/^\/privasi\/?$/, () => privasiView(view), null);
route(/^\/latihan\/?$/, gated('latihan', () => latihanView(view)), 'latihan');
route(/^\/(?:tongkrongan|markas)\/?$/, gated('markas', () => markasView(view, new URLSearchParams(location.search).get('ruang') || 'alun')), 'markas');
route(/^\/t\/([A-Za-z0-9_-]{6,16})\/?$/, (code) => arenaView(view, code, { title: 'Ajak Duel', extra: challengeExtra }), null);
route(/^\/([A-Za-z0-9._]{1,30})\/?$/, (u) => fighterView(view, u.toLowerCase()),
  (u) => (session.me && session.me.username === u.toLowerCase() ? 'profil' : null));
route(/.*/, () => soonView(view, ''), null);

// Di bawah arena tantangan: tantang balik / tantang lagi.
function challengeExtra(box, f) {
  const me = session.me?.username;
  const a = f.fighters.a?.username;
  const b = f.fighters.b?.username;
  const target = me === b ? a : me === a ? b : a;
  const label = me === b ? `Balas duel @${target}` : me === a ? `Ajak @${target} duel lagi` : `Ajak duel @${target}`;
  box.innerHTML = `<button class="btn btn-accent btn-block" type="button">⚑ ${esc(label)}</button><p class="m-status" hidden></p>`;
  box.querySelector('button').onclick = async (e) => {
    if (!session.me) return openLogin();
    const btn = e.currentTarget;
    btn.disabled = true;
    const err = await sendChallenge(target, navigate);
    if (err && box.isConnected) {
      const p = box.querySelector('p');
      p.hidden = false;
      p.textContent = err;
      btn.disabled = false;
    }
  };
}

// Sembunyikan tab untuk modul yang dimatikan.
if (!on('latihan')) document.querySelector('.tabbar [data-tab="latihan"]')?.remove();
if (!on('markas')) document.querySelector('.tabbar [data-tab="markas"]')?.remove();
document.querySelector('.tabbar').style.gridTemplateColumns = `repeat(${document.querySelectorAll('.tabbar a').length}, 1fr)`;

// Tombol kanan atas: "Masuk" atau "Profil".
const loginBtn = document.getElementById('loginBtn');
loginBtn.addEventListener('click', () => (session.me ? navigate(`/${session.me.username}`) : openLogin()));
onAuthChange((me) => {
  loginBtn.textContent = me ? 'Identitas' : 'Masuk';
  loginBtn.classList.toggle('btn-accent', !!me);
  loginBtn.classList.toggle('btn-outline', !me);
});

// Sesi habis saat menyimpan sesuatu: segarkan status masuk lalu tawarkan masuk lagi.
addEventListener('auth:expired', () => loadSession().then(() => { if (!session.me) openLogin(); }));

// Tampilkan halaman setelah status masuk diketahui (cepat: dua request kecil).
loadSession().finally(start);
