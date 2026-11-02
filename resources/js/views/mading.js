import { $, esc, api, fmtDate, countdown } from '../ui.js';
import { avatar } from '../game/index.js';

// Teks pengumuman: polos, baris kosong = paragraf baru, URL jadi tautan.
const para = (s) => esc(s).split(/\n{2,}/).map((p) => `<p>${p.replace(/\n/g, '<br>').replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener nofollow">$1</a>')}</p>`).join('');

// link=false dipakai di dalam elemen yang sudah berupa tautan (tautan tidak boleh bersarang).
const who = (f, link = true) => {
  if (!f) return '?';
  const inner = `<span class="mini-av">${avatar(f.look, { size: 32 })}</span>@${esc(f.username)}`;
  return link ? `<a href="/${esc(f.username)}" data-link class="who">${inner}</a>` : `<span class="who">${inner}</span>`;
};

export function madingView(el) {
  let alive = true;
  let stop = () => {};
  document.title = `Papan Kabar · ${window.GAME?.brand?.name ?? ''}`;
  el.innerHTML = '<section class="hero"><span class="eyebrow tape">Kabar dari gang</span><h1>Papan <em>Kabar</em></h1></section><p class="loading">Memuat…</p>';

  api('/api/mading').then(({ data }) => {
    if (!alive) return;
    const n = data.night;
    el.innerHTML = `
      <section class="hero"><span class="eyebrow tape">Kabar dari gang</span><h1>Papan <em>Kabar</em></h1></section>
      <section class="card fn-card">
        <div class="fn-row"><div><span class="eyebrow">Malam Gelanggang berikutnya</span><b class="fn-time">${esc(fmtDate(data.next, { weekday: 'long', day: undefined, month: undefined }))} WIB</b></div><b class="fn-cd" id="cd">--:--:--</b></div>
      </section>
      ${data.news.length ? data.news.map((a) => `
        <article class="card news${a.pinned ? ' pinned' : ''}">
          <div class="news-head">${a.pinned ? '<span class="pill tier">📌 Disematkan</span>' : ''}<span class="sub">${esc(fmtDate(a.at))}</span></div>
          <h2>${esc(a.title)}</h2>
          <div class="news-body">${para(a.body)}</div>
        </article>`).join('') : ''}
      ${n ? `<section class="card">
        <h2>Malam Gelanggang terakhir</h2>
        <p class="sub">${esc(fmtDate(n.slot, { weekday: 'long' }))} WIB</p>
        <div class="record"><div><b>${n.fights}</b><small>Laga</small></div><div><b>${n.ko}</b><small>KO/TKO</small></div><div><b>${n.draws}</b><small>Seri</small></div></div>
        ${n.highlights.map((h) => `<a class="hl" href="/laga/${h.id}" data-link>
          <span class="eyebrow">${esc(h.label)}</span>
          <span class="hl-line">${who(h.winner, false)} mengalahkan ${who(h.loser, false)} · ${esc(h.how)}${h.how !== 'Angka' ? ` R${h.round}` : ''}</span>
          <span class="go">Tonton ›</span></a>`).join('')}
      </section>` : ''}
      <section class="card">
        <h2>Top minggu ini</h2>
        ${data.week.length ? `<ol class="mini-rank">${data.week.map((r, i) => `<li><span class="rk">${i + 1}</span>${who(r)}<span class="sub">${r.w}M ${r.l}K${r.ko ? ` · ${r.ko} KO` : ''}</span></li>`).join('')}</ol>` : '<p class="sub">Belum ada laga minggu ini.</p>'}
        <a class="btn btn-ghost btn-block" href="/ranking?papan=week" data-link>Lihat ranking lengkap</a>
      </section>
      <section class="card">
        <h2>${esc(data.season?.name ?? 'Season')}</h2>
        ${data.season ? `<p class="sub">Berjalan sejak ${esc(fmtDate(data.season.since, { hour: undefined, minute: undefined, year: 'numeric' }))}. Bintang dan peringkat dibekukan saat season ditutup; 10 besar dapat lencana Juara.</p>` : ''}
        ${data.champions.length ? `<h3 class="h3">Hall of Fame</h3><ol class="mini-rank">${data.champions.map((c) => `<li><span class="rk">🏆</span>${who(c.fighter)}<span class="sub">${esc(c.name)} · ★ ${c.stars}</span></li>`).join('')}</ol>` : ''}
      </section>`;
    stop = countdown(data.next, (t) => { const c = $('#cd', el); if (c) c.textContent = t; });
  }).catch((e) => {
    if (alive) el.querySelector('.loading').textContent = e.message;
  });

  return { cleanup: () => { alive = false; stop(); } };
}
