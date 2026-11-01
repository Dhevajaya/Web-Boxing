import { esc, api, STAT_NAMES } from '../ui.js';
import { baseFighter, ovr } from '../game/index.js';
import { session, logout, openLogin } from '../auth.js';

export function fighterView(el, username) {
  el.innerHTML = `<p class="loading">Mencari @${esc(username)}…</p>`;
  let alive = true;

  api(`/api/fighters/${encodeURIComponent(username)}`)
    .then(({ data }) => alive && draw(el, data))
    .catch((err) => {
      if (!alive) return;
      // Server belum siap (misalnya database belum dimigrasi): tetap tampilkan kartu dari hash.
      const b = baseFighter(username);
      draw(el, {
        username: b.username, registered: false, style: b.style, styleName: b.styleName, kelas: b.kelas,
        nick: `${b.nick} dari ${b.kota}`, stats: b.base, ovr: ovr(b.base),
        record: { w: 0, l: 0, d: 0, ko: 0, stars: 0 }, offline: err.status ?? true,
      });
    });

  return { cleanup: () => { alive = false; } };
}

function draw(el, f) {
  const mine = session.me?.username === f.username;
  const welcome = mine && new URLSearchParams(location.search).has('selamat');
  if (welcome) history.replaceState({}, '', location.pathname); // jangan tampil lagi saat dimuat ulang

  const stats = Object.entries(STAT_NAMES)
    .map(([k, label]) => `<div class="stat"><span>${label}</span><div class="bar"><i style="width:${f.stats[k]}%"></i></div><b>${f.stats[k]}</b></div>`)
    .join('');
  const brand = window.GAME?.brand ?? {};

  el.innerHTML = `
    <a href="/" data-link class="sub back">← Cari nama lain</a>
    ${welcome ? `<p class="banner">🥊 Selamat datang di ring, <b>@${esc(f.username)}</b>! Fight Night pertamamu dimulai saat fitur pertandingan rilis.</p>` : ''}
    <article class="fcard">
      <div class="fcard-head">
        <div>
          <div class="fcard-u">@${esc(f.username)}</div>
          <div class="fcard-nick">“${esc(f.nick)}”</div>
        </div>
        <div class="ovr"><b>${f.ovr}</b><small>NILAI</small></div>
      </div>
      <div class="fcard-body">
        <div class="pills">
          ${mine ? '<span class="pill on">Ini kamu</span>' : ''}
          ${f.npc ? '<span class="pill">NPC</span>' : ''}
          ${f.verified ? '<span class="pill on">✓ Terverifikasi</span>' : ''}
          <span class="pill">${esc(f.kelas)}</span>
          <span class="pill">${esc(f.styleName)}</span>
          ${f.registered ? '' : '<span class="pill">Belum daftar</span>'}
        </div>
        ${f.registered ? `<div class="record">
          <div><b>${f.record.w}</b><small>Menang</small></div>
          <div><b>${f.record.l}</b><small>Kalah</small></div>
          <div><b>${f.record.d}</b><small>Seri</small></div>
        </div>` : ''}
        <div class="stats">${stats}</div>
      </div>
    </article>
    ${mine ? `<section class="card">
      <h2>Akunmu</h2>
      <p class="sub">${f.verified ? 'Terverifikasi lewat Instagram.' : 'Masuk lewat mode manual/pengembang, belum terverifikasi Instagram.'} Atur tampilan dan poin stat hadir di Fase 3.</p>
      <div class="row">
        <button class="btn btn-ghost" id="logoutBtn" type="button">Keluar</button>
        <button class="btn btn-ghost" id="logoutAllBtn" type="button">Keluar semua perangkat</button>
      </div>
    </section>` : ''}
    ${!f.registered && !session.me ? `<section class="card">
      <h2>Ini akunmu?</h2>
      <p class="sub">Kartu ini dibuat otomatis dari username. Follow <b>@${esc(brand.instagram)}</b> lalu kirim DM <b>${esc(brand.keyword)}</b> untuk jadi petarung.</p>
      <button class="btn btn-accent btn-block" id="claimBtn" type="button">Masuk / daftar</button>
    </section>` : ''}
    ${f.offline ? '<p class="parity">Server belum terhubung; kartu dihitung di browser.</p>' : ''}
  `;

  el.querySelector('#logoutBtn')?.addEventListener('click', () => logout(false));
  el.querySelector('#logoutAllBtn')?.addEventListener('click', () => logout(true));
  el.querySelector('#claimBtn')?.addEventListener('click', () => openLogin());
}
