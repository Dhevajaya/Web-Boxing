// Arena: memutar ulang laga dari input yang tersimpan (stat, gaya, seed, versi).
// Hasil di browser harus sama dengan hasil server; bila tidak, ditandai.
import { $, esc, api, fmtDate, KIND } from '../ui.js';
import { simulate, avatar, lookFor } from '../game/index.js';
import { describe, resultLine } from '../game/commentary.js';
import { session } from '../auth.js';

const PACE = { bell: 1300, miss: 520, hit: 650, big: 950, kd: 1700, ko: 2200, end: 1600 };

export function arenaView(el, id, { title = null, extra = null } = {}) {
  let alive = true;
  let timer = null;
  el.innerHTML = '<p class="loading">Menyiapkan ring…</p>';

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

    el.innerHTML = `
      <a href="${session.me ? `/${esc(session.me.username)}` : '/'}" data-link class="sub back">← Kembali</a>
      <div class="arena-meta"><span class="eyebrow">${esc(title ?? KIND[f.kind] ?? 'Laga')}</span><span class="sub">${esc(fmtDate(f.at, { weekday: 'short' }))} WIB</span></div>
      <section class="arena" aria-label="Arena">
        <div class="arena-top">
          <div class="corner blue"><a href="/${esc(A.username)}" data-link class="cname">${esc(names.a)}</a><div class="hp blue"><i id="ha" style="width:100%"></i></div></div>
          <div class="rd" id="rd">R1</div>
          <div class="corner red"><a href="/${esc(B.username)}" data-link class="cname">${esc(names.b)}</a><div class="hp red"><i id="hb" style="width:100%"></i></div></div>
        </div>
        <div class="ring">
          <div class="boxer a" id="ba">${avatar(A.look, { size: 150, title: names.a })}</div>
          <div class="boxer b" id="bb">${avatar(B.look, { size: 150, flip: true, title: names.b })}</div>
          <div class="pow" id="pow" hidden></div>
        </div>
        <div class="controls">
          <button class="btn btn-ghost btn-sm" id="play" type="button">Jeda</button>
          <button class="btn btn-ghost btn-sm" id="speed" type="button">1×</button>
          <button class="btn btn-ghost btn-sm" id="skip" type="button">Lewati</button>
        </div>
        <ul class="feed" id="feed" aria-live="off"></ul>
      </section>
      <section class="card result-card" id="res" hidden></section>
      ${extra ? '<div id="extra"></div>' : ''}
      ${!r ? '<p class="parity bad">Versi mesin laga ini tidak tersedia di browser. Muat ulang halaman.</p>' : ''}
      ${r && !match ? '<p class="parity bad">✗ Hasil di browser berbeda dengan server. Laporkan ke admin.</p>' : ''}`;
    if (extra) extra($('#extra', el), f);
    if (!r) return showResult(f, names);

    const ba = $('#ba', el);
    const bb = $('#bb', el);
    const feed = $('#feed', el);
    const pow = $('#pow', el);
    let i = 0;
    let speed = 1;
    let playing = true;

    const setHp = (e) => {
      $('#ha', el).style.width = `${e.ha / 10}%`;
      $('#hb', el).style.width = `${e.hb / 10}%`;
    };
    const anim = (node, cls) => {
      node.classList.remove('lunge', 'hit', 'big', 'down', 'win', 'dodge');
      void node.offsetWidth; // mulai ulang animasi CSS
      if (cls) node.classList.add(cls);
    };
    const show = (e) => {
      setHp(e);
      $('#rd', el).textContent = `R${e.rd}`;
      const atk = e.who === 'a' ? ba : bb;
      const def = e.who === 'a' ? bb : ba;
      if (['hit', 'big', 'kd', 'ko', 'miss'].includes(e.t)) anim(atk, 'lunge');
      if (e.t === 'miss') anim(def, 'dodge');
      if (e.t === 'hit') anim(def, 'hit');
      if (e.t === 'big') anim(def, 'big');
      if (e.t === 'kd' || e.t === 'ko') anim(def, 'down');
      if (['big', 'kd', 'ko'].includes(e.t)) {
        pow.hidden = false;
        pow.className = `pow ${e.who === 'a' ? 'on-b' : 'on-a'}`;
        pow.textContent = e.t === 'ko' ? 'KO!' : e.t === 'kd' ? 'JATUH!' : 'BAM!';
        void pow.offsetWidth;
        pow.classList.add('go');
      }
      const li = document.createElement('li');
      li.textContent = describe(e, names);
      if (['big', 'kd', 'ko'].includes(e.t)) li.className = 'big';
      if (e.t === 'bell' || e.t === 'end') li.className = 'bell';
      feed.prepend(li);
    };
    const finish = () => {
      clearTimeout(timer);
      const last = r.events[r.events.length - 1];
      setHp(last);
      if (r.win !== 'draw') {
        anim(r.win === 'a' ? ba : bb, 'win');
        if (r.how !== 'Angka') anim(r.win === 'a' ? bb : ba, 'down');
      }
      $('#play', el).disabled = true;
      $('#skip', el).disabled = true;
      showResult(f, names, r);
    };
    const next = () => {
      if (!alive) return;
      const e = r.events[i++];
      if (!e) return finish();
      show(e);
      if (playing) timer = setTimeout(next, PACE[e.t] / speed);
    };

    $('#play', el).onclick = (ev) => {
      playing = !playing;
      ev.currentTarget.textContent = playing ? 'Jeda' : 'Putar';
      clearTimeout(timer);
      if (playing) next();
    };
    $('#speed', el).onclick = (ev) => {
      speed = speed === 1 ? 2 : speed === 2 ? 4 : 1;
      ev.currentTarget.textContent = `${speed}×`;
    };
    $('#skip', el).onclick = () => {
      clearTimeout(timer);
      while (i < r.events.length) show(r.events[i++]);
      finish();
    };
    timer = setTimeout(next, 500);
  }

  function showResult(f, names, r = null) {
    const line = resultLine(r ?? { win: f.winner, how: f.how, rd: f.round, scoreA: f.scoreA, scoreB: f.scoreB }, names);
    const mySide = session.me && (f.fighters.a?.username === session.me.username ? 'a' : f.fighters.b?.username === session.me.username ? 'b' : null);
    const myXp = mySide ? f.xp?.[mySide] : 0;
    const box = $('#res', el);
    box.hidden = false;
    box.innerHTML = `
      <div class="result"><b>${esc(line.title)}</b><span>${esc(line.sub)}</span></div>
      ${myXp ? `<p class="xp-gain">+${myXp} XP untukmu</p>` : ''}
      <div class="row">
        <button class="btn btn-ghost" type="button" id="share">Bagikan</button>
        <button class="btn btn-ghost" type="button" id="again">Putar ulang</button>
      </div>`;
    $('#again', box).onclick = () => arenaView(el, id, { title, extra });
    $('#share', box).onclick = async (e) => {
      const btn = e.currentTarget;
      const link = location.href;
      try {
        if (navigator.share) await navigator.share({ title: document.title, url: link });
        else {
          await navigator.clipboard.writeText(link);
          btn.textContent = 'Link tersalin';
        }
      } catch { /* dibatalkan */ }
    };
  }

  return { cleanup: () => { alive = false; clearTimeout(timer); } };
}
