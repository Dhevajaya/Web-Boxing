import { esc, api, STAT_NAMES, fmtDate, RESULT, KIND, badgeLabel, sendChallenge } from '../ui.js';
import { navigate } from '../router.js';
import { baseFighter, ovr, lookFor, avatar, JURUS } from '../game/index.js';
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

// Riwayat laga (terbaru dulu), dengan tombol muat lebih banyak.
export function fightList(box, username) {
  const load = (before) => api(`/api/fighters/${encodeURIComponent(username)}/fights${before ? `?before=${before}` : ''}`)
    .then(({ data }) => {
      if (!box.isConnected) return; // pemain sudah pindah halaman
      const list = box.querySelector('.fights');
      const html = data.items.map((x) => {
        const [letter, label, cls] = RESULT[x.result];
        const o = x.opponent ?? { username: '?', look: null };
        return `<li><a href="${x.kind === 'challenge' && x.code ? `/t/${esc(x.code)}` : `/laga/${x.id}`}" data-link>
          <span class="res ${cls}" title="${label}">${letter}</span>
          <span class="fl-av">${o.look ? avatar(o.look, { size: 36 }) : ''}</span>
          <span class="fl-id"><b>vs @${esc(o.username)}</b><small>${x.v === 1 ? 'Arsip tinju · ' : ''}${esc(KIND[x.kind] ?? x.kind)} · ${esc(x.how)}${x.how !== 'Angka' ? ` R${x.round}` : ''}${x.xp ? ` · +${x.xp} XP` : ''}</small></span>
          <span class="fl-at">${esc(fmtDate(x.at, { hour: undefined, minute: undefined }))}</span></a></li>`;
      }).join('');
      if (!before && !html) list.innerHTML = '<li class="empty">Belum ada laga.</li>';
      else list.insertAdjacentHTML('beforeend', html);
      const more = box.querySelector('.more');
      more.hidden = !data.next;
      more.onclick = () => load(data.next);
    })
    .catch(() => { if (box.isConnected) box.querySelector('.fights').innerHTML = '<li class="empty">Riwayat laga belum bisa dimuat.</li>'; });
  load(null);
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

  const stats = Object.entries(STAT_NAMES).filter(([k]) => f.stats[k] !== undefined)
    .map(([k, label]) => `<div class="stat"><span>${label}</span><div class="bar"><i style="width:${f.stats[k]}%"></i></div><b>${f.stats[k]}</b></div>`)
    .join('');
  const jurus = (f.jurus ?? []).map((j) => JURUS[j.id] ? { ...j, desc: JURUS[j.id].desc } : j);
  const brand = window.GAME?.brand ?? {};
  const look = f.look ?? lookFor(f.username);
  const free = f.pointsFree ?? 0;

  el.innerHTML = `
    <a href="/" data-link class="sub back">← Cari nama lain</a>
    ${welcome ? `<p class="banner">⚑ Selamat datang di gelanggang, <b>@${esc(f.username)}</b>! Pilih gaya bertarung, bagikan ${free || ''} poin atributmu, lalu Malam Gelanggang berikutnya kamu ikut bertarung otomatis.</p>` : ''}
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
          <span class="pill arch-pill" title="${esc(f.passive ? `Pasif: ${f.passive.name}. ${f.passive.desc}` : '')}">${esc(f.styleName)}</span>
          ${f.registered ? '' : '<span class="pill">Belum daftar</span>'}
          ${f.supporter ? `<span class="pill sup-pill">♥ Pendukung</span>` : ''}
          ${f.sasana ? `<span class="pill" style="color:${esc(f.sasana.color)}">${esc(f.sasana.icon)} ${esc(f.sasana.name)}</span>` : ''}
        </div>
        ${f.badges?.length ? `<div class="badges">${f.badges.map((b) => { const [ic, label] = badgeLabel(b.code, b.meta); return `<span class="badge" title="${esc(label)}"><i aria-hidden="true">${ic}</i>${esc(label)}</span>`; }).join('')}</div>` : ''}
        ${levelBlock(f)}
        ${f.registered ? `<div class="record">
          <div><b>${f.record.w}</b><small>Menang</small></div>
          <div><b>${f.record.l}</b><small>Kalah</small></div>
          <div><b>${f.record.d}</b><small>Seri</small></div>
        </div>` : ''}
        ${jurus.length ? `<div class="jurus-mini"><small>Jurus Andalan</small>${jurus.map((j) => `<span title="${esc(j.desc ?? '')}">✦ ${esc(j.name)}</span>`).join('')}</div>` : ''}
        <div class="stats">${stats}</div>
      </div>
    </article>
    ${f.registered && f.active && !mine && !f.npc ? `<section class="card challenge">
      <div><h2>Ajak duel @${esc(f.username)}</h2><p class="sub">Duel kilat 3 ronde. Hasilnya bisa dibagikan.</p></div>
      <button class="btn btn-accent" id="chBtn" type="button">⚑ Ajak Duel!</button>
      <p class="m-status" id="chErr" hidden></p>
    </section>` : ''}
    ${f.registered && f.npc && session.me ? `<section class="card challenge">
      <div><h2>Latih tanding</h2><p class="sub">Jagoan kampung ini siap jadi lawan latihanmu.</p></div>
      <button class="btn btn-ghost" id="chBtn" type="button">⚑ Ajak Duel</button>
      <p class="m-status" id="chErr" hidden></p>
    </section>` : ''}
    ${f.registered ? `<section class="card" id="history">
      <h2>Riwayat Laga</h2>
      <ul class="fights"></ul>
      <button class="btn btn-ghost btn-block more" type="button" hidden>Muat lebih banyak</button>
    </section>` : ''}
    ${mine ? `<section class="card">
      <h2>Atur Identitas</h2>
      <nav class="edit-links" aria-label="Atur Identitas">
        <a href="/profil/gaya" data-link><span class="ic" aria-hidden="true">✦</span><span><b>Gaya & Jurus Andalan</b><small>${esc(f.styleName)} · ${jurus.map((j) => esc(j.name)).join(', ') || 'jurus khas'}</small></span></a>
        <a href="/profil/stat" data-link><span class="ic" aria-hidden="true">💪</span><span><b>Atribut</b><small>${free ? `${free} poin belum dipakai` : 'Semua poin sudah terpasang'}</small></span>${free ? `<em class="dot">${free}</em>` : ''}</a>
        <a href="/profil/tampilan" data-link><span class="ic" aria-hidden="true">🎨</span><span><b>Koleksi</b><small>Wajah, rambut, sarung, celana</small></span></a>
        <a href="/profil/identitas" data-link><span class="ic" aria-hidden="true">✏️</span><span><b>Julukan & kota</b><small>“${esc(f.nick)}”</small></span></a>
        ${window.GAME?.features?.sasana === false ? '' : `<a href="/profil/sasana" data-link><span class="ic" aria-hidden="true">🏴</span><span><b>Geng</b><small>${f.sasana ? `${esc(f.sasana.icon)} ${esc(f.sasana.name)}` : 'Belum gabung geng'}</small></span></a>`}
      </nav>
    </section>
    <section class="card">
      <h2>Akunmu</h2>
      <p class="sub">${f.verified ? 'Terverifikasi lewat Instagram.' : 'Masuk lewat mode manual/pengembang, belum terverifikasi Instagram.'}</p>
      <div class="row">
        <button class="btn btn-ghost" id="logoutBtn" type="button">Cabut</button>
        <button class="btn btn-ghost" id="logoutAllBtn" type="button">Cabut dari semua perangkat</button>
      </div>
    </section>` : ''}
    ${!f.registered && !session.me ? `<section class="card">
      <h2>Ini akunmu?</h2>
      <p class="sub">Berkas ini dibuat otomatis dari username. Follow <b>@${esc(brand.instagram)}</b> lalu kirim DM <b>${esc(brand.keyword)}</b> untuk naik gelanggang.</p>
      <button class="btn btn-accent btn-block" id="claimBtn" type="button">Masuk / daftar</button>
    </section>` : ''}
    ${f.offline ? '<p class="parity">Server belum terhubung; kartu dihitung di browser.</p>' : ''}
  `;

  if (f.registered) fightList(el.querySelector('#history'), f.username);
  el.querySelector('#chBtn')?.addEventListener('click', async (e) => {
    if (!session.me) return openLogin();
    const btn = e.currentTarget;
    btn.disabled = true;
    const err = await sendChallenge(f.username, navigate);
    if (err && btn.isConnected) {
      const p = el.querySelector('#chErr');
      p.hidden = false;
      p.textContent = err;
      btn.disabled = false;
    }
  });
  el.querySelector('#logoutBtn')?.addEventListener('click', () => logout(false));
  el.querySelector('#logoutAllBtn')?.addEventListener('click', () => logout(true));
  el.querySelector('#claimBtn')?.addEventListener('click', () => openLogin());
}
