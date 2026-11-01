import { $, esc, api } from '../ui.js';
import { avatar } from '../game/index.js';
import { session, openLogin } from '../auth.js';

const rp = (n) => `Rp${Number(n).toLocaleString('id-ID')}`;
const PERKS = {
  tribun: ['Lencana ♥ Pendukung di kartu', 'Tanda ♥ di ranking dan chat', 'Sarung tinju & celana Neon'],
  ringside: ['Semua keuntungan Tribun', 'Warna rambut Emas'],
  vip: ['Semua keuntungan Ringside', 'Namamu di urutan teratas daftar pendukung'],
};

export function dukungView(el) {
  let alive = true;
  document.title = `Dukung · ${window.GAME?.brand?.name ?? ''}`;
  el.innerHTML = '<p class="loading">Memuat…</p>';

  const load = () => api('/api/dukung', { cache: 'no-cache' }).then(({ data }) => alive && draw(data)).catch((e) => {
    if (alive) el.innerHTML = `<p class="banner bad">${esc(e.message)}</p>`;
  });

  function draw(d) {
    const me = d.me;
    el.innerHTML = `
      <section class="hero"><span class="eyebrow">Server, hosting, dan kopi admin</span><h1>Du<em>kung</em></h1>
        <p>Game ini gratis. Dukunganmu membayar server agar Fight Night tetap jalan tiap 6 jam.</p></section>
      <section class="card">
        <h2>Cara dukung</h2>
        <ol class="steps">
          <li>Buka halaman donasi di bawah.</li>
          <li>Tulis <b>@${esc(session.me?.username ?? 'usernamemu')}</b> di pesan donasi.</li>
          <li>Status pendukung aktif otomatis dalam beberapa menit. Total donasi dijumlahkan dan tier tidak pernah turun.</li>
        </ol>
        ${d.url ? `<a class="btn btn-accent btn-block" href="${esc(d.url)}" target="_blank" rel="noopener">♥ Dukung lewat Saweria</a>` : '<p class="note">Tautan donasi belum disetel admin.</p>'}
        ${me ? `<p class="sub">Statusmu: <b>${me.tier ? esc(me.tier) : 'belum pendukung'}</b>${me.total ? ` · total ${rp(me.total)}` : ''}.</p>
          ${me.tier ? `<label class="lbl inline-check"><input type="checkbox" id="anon" ${me.anonymous ? 'checked' : ''}> Sembunyikan namaku dari daftar pendukung dan tanda ♥</label>` : ''}`
          : '<button class="btn btn-ghost btn-block" id="login" type="button">Masuk untuk melihat statusmu</button>'}
      </section>
      <div class="tiers">${d.tiers.map((t) => `
        <section class="card tier-card${me?.tier === t.id ? ' mine' : ''}">
          <div class="tier-h"><b>${esc(t.name)}</b><span>mulai ${rp(t.min)}</span></div>
          <ul>${(PERKS[t.id] ?? []).map((p) => `<li>${esc(p)}</li>`).join('')}</ul>
        </section>`).join('')}</div>
      <section class="card">
        <h2>Terima kasih ♥</h2>
        ${d.supporters.length ? `<div class="sup-list">${d.supporters.map((s) => `<a class="sup-item" href="/${esc(s.username)}" data-link title="${esc(s.tier)}">
          <span class="mini-av">${avatar(s.look, { size: 32 })}</span><span>@${esc(s.username)}</span><small>${esc(s.tier)}</small></a>`).join('')}</div>`
          : '<p class="sub">Jadilah pendukung pertama!</p>'}
      </section>`;
    $('#login', el)?.addEventListener('click', () => openLogin());
    $('#anon', el)?.addEventListener('change', async (e) => {
      const box = e.currentTarget;
      box.disabled = true;
      try { await api('/api/me/supporter', { method: 'PUT', body: JSON.stringify({ anonymous: box.checked }) }); load(); } catch { box.disabled = false; }
    });
  }

  load();
  return { cleanup: () => { alive = false; } };
}
