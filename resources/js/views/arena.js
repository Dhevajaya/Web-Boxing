// Arena: memutar ulang laga dari input yang tersimpan (stat, gaya, seed, versi) dengan
// animasi tampak samping, efek suara, dan komentator bersuara (resources/js/game/stage.js).
// Hasil di browser harus sama dengan hasil server; bila tidak, ditandai.
import { $, esc, api, fmtDate, KIND } from '../ui.js';
import { simulate, lookFor, LOOKS, rules, JURUS } from '../game/index.js';
import { describe, resultLine } from '../game/commentary.js';
import { createStage } from '../game/stage.js';
import { session } from '../auth.js';

export function arenaView(el, id, { title = null, extra = null } = {}) {
  let alive = true;
  let stage = null;
  el.innerHTML = '<p class="loading">Menyiapkan gelanggang…</p>';

  const url = typeof id === 'string' && !/^\d+$/.test(id) ? `/api/challenges/${encodeURIComponent(id)}` : `/api/fights/${id}`;
  api(url)
    .then(({ data }) => alive && start(data))
    .catch((e) => {
      if (alive) el.innerHTML = `<a href="/" data-link class="sub back">← Beranda</a><p class="banner bad">${esc(e.status === 404 ? 'Laga tidak ditemukan atau belum dimulai.' : e.message)}</p>`;
    });

  function start(f) {
    const A = f.fighters.a ?? { username: '?', look: lookFor('?') };
    const B = f.fighters.b ?? { username: '?', look: lookFor('?') };
    const names = { a: `@${A.username}`, b: `@${B.username}` };
    document.title = `${names.a} vs ${names.b} · ${window.GAME?.brand?.name ?? ''}`;

    let r;
    try {
      r = simulate(f.a, f.b, f.seed, f.v);
    } catch {
      r = null;
    }
    const match = r && r.win === f.winner && r.how === f.how && r.rd === f.round;
    const v2 = (f.v ?? 1) >= 2;
    const R = v2 ? rules(f.v) : null;
    const staMax = v2 ? { a: R.fight.stamina.base + R.fight.stamina.perStat * f.a.stats.stamina, b: R.fight.stamina.base + R.fight.stamina.perStat * f.b.stats.stamina } : null;
    const meters = (s) => `<div class="meters"><span class="mt napas" title="Napas"><i id="n${s}" style="width:100%"></i></span><span class="mt bara" title="Meter Bara"><i id="m${s}" style="width:0%"></i></span><span class="leg" id="l${s}" title="Kaki" hidden>🦵</span></div>`;

    el.innerHTML = `
      <a href="${session.me ? `/${esc(session.me.username)}` : '/'}" data-link class="sub back">← Kembali</a>
      <section class="arena2" aria-label="Arena">
        <div class="arena-meta"><span class="eyebrow">${esc(title ?? KIND[f.kind] ?? 'Laga')}</span>
          <span class="sub">${esc(fmtDate(f.at, { weekday: 'short' }))} WIB</span>
          <span class="arena-tools"><button class="snd" id="fxBtn" type="button" title="Efek visual">✦</button><button class="snd" id="snd" type="button" aria-pressed="false" title="Suara">🔊</button></span></div>
        <div class="arena-top">
          <div class="corner blue"><a href="/${esc(A.username)}" data-link class="cname">${esc(names.a)}</a><div class="hp blue"><i id="ha" style="width:100%"></i></div>${v2 ? meters('a') : ''}</div>
          <div class="rd"><b id="rd">R1</b><small>RONDE</small></div>
          <div class="corner red"><a href="/${esc(B.username)}" data-link class="cname">${esc(names.b)}</a><div class="hp red"><i id="hb" style="width:100%"></i></div>${v2 ? meters('b') : ''}</div>
        </div>
        ${v2 ? '' : '<p class="archive-tag">Arsip era tinju · diputar dengan aturan lama</p>'}
        <div class="ring-wrap"><div class="ring-stage" id="stage"></div>
          <button class="tap-sound" id="tapSound" type="button" hidden>🔊 Ketuk untuk suara</button></div>
        <div class="controls">
          <button class="btn btn-ghost btn-sm" id="play" type="button">Jeda</button>
          <button class="btn btn-ghost btn-sm" id="speed" type="button">1×</button>
          <button class="btn btn-ghost btn-sm" id="skip" type="button">Lewati ke hasil</button>
        </div>
        <ul class="feed" id="feed" aria-live="off"></ul>
        <p class="hint" id="voiceHint" hidden>Komentator bersuara butuh suara Bahasa Indonesia di perangkat ini (Pengaturan → Teks ke ucapan). Efek suara tetap jalan.</p>
      </section>
      <section class="card result-card" id="res" hidden></section>
      ${extra ? '<div id="extra"></div>' : ''}
      ${!r ? '<p class="parity bad">Versi mesin laga ini tidak tersedia di browser. Muat ulang halaman.</p>' : ''}
      ${r && !match ? '<p class="parity bad">✗ Hasil di browser berbeda dengan server. Laporkan ke admin.</p>' : ''}`;
    if (extra) extra($('#extra', el), f);
    if (!r) return showResult(f, names);

    const feed = $('#feed', el);
    const setHp = (ha, hb) => {
      $('#ha', el).style.width = `${Math.max(0, ha) / 10}%`;
      $('#hb', el).style.width = `${Math.max(0, hb) / 10}%`;
    };
    const addLine = (e, text = null) => {
      const li = document.createElement('li');
      li.textContent = text ?? describe(e, names);
      if (['big', 'kd', 'ko'].includes(e.t)) li.className = 'big';
      if (e.t === 'bell' || e.t === 'end') li.className = 'bell';
      feed.prepend(li);
      while (feed.children.length > 40) feed.lastElementChild.remove();
    };

    let fxLevel = 'full';
    try { fxLevel = localStorage.getItem('wb.fx') || 'full'; } catch { /* diblokir */ }
    const setStatus = (e) => {
      if (!v2 || e.na === undefined) return;
      for (const s of ['a', 'b']) {
        const n = $(`#n${s}`, el);
        if (!n) return;
        n.style.width = `${Math.max(0, Math.min(100, (e[`n${s}`] / staMax[s]) * 100))}%`;
        $(`#m${s}`, el).style.width = `${e[`m${s}`]}%`;
        $(`#m${s}`, el).parentElement.classList.toggle('full', e[`m${s}`] >= 100);
        const leg = $(`#l${s}`, el);
        leg.hidden = e[`l${s}`] < 25;
        leg.classList.toggle('bad', e[`l${s}`] >= 50);
      }
    };
    stage = createStage($('#stage', el), {
      catalog: LOOKS,
      looks: { a: A.look ?? lookFor(A.username), b: B.look ?? lookFor(B.username) },
      names,
      brand: window.GAME?.brand ?? {},
      fx: fxLevel,
      jurusNames: Object.fromEntries(Object.values(JURUS).map((j) => [j.id, j.name])),
    }, r, {
      onEvent: (e, i, text) => addLine(e, text),
      onStatus: setStatus,
      onHp: setHp,
      onRound: (rd) => { $('#rd', el).textContent = `R${rd}`; },
      onDone: () => {
        if (!alive) return;
        $('#play', el).disabled = true;
        $('#skip', el).disabled = true;
        showResult(f, names, r);
      },
    });

    const snd = $('#snd', el);
    const paintSnd = () => {
      snd.textContent = stage.muted ? '🔇' : '🔊';
      snd.setAttribute('aria-pressed', String(stage.muted));
      snd.setAttribute('aria-label', stage.muted ? 'Nyalakan suara' : 'Matikan suara');
    };
    paintSnd();
    snd.onclick = () => { stage.setMuted(!stage.muted); paintSnd(); };
    const fxBtn = $('#fxBtn', el);
    const FX = { full: ['Efek: Penuh', '✦'], lite: ['Efek: Ringan', '✧'], off: ['Efek: Hemat', '·'] };
    const paintFx = () => { fxBtn.textContent = FX[fxLevel][1]; fxBtn.title = `${FX[fxLevel][0]} (berlaku di laga berikutnya)`; fxBtn.setAttribute('aria-label', FX[fxLevel][0]); };
    paintFx();
    fxBtn.onclick = () => {
      fxLevel = { full: 'lite', lite: 'off', off: 'full' }[fxLevel];
      try { localStorage.setItem('wb.fx', fxLevel); } catch { /* abaikan */ }
      paintFx();
      addLine({ t: 'bell' }, `${FX[fxLevel][0]} dipakai mulai laga berikutnya.`);
    };
    // Daftar suara perangkat dimuat belakangan di beberapa browser.
    setTimeout(() => { if (alive && !stage.muted && !stage.voiceAvailable()) $('#voiceHint', el).hidden = false; }, 1500);

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
    $('#skip', el).onclick = () => {
      stage.skip();
      feed.innerHTML = '';
      for (const e of [...r.events].reverse().slice(0, 12).reverse()) addLine(e);
    };
    stage.play();
    // Suara diblokir browser (link dibuka langsung): tawarkan tombol, sentuhan apa pun juga membukanya.
    const tap = $('#tapSound', el);
    const unlock = () => {
      stage?.resume();
      setTimeout(() => { if (tap.isConnected) tap.hidden = !stage.audioBlocked() || stage.muted; }, 150);
    };
    setTimeout(() => { if (alive && stage.audioBlocked() && !stage.muted) tap.hidden = false; }, 400);
    tap.onclick = unlock;
    el.addEventListener('pointerdown', unlock, { once: true });
  }

  function showResult(f, names, r = null) {
    const line = resultLine(r ?? { win: f.winner, how: f.how, rd: f.round, scoreA: f.scoreA, scoreB: f.scoreB }, names);
    const mySide = session.me && (f.fighters.a?.username === session.me.username ? 'a' : f.fighters.b?.username === session.me.username ? 'b' : null);
    const myXp = mySide ? f.xp?.[mySide] : 0;
    const box = $('#res', el);
    if (!box) return;
    box.hidden = false;
    box.innerHTML = `
      <div class="result"><b>${esc(line.title)}</b><span>${esc(line.sub)}</span></div>
      ${f.summary?.rounds?.length ? `<div class="cards">${f.summary.rounds.map(([a, b], k) => `<span><small>R${k + 1}</small>${a} - ${b}</span>`).join('')}</div>` : ''}
      ${myXp ? `<p class="xp-gain">+${myXp} XP untukmu</p>` : ''}
      <div class="row">
        <button class="btn btn-ghost" type="button" id="share">Bagikan</button>
        <button class="btn btn-ghost" type="button" id="again">Putar ulang</button>
      </div>`;
    $('#again', box).onclick = () => { stage?.destroy(); arenaView(el, id, { title, extra }); };
    $('#share', box).onclick = async (e) => {
      const btn = e.currentTarget;
      try {
        if (navigator.share) await navigator.share({ title: document.title, url: location.href });
        else {
          await navigator.clipboard.writeText(location.href);
          btn.textContent = 'Link tersalin';
        }
      } catch { /* dibatalkan */ }
    };
  }

  return { cleanup: () => { alive = false; stage?.destroy(); } };
}
