import { $, esc, api, normalizeUsername, countdown, RESULT } from '../ui.js';
import { navigate } from '../router.js';
import { baseFighter, simulate, signature, ovr, lookFor, avatar } from '../game/index.js';
import { describe, resultLine } from '../game/commentary.js';
import { session, openLogin } from '../auth.js';

export function homeView(el) {
  const brand = window.GAME?.brand ?? {};
  const expired = new URLSearchParams(location.search).get('masuk') === 'kedaluwarsa';
  if (expired) history.replaceState({}, '', '/');
  el.innerHTML = `
    ${expired ? '<p class="banner bad">Link masuk itu sudah kedaluwarsa atau sudah dipakai. Minta link baru ya.</p>' : ''}
    <section class="hero">
      <span class="eyebrow">Fight Night tiap 6 jam</span>
      <h1>Mana <em>lawan</em>mu?</h1>
      <p>Ketik username Instagram untuk melihat kartu petarungnya.</p>
    </section>

    <section class="card fn-card" id="fn" aria-live="polite">
      <div class="fn-row"><div><span class="eyebrow">Fight Night berikutnya</span><b class="fn-time" id="fnTime">…</b></div><b class="fn-cd" id="fnCd">--:--:--</b></div>
      <div id="fnMine"></div>
      <div class="row"><a class="btn btn-ghost btn-sm" href="/ranking" data-link>🏆 Ranking</a>${window.GAME?.features?.piala === false ? '' : '<a class="btn btn-ghost btn-sm" href="/piala" data-link>🥇 Piala</a>'}</div>
    </section>

    <form class="field" id="search" autocomplete="off">
      <span class="at">@</span>
      <input id="q" type="text" autocapitalize="none" spellcheck="false" placeholder="username" aria-label="Username Instagram">
      <button class="btn btn-accent btn-sm" type="submit">Cari</button>
    </form>
    <p class="err" id="qErr" hidden>Username tidak valid.</p>

    <section class="card" aria-labelledby="demoH">
      <h2 id="demoH">Uji mesin</h2>
      <p class="sub">Laga dihitung di browser dari seed, lalu dibandingkan dengan hitungan server. Hasilnya harus identik.</p>
      <div class="row">
        <label class="field"><span class="at">@</span><input id="ua" value="si.biru" aria-label="Petarung sudut biru"></label>
        <label class="field"><span class="at">@</span><input id="ub" value="si.merah" aria-label="Petarung sudut merah"></label>
      </div>
      <div class="row">
        <label class="field"><span class="at">#</span><input id="seed" inputmode="numeric" value="2026" aria-label="Seed"></label>
        <button class="btn btn-ghost" id="rand" type="button">Acak seed</button>
      </div>
      <button class="btn btn-accent btn-block" id="go" type="button">Tanding!</button>
      <div id="ring" hidden>
        <div class="versus">
          <div><div class="side-av" id="aa"></div><div class="side-name blue" id="na"></div><div class="hp blue"><i id="ha" style="width:100%"></i></div></div>
          <span class="vs">VS</span>
          <div><div class="side-av red" id="ab"></div><div class="side-name red" id="nb"></div><div class="hp red"><i id="hb" style="width:100%"></i></div></div>
        </div>
        <ul class="feed" id="feed"></ul>
        <div class="result" id="res" hidden><b id="resT"></b><span id="resS"></span></div>
        <p class="parity" id="parity"></p>
      </div>
    </section>

    ${session.me ? '' : `<section class="card">
      <h2>Jadi petarung</h2>
      <p class="sub">Follow <b>@${esc(brand.instagram)}</b> lalu kirim DM <b>${esc(brand.keyword)}</b>. Tanpa password.</p>
      <button class="btn btn-accent btn-block" id="joinBtn" type="button">Masuk / daftar</button>
    </section>`}
  `;
  $('#joinBtn', el)?.addEventListener('click', () => openLogin());

  let stopCd = () => {};
  let alive = true;
  api('/api/fight-night').then(({ data }) => {
    if (!alive) return;
    const tz = window.GAME?.brand?.timezone;
    $('#fnTime', el).textContent = `${new Date(data.next).toLocaleString('id-ID', { weekday: 'long', hour: '2-digit', minute: '2-digit', timeZone: tz })} WIB`;
    stopCd = countdown(data.next, (t, s) => {
      const cd = $('#fnCd', el);
      if (cd) cd.textContent = t;
      if (s === 0) stopCd();
    });
    if (data.mine) {
      const [, label, cls] = RESULT[data.mine.result];
      $('#fnMine', el).innerHTML = `<a class="fn-mine" href="/laga/${data.mine.id}" data-link><span class="res ${cls}">${label}</span> laga terakhirmu vs <b>@${esc(data.mine.opponent?.username ?? '?')}</b> (${esc(data.mine.how)}) <span class="go">Tonton ›</span></a>`;
    } else if (session.me) {
      $('#fnMine', el).innerHTML = '<p class="hint">Kamu otomatis ikut selama aktif dalam 14 hari terakhir.</p>';
    }
  }).catch(() => { if (alive) $('#fn', el).hidden = true; });
  if (expired) openLogin();

  $('#search', el).onsubmit = (e) => {
    e.preventDefault();
    const u = normalizeUsername($('#q', el).value);
    $('#qErr', el).hidden = !!u;
    if (u) navigate(`/${u}`);
  };
  $('#rand', el).onclick = () => { $('#seed', el).value = String((Math.random() * 2 ** 32) >>> 0); };

  let timer = null;
  $('#go', el).onclick = () => {
    clearInterval(timer);
    const ua = normalizeUsername($('#ua', el).value);
    const ub = normalizeUsername($('#ub', el).value);
    const seed = Number($('#seed', el).value) >>> 0;
    if (!ua || !ub) return;

    const fa = baseFighter(ua);
    const fb = baseFighter(ub);
    const A = { style: fa.style, stats: fa.base };
    const B = { style: fb.style, stats: fb.base };
    const r = simulate(A, B, seed);
    const names = { a: `@${ua}`, b: `@${ub}` };

    $('#ring', el).hidden = false;
    $('#res', el).hidden = true;
    $('#na', el).textContent = `${names.a} · ${ovr(fa.base)}`;
    $('#nb', el).textContent = `${ovr(fb.base)} · ${names.b}`;
    // Wajah dari hash dulu, lalu diganti tampilan pilihan pemain bila sudah daftar.
    const face = (sel, u, flip) => {
      $(sel, el).innerHTML = avatar(lookFor(u), { size: 64, flip, title: `@${u}` });
      api(`/api/fighters/${u}`).then(({ data }) => {
        if (data.look) $(sel, el).innerHTML = avatar(data.look, { size: 64, flip, title: `@${u}` });
      }).catch(() => {});
    };
    face('#aa', ua, false);
    face('#ab', ub, true);
    const feed = $('#feed', el);
    feed.innerHTML = '';
    const setHp = (e) => {
      $('#ha', el).style.width = `${e.ha / 10}%`;
      $('#hb', el).style.width = `${e.hb / 10}%`;
    };
    setHp({ ha: 1000, hb: 1000 });

    let i = 0;
    timer = setInterval(() => {
      const e = r.events[i++];
      if (!e) {
        clearInterval(timer);
        const line = resultLine(r, names);
        $('#resT', el).textContent = line.title;
        $('#resS', el).textContent = line.sub;
        $('#res', el).hidden = false;
        return;
      }
      setHp(e);
      const li = document.createElement('li');
      li.textContent = describe(e, names);
      if (['big', 'kd', 'ko'].includes(e.t)) li.className = 'big';
      if (e.t === 'bell' || e.t === 'end') li.className = 'bell';
      feed.prepend(li);
    }, 260);

    // Bandingkan dengan hitungan server.
    const sig = signature(r);
    const p = $('#parity', el);
    p.className = 'parity';
    p.textContent = `Browser: ${sig} · menghubungi server…`;
    api(`/api/engine/preview/${ua}/${ub}?seed=${seed}`)
      .then(({ data }) => {
        const same = data.sig === sig;
        p.className = `parity ${same ? 'ok' : 'bad'}`;
        p.textContent = same
          ? `✓ Server menghitung hasil yang sama (sidik jari ${sig}).`
          : `✗ Berbeda! browser ${sig}, server ${data.sig}. Jalankan test vektor.`;
      })
      .catch(() => { p.textContent = `Browser: ${sig} · server tidak terjangkau.`; });
  };

  return { cleanup: () => { alive = false; clearInterval(timer); stopCd(); } };
}
