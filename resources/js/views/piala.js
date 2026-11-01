import { $, esc, api, fmtDate, countdown } from '../ui.js';
import { avatar } from '../game/index.js';
import { session, openLogin } from '../auth.js';

const side = (f, won, lost) => f
  ? `<span class="bm-p${won ? ' won' : ''}${lost ? ' lost' : ''}"><span class="mini-av">${avatar(f.look, { size: 28 })}</span><span class="bm-n">@${esc(f.username)}</span></span>`
  : '<span class="bm-p tbd"><span class="mini-av"></span><span class="bm-n">—</span></span>';

function matchCard(m) {
  const won = (s) => m.winner === s;
  const lost = (s) => m.winner && m.winner !== s;
  const inner = `${side(m.a, won('a'), lost('a'))}${side(m.b, won('b'), lost('b'))}
    <span class="bm-meta">${m.walkover ? 'Bye' : m.fight_id ? 'Tonton ›' : esc(fmtDate(m.at, { day: undefined, month: undefined, weekday: 'short' }))}</span>`;
  return m.fight_id ? `<a class="bm" href="/laga/${m.fight_id}" data-link>${inner}</a>` : `<div class="bm">${inner}</div>`;
}

export function pialaView(el) {
  let alive = true;
  let stop = () => {};
  document.title = `Piala Mingguan · ${window.GAME?.brand?.name ?? ''}`;

  const load = () => api('/api/piala', { cache: 'no-cache' }).then(({ data }) => alive && draw(data)).catch((e) => {
    if (alive) el.innerHTML = `<p class="banner bad">${esc(e.message)}</p>`;
  });

  function draw(d) {
    stop();
    const c = d.cup;
    const me = d.me;
    const week = new Date(`${c.week}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long' });
    const myRounds = d.bracket ? d.bracket.flatMap((r) => r.matches.filter((m) => [m.a?.username, m.b?.username].includes(session.me?.username)).map((m) => ({ ...m, name: r.name }))) : [];

    let status = '';
    if (c.status === 'open') {
      status = `
        <div class="fn-row"><div><span class="eyebrow">Undian</span><b class="fn-time">${esc(fmtDate(c.draw_at, { weekday: 'long', day: undefined, month: undefined }))} WIB</b></div><b class="fn-cd" id="cd">--:--:--</b></div>
        <p class="sub"><b>${c.entrants}</b> petarung mendaftar (minimal ${c.min_entrants}). ${c.auto_active_days ? `Pemain yang aktif ${c.auto_active_days} hari terakhir ikut otomatis saat undian; tekan <b>Mundur</b> kalau tidak mau ikut.` : ''}</p>
        ${me ? (me.entered
          ? '<div class="row"><span class="pill on">✓ Kamu terdaftar</span><button class="btn btn-ghost btn-sm" id="leave" type="button">Mundur</button></div>'
          : '<button class="btn btn-accent btn-block" id="join" type="button">🏆 Daftar Piala</button>')
          : '<button class="btn btn-accent btn-block" id="login" type="button">Masuk untuk mendaftar</button>'}`;
    } else if (c.status === 'drawn') {
      status = `
        <p class="sub"><b>${c.entrants}</b> petarung · bagan ${c.size} · ${c.rounds} babak. Babak awal ${esc(fmtDate(c.early_at, { weekday: 'long', day: undefined, month: undefined }))} WIB, final ${esc(fmtDate(c.final_at, { weekday: 'long', day: undefined, month: undefined }))} WIB.</p>
        ${me?.seed ? `<p class="banner">${me.out_round ? `Kamu tersingkir di babak ${me.out_round}. Coba lagi minggu depan!` : `Kamu unggulan <b>#${me.seed}</b> dan masih bertahan. 🔥`}</p>` : ''}`;
    } else if (c.status === 'done') {
      status = c.champion ? `<a class="champ" href="/${esc(c.champion.username)}" data-link>
        <span class="champ-av">${avatar(c.champion.look, { size: 96 })}</span>
        <span><span class="eyebrow">Juara minggu ini</span><b>@${esc(c.champion.username)}</b><small>dari ${c.entrants} petarung</small></span></a>` : '';
    } else {
      status = `<p class="sub">Piala minggu ini batal karena peserta kurang dari ${c.min_entrants}. Pendaftaran minggu depan dibuka Senin 00.00 WIB.</p>`;
    }

    el.innerHTML = `
      <section class="hero"><span class="eyebrow">Minggu ${esc(week)}</span><h1>Piala <em>Mingguan</em></h1>
        <p>Turnamen gugur. Kalah sekali langsung pulang, juaranya dapat lencana permanen.</p></section>
      <section class="card">${status}<p class="m-status" id="pErr" hidden></p></section>
      ${myRounds.length ? `<section class="card"><h2>Lagamu</h2><div class="bm-list">${myRounds.map((m) => `<div><span class="eyebrow">${esc(m.name)}</span>${matchCard(m)}</div>`).join('')}</div></section>` : ''}
      ${d.bracket ? `<section class="card"><h2>Bagan</h2>
        <div class="bracket">${d.bracket.filter((r) => r.full).map((r) => `
          <div class="br-col"><div class="br-h"><b>${esc(r.name)}</b><small>${esc(fmtDate(r.at, { day: undefined, month: undefined, weekday: 'short' }))}</small></div>
          ${r.matches.map(matchCard).join('')}</div>`).join('')}</div>
        ${d.bracket.some((r) => !r.full) ? `<p class="hint">Babak awal (${d.bracket.filter((r) => !r.full).map((r) => `${esc(r.name)}: ${r.matches_total} laga`).join(', ')}) tidak ditampilkan semua; lagamu ada di atas.</p>` : ''}
      </section>` : ''}
      ${d.history.length ? `<section class="card"><h2>Juara sebelumnya</h2><ol class="mini-rank">${d.history.map((h) => `<li><span class="rk">🥇</span>${h.champion ? `<a class="who" href="/${esc(h.champion.username)}" data-link><span class="mini-av">${avatar(h.champion.look, { size: 28 })}</span>@${esc(h.champion.username)}</a>` : '?'}<span class="sub">${esc(new Date(`${h.week}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }))} · ${h.entrants} peserta</span></li>`).join('')}</ol></section>` : ''}`;

    if (c.status === 'open') stop = countdown(c.draw_at, (t, s) => { const x = $('#cd', el); if (x) x.textContent = t; if (s === 0) { stop(); setTimeout(load, 5000); } });
    const act = (path) => async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      try {
        await api(path, { method: 'POST' });
        load();
      } catch (err) {
        const p = $('#pErr', el);
        if (p) { p.hidden = false; p.textContent = err.message; }
        btn.disabled = false;
      }
    };
    $('#join', el)?.addEventListener('click', act('/api/piala/join'));
    $('#leave', el)?.addEventListener('click', act('/api/piala/leave'));
    $('#login', el)?.addEventListener('click', () => openLogin());
  }

  el.innerHTML = '<p class="loading">Memuat piala…</p>';
  load();
  return { cleanup: () => { alive = false; stop(); } };
}
