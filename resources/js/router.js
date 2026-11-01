// Router path sederhana untuk shell SPA. Server selalu mengirim view yang sama
// (routes/web.php), lalu router ini memilih tampilan berdasarkan location.pathname.

const routes = [];
let current = null;

export function route(pattern, handler, tab = null) {
  routes.push({ pattern, handler, tab });
}

export function navigate(path, { replace = false } = {}) {
  if (path === location.pathname) return render();
  history[replace ? 'replaceState' : 'pushState']({}, '', path);
  render();
}

export function render() {
  const path = location.pathname;
  for (const r of routes) {
    const m = path.match(r.pattern);
    if (m) {
      current?.cleanup?.();
      const args = m.slice(1).map(decodeURIComponent);
      current = r.handler(...args) || null;
      const tab = typeof r.tab === 'function' ? r.tab(...args) : r.tab;
      document.querySelectorAll('.tabbar a').forEach((a) => {
        if (a.dataset.tab === tab) a.setAttribute('aria-current', 'page');
        else a.removeAttribute('aria-current');
      });
      window.scrollTo(0, 0);
      return;
    }
  }
}

export function start() {
  // Link internal ditandai data-link: pindah halaman tanpa memuat ulang.
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-link]');
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
    e.preventDefault();
    navigate(a.getAttribute('href'));
  });
  addEventListener('popstate', render);
  render();
}
