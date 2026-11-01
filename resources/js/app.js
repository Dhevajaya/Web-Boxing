import { route, start } from './router.js';
import { homeView } from './views/home.js';
import { fighterView } from './views/fighter.js';
import { soonView } from './views/soon.js';

const view = document.getElementById('view');

route(/^\/$/, () => homeView(view), 'home');
route(/^\/(mading|latihan|markas|piala|profil|dukung|privasi)\/?$/, (page) => soonView(view, page), (page) => page);
route(/^\/t\/([A-Za-z0-9_-]{6,16})\/?$/, () => soonView(view, 'tantangan'), null);
route(/^\/([A-Za-z0-9._]{1,30})\/?$/, (u) => fighterView(view, u.toLowerCase()), null);
route(/.*/, () => soonView(view, ''), null);

document.getElementById('loginBtn')?.addEventListener('click', () => {
  alert('Login lewat DM Instagram dibangun di Fase 2.');
});

start();
