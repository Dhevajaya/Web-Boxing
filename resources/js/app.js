import { route, start, navigate } from './router.js';
import { session, loadSession, onAuthChange, openLogin } from './auth.js';
import { homeView } from './views/home.js';
import { fighterView } from './views/fighter.js';
import { soonView } from './views/soon.js';

const view = document.getElementById('view');

route(/^\/$/, () => homeView(view), 'home');
route(/^\/profil\/?$/, () => {
  if (session.me) navigate(`/${session.me.username}`, { replace: true });
  else {
    soonView(view, 'profil');
    openLogin();
  }
}, 'profil');
route(/^\/(mading|latihan|markas|piala|dukung|privasi)\/?$/, (page) => soonView(view, page), (page) => page);
route(/^\/t\/([A-Za-z0-9_-]{6,16})\/?$/, () => soonView(view, 'tantangan'), null);
route(/^\/([A-Za-z0-9._]{1,30})\/?$/, (u) => fighterView(view, u.toLowerCase()),
  (u) => (session.me && session.me.username === u.toLowerCase() ? 'profil' : null));
route(/.*/, () => soonView(view, ''), null);

// Tombol kanan atas: "Masuk" atau "Profil".
const loginBtn = document.getElementById('loginBtn');
loginBtn.addEventListener('click', () => (session.me ? navigate(`/${session.me.username}`) : openLogin()));
onAuthChange((me) => {
  loginBtn.textContent = me ? 'Profil' : 'Masuk';
  loginBtn.classList.toggle('btn-accent', !!me);
  loginBtn.classList.toggle('btn-outline', !me);
});

// Tampilkan halaman setelah status masuk diketahui (cepat: dua request kecil).
loadSession().finally(start);
