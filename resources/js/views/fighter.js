import { esc, api, STAT_NAMES } from '../ui.js';
import { baseFighter, ovr, lookFor, avatar } from '../game/index.js';
import { session, logout, openLogin } from '../auth.js';

export function fighterView(el, username) {
  el.innerHTML = `<p class="loading">Mencari @${esc(username)}…</p>`;
  document.title = `@${username} · ${window.GAME?.brand?.name ?? ''}`;
  let alive = true;
  const mine = session.me?.username === username;

  // Kartu sendiri selalu divalidasi ulang agar perubahan dari editor langsung terlihat.
  api(`/api/fighters/${encodeURIComponent(username)}`, mine ? { cache: 'no-cache' } : {})
    .then(({ data }) => alive && draw(el, data))
    .catch((err) => {
      if (!alive) return;
      // Server belum siap (misalnya database belum dimigrasi): tetap tampilkan kartu dari hash.
      const b = baseFighter(username);
      draw(el, {
        username: b.username, registered: false, style: b.style, styleName: b.styleName, kelas: b.kelas,
        nick: `${b.nick} dari ${b.kota}`, stats: b.base, ovr: ovr(b.base), look: lookFor(b.username),
        record: { w: 0, l: 0, d: 0, ko: 0, stars: 0 }, offline: err.status ?? true,
      });
    });

  return { cleanup: () => { alive = false; } };
}

function levelBlock(f) {
  if (!f.registered || !f.level) return '';
  const { lv, into, need } = f.level;
  const pct = need ? Math.round((into / need) * 100) : 100;
  return `<div class="lvrow">
    <span class="lv">LV <b>${lv}</b></span>
    <div class="xpbar" role="progressbar" aria-label="XP ke level berikutnya" aria-valuemin="0" aria-valuemax="${need || 1}" aria-valuenow="${need ? into : 1}"><i style="width:${pct}%"></i></div>
    <span class="xp">${need ? `${into}/${need} XP` : 'MAKS'}</span>
  </div>`;
}

function draw(el, f) {
  const mine = session.me?.username === f.username;
  const welcome = mine && new URLSearchParams(location.search).has('selamat');
  if (welcome) history.replaceState({}, '', location.pathname); // jangan tampil lagi saat dimuat ulang

  const stats = Object.entries(STAT_NAMES)
    .map(([k, label]) => `<div class="stat"><span>${label}</span><div class="bar"><i style="width:${f.stats[k]}%"></i></div><b>${f.stats[k]}</b></div>`)
    .join('');
  const brand = window.GAME?.brand ?? {};
  const look = f.look ?? lookFor(f.username);
  const free = f.pointsFree ?? 0;

  el.innerHTML = `
    <a href="/" data-link class="sub back">← Cari nama lain</a>
    ${welcome ? `<p class="banner">🥊 Selamat datang di ring, <b>@${esc(f.username)}</b>! Atur tampilan dan bagikan ${free || ''} poin statmu dulu, Fight Night pertamamu dimulai saat fitur pertandingan rilis.</p>` : ''}
    <article class="fcard">
      <div class="fcard-head">
        <div class="fcard-av">${avatar(look, { size: 92, title: `Avatar @${f.username}` })}</div>
        <div class="fcard-id">
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
          ${f.registered && f.tier ? `<span class="pill tier">${esc(f.tier.icon)} ${esc(f.tier.name)}</span>` : ''}
          <span class="pill">${esc(f.kelas)}</span>
          <span class="pill">${esc(f.styleName)}</span>
          ${f.registered ? '' : '<span class="pill">Belum daftar</span>'}
        </div>
        ${levelBlock(f)}
        ${f.registered ? `<div class="record">
          <div><b>${f.record.w}</b><small>Menang</small></div>
          <div><b>${f.record.l}</b><small>Kalah</small></div>
          <div><b>${f.record.d}</b><small>Seri</small></div>
        </div>` : ''}
        <div class="stats">${stats}</div>
      </div>
    </article>
    ${mine ? `<section class="card">
      <h2>Atur petarung</h2>
      <nav class="edit-links" aria-label="Atur petarung">
        <a href="/profil/tampilan" data-link><span class="ic" aria-hidden="true">🎨</span><span><b>Tampilan</b><small>Wajah, rambut, sarung tinju</small></span></a>
        <a href="/profil/identitas" data-link><span class="ic" aria-hidden="true">✏️</span><span><b>Julukan & kota</b><small>“${esc(f.nick)}”</small></span></a>
        <a href="/profil/stat" data-link><span class="ic" aria-hidden="true">💪</span><span><b>Poin stat</b><small>${free ? `${free} poin belum dipakai` : 'Semua poin sudah terpasang'}</small></span>${free ? `<em class="dot">${free}</em>` : ''}</a>
      </nav>
    </section>
    <section class="card">
      <h2>Akunmu</h2>
      <p class="sub">${f.verified ? 'Terverifikasi lewat Instagram.' : 'Masuk lewat mode manual/pengembang, belum terverifikasi Instagram.'}</p>
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
