import { $, esc, api, normalizeUsername, countdown, RESULT } from '../ui.js';
import { navigate } from '../router.js';
import { baseFighter, defaultInput, simulate, signature, ovr, lookFor, avatar, LOOKS, JURUS, ARCHETYPES, rules } from '../game/index.js';
import { createStage } from '../game/stage.js';
import { describe, resultLine } from '../game/commentary.js';
import { session, openLogin } from '../auth.js';

export function homeView(el) {
  const brand = window.GAME?.brand ?? {};
  const expired = new URLSearchParams(location.search).get('masuk') === 'kedaluwarsa';
  if (expired) history.replaceState({}, '', '/');
  el.innerHTML = `
    ${expired ? '<p class="banner bad">Link masuk itu sudah kedaluwarsa atau sudah dipakai. Minta link baru ya.</p>' : ''}
    <section class="hero">
      <span class="eyebrow tape">${esc(brand.tagline ?? 'Dari Gang, Naik Gelanggang.')}</span>
      <h1>Siapa <em>jagoan</em> gang ini?</h1>
      <p>Ketik username Instagram untuk membuka berkas petarungnya.</p>
    </section>

    <section class="card fn-card hazard" id="fn" aria-live="polite">
      <div class="fn-row"><div><span class="eyebrow">Malam Gelanggang berikutnya</span><b class="fn-time" id="fnTime">…</b></div><b class="fn-cd" id="fnCd">--:--:--</b></div>
      <div id="fnMine"></div>
      <div class="row"><a class="btn btn-ghost btn-sm" href="/ranking" data-link>🏆 Papan Jagoan</a>${window.GAME?.features?.piala === false ? '' : '<a class="btn btn-ghost btn-sm" href="/piala" data-link>🥇 Piala</a>'}<a class="btn btn-ghost btn-sm" href="/papan-kabar" data-link>📌 Kabar</a></div>
    </section>

    <form class="field" id="search" autocomplete="off">
      <span class="at">@</span>
      <input id="q" type="text" autocapitalize="none" spellcheck="false" placeholder="username" aria-label="Username Instagram">
      <button class="btn btn-accent btn-sm" type="submit">Cari</button>
    </form>
    <p class="err" id="qErr" hidden>Username tidak valid.</p>

    <section class="card" aria-labelledby="demoH">
      <h2 id="demoH">Duel Coba</h2>
      <p class="sub">Adu dua username dengan stat dasar dan jurus khasnya. Laga dihitung di browser lalu dicocokkan dengan server: hasilnya harus identik.</p>
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
        <div class="arena-top demo-rd"><div class="rd"><b id="rd">R1</b><small>RONDE</small></div>
          <button class="snd" id="snd" type="button" aria-pressed="false" title="Suara">🔊</button></div>
        <div class="ring-wrap"><div class="ring-stage" id="stage"></div>
          <button class="tap-sound" id="tapSound" type="button" hidden>🔊 Ketuk untuk suara</button></div>
        <div class="controls">
          <button class="btn btn-ghost btn-sm" id="play" type="button">Jeda</button>
          <button class="btn btn-ghost btn-sm" id="speed" type="button">1×</button>
          <button class="btn btn-ghost btn-sm" id="skip" type="button">Lewati ke hasil</button>
        </div>
        <ul class="feed" id="feed"></ul>
        <div class="result" id="res" hidden><b id="resT"></b><span id="resS"></span></div>
        <p class="parity" id="parity"></p>
      </div>
    </section>

    ${session.me ? '' : `<section class="card">
      <h2>Naik ke gelanggang</h2>
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

  let stage = null;
  $('#go', el).onclick = async () => {
    stage?.destroy();
    stage = null;
    const ua = normalizeUsername($('#ua', el).value);
    const ub = normalizeUsername($('#ub', el).value);
    const seed = Number($('#seed', el).value) >>> 0;
    if (!ua || !ub) return;

    const fa = baseFighter(ua);
    const fb = baseFighter(ub);
    const A = defaultInput(ua);
    const B = defaultInput(ub);
    const r = simulate(A, B, seed);
    const names = { a: `@${ua}`, b: `@${ub}` };

    $('#ring', el).hidden = false;
    $('#res', el).hidden = true;
    $('#na', el).textContent = `${names.a} · ${ovr(fa.base)} · ${ARCHETYPES[fa.style]?.name ?? ''}`;
    $('#nb', el).textContent = `${ARCHETYPES[fb.style]?.name ?? ''} · ${ovr(fb.base)} · ${names.b}`;
    $('#rd', el).textContent = 'R1';
    const feed = $('#feed', el);
    feed.innerHTML = '';
    const setHp = (ha, hb) => {
      $('#ha', el).style.width = `${Math.max(0, ha) / 10}%`;
      $('#hb', el).style.width = `${Math.max(0, hb) / 10}%`;
    };
    setHp(1000, 1000);

    // Tampilan dari hash dulu; diganti tampilan pilihan pemain bila sudah daftar.
    const lookOf = (u) => api(`/api/fighters/${u}`).then(({ data }) => data.look ?? lookFor(u)).catch(() => lookFor(u));
    const btn = $('#go', el);
    btn.disabled = true;
    const [la, lb] = await Promise.all([lookOf(ua), lookOf(ub)]);
    btn.disabled = false;
    if (!alive) return;
    $('#aa', el).innerHTML = avatar(la, { size: 64, title: names.a });
    $('#ab', el).innerHTML = avatar(lb, { size: 64, flip: true, title: names.b });

    const addLine = (e, text = null) => {
      const li = document.createElement('li');
      li.textContent = text ?? describe(e, names);
      if (['big', 'kd', 'ko'].includes(e.t)) li.className = 'big';
      if (e.t === 'bell' || e.t === 'end') li.className = 'bell';
      feed.prepend(li);
      while (feed.children.length > 40) feed.lastElementChild.remove();
    };
    const showResult = () => {
      const line = resultLine(r, names);
      $('#resT', el).textContent = line.title;
      $('#resS', el).textContent = line.sub;
      $('#res', el).hidden = false;
    };
    let fxLevel = 'full';
    try { fxLevel = localStorage.getItem('wb.fx') || 'full'; } catch { /* diblokir */ }
    stage = createStage($('#stage', el), { catalog: LOOKS, looks: { a: la, b: lb }, names, brand, fx: fxLevel, jurusNames: Object.fromEntries(Object.values(JURUS).map((j) => [j.id, j.name])) }, r, {
      onEvent: (e, i, text) => addLine(e, text),
      onHp: setHp,
      onRound: (rd) => { $('#rd', el).textContent = `R${rd}`; },
      onDone: () => {
        if (!alive) return;
        $('#play', el).disabled = true;
        $('#skip', el).disabled = true;
        showResult();
      },
    });
    for (const id of ['#play', '#skip']) $(id, el).disabled = false;
    $('#play', el).textContent = 'Jeda';

    const snd = $('#snd', el);
    const paintSnd = () => {
      snd.textContent = stage.muted ? '🔇' : '🔊';
      snd.setAttribute('aria-pressed', String(stage.muted));
      snd.setAttribute('aria-label', stage.muted ? 'Nyalakan suara' : 'Matikan suara');
    };
    paintSnd();
    snd.onclick = () => { stage?.setMuted(!stage.muted); paintSnd(); };
    $('#play', el).onclick = (ev) => {
      if (stage.playing) stage.pause();
      else stage.play();
      ev.currentTarget.textContent = stage.playing ? 'Jeda' : 'Putar';
    };
    $('#speed', el).onclick = (ev) => {
      const next = { 1: 2, 2: 4, 4: 1 }[ev.currentTarget.dataset.s ?? '1'];
      ev.currentTarget.dataset.s = String(next);
      ev.currentTarget.textContent = `${next}×`;
      stage.setSpeed(next);
    };
    $('#speed', el).dataset.s = '1';
    $('#speed', el).textContent = '1×';
    $('#skip', el).onclick = () => {
      stage.skip();
      feed.innerHTML = '';
      for (const e of [...r.events].reverse().slice(0, 12).reverse()) addLine(e);
    };
    stage.play();
    // Tombol "Tanding!" sendiri adalah sentuhan, jadi suara biasanya langsung jalan.
    const tap = $('#tapSound', el);
    tap.hidden = true;
    setTimeout(() => { if (alive && stage?.audioBlocked() && !stage.muted) tap.hidden = false; }, 400);
    tap.onclick = () => { stage?.resume(); tap.hidden = true; };
    $('#ring', el).scrollIntoView({ behavior: 'smooth', block: 'start' });

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

  return { cleanup: () => { alive = false; stage?.destroy(); stopCd(); } };
}
