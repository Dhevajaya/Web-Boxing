// Naik Gelanggang: pusat laga. Malam Gelanggang berikutnya, laga terakhirmu, Ajak Duel lawan setara,
// dan pintu ke Papan Jagoan serta Piala Kampung.
import { $, esc, api, sendChallenge, countdown, RESULT, normalizeUsername } from '../ui.js';
import { avatar, ARCHETYPES } from '../game/index.js';
import { session, openLogin } from '../auth.js';
import { navigate } from '../router.js';

export function gelanggangView(el) {
  let alive = true;
  let stopCd = () => {};
  document.title = `Naik Gelanggang · ${window.GAME?.brand?.name ?? ''}`;
  const piala = window.GAME?.features?.piala !== false;

  el.innerHTML = `
    <section class="hero"><span class="eyebrow tape">Dari gang, naik gelanggang</span><h1>Naik <em>Gelanggang</em></h1>
      <p>Laga berjalan otomatis sesuai atribut, gaya bertarung, dan jurus andalanmu.</p></section>
    <section class="card hazard" id="night" aria-live="polite">
      <div class="fn-row"><div><span class="eyebrow">Malam Gelanggang berikutnya</span><b class="fn-time" id="nTime">…</b></div><b class="fn-cd" id="nCd">--:--:--</b></div>
      <div id="nMine"></div>
      <p class="sub">Semua petarung yang aktif 14 hari terakhir otomatis dipasangkan tiap 6 jam.</p>
    </section>
    <section class="card">
      <h2>Ajak Duel</h2>
      <p class="sub">Duel langsung, hasil saat itu juga. Hanya penantang yang dapat XP.</p>
      <form class="field" id="find" autocomplete="off"><span class="at">@</span>
        <input id="fq" placeholder="username lawan" aria-label="Username lawan" autocapitalize="none" spellcheck="false">
        <button class="btn btn-accent btn-sm" type="submit">Ajak</button></form>
      <p class="m-status" id="fErr" hidden></p>
      <div id="spar"></div>
    </section>
    <div class="row gl-links">
      <a class="btn btn-ghost" href="/ranking" data-link>🏆 Papan Jagoan</a>
      ${piala ? '<a class="btn btn-ghost" href="/piala" data-link>🥇 Piala Kampung</a>' : ''}
    </div>`;

  api('/api/fight-night').then(({ data }) => {
    if (!alive) return;
    const tz = window.GAME?.brand?.timezone;
    $('#nTime', el).textContent = `${new Date(data.next).toLocaleString('id-ID', { weekday: 'long', hour: '2-digit', minute: '2-digit', timeZone: tz })} WIB`;
    stopCd = countdown(data.next, (t, s) => { const c = $('#nCd', el); if (c) c.textContent = t; if (s === 0) stopCd(); });
    if (data.mine) {
      const [, label, cls] = RESULT[data.mine.result];
      $('#nMine', el).innerHTML = `<a class="fn-mine" href="/laga/${data.mine.id}" data-link><span class="res ${cls}">${label}</span> laga terakhirmu vs <b>@${esc(data.mine.opponent?.username ?? '?')}</b> (${esc(data.mine.how)}) <span class="go">Tonton ›</span></a>`;
    }
  }).catch(() => { if (alive) $('#night', el).hidden = true; });

  const status = (msg) => { const p = $('#fErr', el); p.hidden = !msg; p.textContent = msg ?? ''; };
  const challenge = async (u, btn) => {
    if (!session.me) return openLogin();
    if (btn) btn.disabled = true;
    const err = await sendChallenge(u, navigate);
    if (err && alive) { status(err); if (btn) btn.disabled = false; }
  };
  $('#find', el).onsubmit = (e) => {
    e.preventDefault();
    const u = normalizeUsername($('#fq', el).value);
    if (!u) return status('Username tidak valid.');
    if (u === session.me?.username) return status('Tidak bisa menantang diri sendiri.');
    status(null);
    return challenge(u, e.submitter);
  };

  const box = $('#spar', el);
  if (!session.me) {
    box.innerHTML = '<button class="btn btn-ghost btn-block" type="button" id="lg">Masuk untuk melihat lawan setara</button>';
    $('#lg', box).onclick = () => openLogin();
  } else {
    box.innerHTML = '<p class="loading">Mencari lawan setara…</p>';
    api('/api/spar').then(({ data }) => {
      if (!alive) return;
      box.innerHTML = `<p class="sub">Lawan setara (★ ${data.stars} ± ${data.range}), berganti tiap hari.</p>
        <ul class="spar">${data.rows.map((r) => `<li>
          <a class="mini-av lg" href="/${esc(r.username)}" data-link>${avatar(r.look, { size: 44 })}</a>
          <a class="rk-id" href="/${esc(r.username)}" data-link><b>@${esc(r.username)}</b><small>${r.sasana ? `${esc(r.sasana.icon)} ` : ''}Lv${r.level} · ★ ${r.stars} · NILAI ${r.ovr}${r.style && ARCHETYPES[r.style] ? ` · ${esc(ARCHETYPES[r.style].name)}` : ''}</small></a>
          <button class="btn btn-accent btn-sm" type="button" data-u="${esc(r.username)}">Ajak Duel</button></li>`).join('') || '<li class="empty">Belum ada lawan setara yang aktif. Ketik username di atas.</li>'}</ul>`;
      box.querySelectorAll('[data-u]').forEach((b) => b.addEventListener('click', () => challenge(b.dataset.u, b)));
    }).catch((e) => { if (alive) box.innerHTML = `<p class="m-status">${esc(e.message)}</p>`; });
  }

  return { cleanup: () => { alive = false; stopCd(); } };
}
