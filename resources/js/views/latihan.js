// Latihan: mini-game 30 detik di sasana. Skor dihitung di browser; server membatasi skor,
// memberi XP (dari skor terbaik hari ini, maks 30/hari), bonus stat (bintang), dan peringkat season.
// Tamu bisa mencoba tanpa hadiah.
//
// Samsak          : garis bergerak di bilah, PUKUL saat di zona emas (PERFECT) / hijau (BAGUS).
// Hindari Pelatih : "!" merah di kepala = NUNDUK, "!" biru di perut = MUNDUR, "?" kuning = tipuan (diam).
// Tahan Serangan  : "!" = TAHAN sampai pukulan mendarat (mulai di akhir = PARRY), "?" = jangan tahan.
import { $, esc, api, STAT_NAMES } from '../ui.js';
import { LOOKS, lookFor } from '../game/index.js';
import { boxerMarkup, boxerNodes, applyPose, POSES, lerpPose } from '../game/boxer.js';
import { createSfx } from '../game/sfx.js';
import { openLogin } from '../auth.js';

const META = {
  samsak: {
    a: 'LATIHAN', b: 'SAMSAK', title: 'Samsak', sub: 'Melatih Pukulan', icon: '🥊', rate: 'AKURASI',
    how: [
      'Garis putih bergerak bolak-balik di bilah.',
      'Ketuk <b>PUKUL</b>, samsak, atau <kbd>Spasi</kbd> saat garis di zona <b class="c-gold">emas (PERFECT)</b> atau <b class="c-green">hijau (BAGUS)</b>.',
      '<b>TENDANG</b> (<kbd>K</kbd>) memberi poin lebih besar, tapi jedanya lebih lama dan meleset lebih mahal.',
      'Combo menggandakan poin sampai <b>2×</b>, tapi garis makin cepat.',
      'Meleset: combo hangus dan skor berkurang.',
    ],
  },
  hindari: {
    a: 'HINDARI', b: 'PELATIH', title: 'Hindari Pelatih', sub: 'Melatih Kecepatan', icon: '💨', rate: 'SELAMAT',
    how: [
      'Pelatih memberi aba-aba sebelum memukul. Bereaksi sebelum bilah oranye habis.',
      '<b class="c-red">!</b> merah di kepala → <b>NUNDUK</b> (<kbd>↓</kbd>).',
      '<b class="c-blue">!</b> biru di perut → <b>MUNDUR</b> (<kbd>←</kbd>).',
      '<b class="c-green">!</b> hijau di kaki (low kick) → <b>CHECK</b>, angkat lutut (<kbd>↑</kbd>).',
      '<b class="c-gold">?</b> kuning = tipuan. Jangan tekan apa-apa!',
    ],
  },
  tahan: {
    a: 'TAHAN', b: 'SERANGAN', title: 'Tahan Serangan', sub: 'Melatih Pertahanan', icon: '🛡️', rate: 'TERTAHAN', badge: 'BARU',
    how: [
      '<b class="c-red">!</b> merah → tekan dan <b>TAHAN</b> (tombol, layar, atau <kbd>Spasi</kbd>) sampai pukulan mendarat, lalu lepas.',
      'Mulai menahan di detik terakhir aba-aba = <b class="c-gold">PARRY!</b> (poin penuh).',
      'Menahan terus dari sebelum aba-aba = <b>GUARD JEBOL</b>.',
      '<b class="c-gold">?</b> kuning = tipuan. Jangan tahan! Combo tinggi = pelatih makin cepat.',
    ],
  },
  lompat: { title: 'Lompat Tali', sub: 'Melatih Stamina', icon: '🪢' },
  situp: { title: 'Sit-up', sub: 'Melatih Ketahanan', icon: '🔁' },
};
const ORDER = ['samsak', 'hindari', 'tahan', 'lompat', 'situp'];
const TRAINER = { skin: 'sawo', hair: 'cepak', hairColor: 'uban', face: 'serius', beard: 'kumis', gloves: 'putih', trunks: 'putih', extra: 'tidak' };
const MAX_TAPS = 15 * 30;
const COVER = { torso: 8, head: 10, fu: 58, ff: 160, bu: 48, bf: 165, crouch: 9, spread: 1.05 };
const WIND_HEAD = { torso: -8, head: -2, fu: 40, ff: 120, bu: 30, bf: 150, crouch: 6, spread: 1.1 };
const WIND_BODY = { torso: 6, head: 4, fu: 20, ff: 110, bu: 16, bf: 150, crouch: 14, spread: 1.2 };

const fmt = (n) => Number(n || 0).toLocaleString('id-ID');
const rand = (a, b) => a + Math.random() * (b - a);
const mult = (combo) => 1 + Math.min(Math.max(combo - 1, 0), 10) / 10;
const starsFor = (cfg, score) => (cfg.stars ?? []).filter((s) => score >= s).length;
const starRow = (n, max = 3) => Array.from({ length: max }, (_, i) => `<i class="${i < n ? 'on' : ''}">★</i>`).join('');

export function latihanView(el) {
  let alive = true;
  let info = null;
  let stop = () => {};
  document.title = `Latihan · ${window.GAME?.brand?.name ?? ''}`;

  const load = (then = menu) => api('/api/training', { cache: 'no-store' })
    .then(({ data }) => { info = data; if (alive) then(); })
    .catch((e) => { if (alive) el.innerHTML = `<p class="banner bad">${esc(e.message)}</p>`; });

  // ------------------------------------------------------------------ daftar latihan
  function menu() {
    stop();
    window.scrollTo(0, 0);
    const me = info.me;
    const bonus = Object.entries(info.bonus_today ?? {}).map(([s, b]) => `<b>+${b} ${esc(STAT_NAMES[s])}</b>`).join(', ');
    const note = !me ? 'Mode coba: skor tidak disimpan. Masuk agar XP, rekor, dan bonus stat tercatat.'
      : info.xp_today > 0 || bonus ? `Latihan hari ini: <b>${info.xp_today}/${info.xp_cap} XP</b>${bonus ? ` · bonus Malam Gelanggang ${bonus}` : ''}.`
        : `Belum latihan hari ini. Skor terbaikmu jadi XP (maks ${info.xp_cap}) &amp; bonus atribut Malam Gelanggang.`;
    el.innerHTML = `
      <section class="hero"><h1>LATI<em>HAN</em></h1><p>Pilih latihan, main 30 detik, kejar rekormu, lalu share!</p></section>
      <p class="tnote">${note}</p>
      ${me ? '' : '<button class="btn btn-accent btn-block" id="login" type="button">Masuk / daftar</button>'}
      <div class="tgrid">${ORDER.filter((k) => info.games[k]).map((k) => {
        const m = META[k];
        const g = info.games[k];
        const line = g.soon ? 'Segera' : !me ? 'Coba gratis!' : g.best > 0 ? `Rekor lu: <b>${fmt(g.best)}</b>${g.rank ? ` · #${g.rank}` : ''}` : 'Belum main · <b>Coba!</b>';
        return `<button class="tcard${g.soon ? ' soon' : ''}" type="button" data-g="${k}" ${g.soon ? 'disabled aria-disabled="true"' : ''}>
          ${m.badge && !g.soon ? `<span class="tbadge">${m.badge}</span>` : ''}
          <span class="tc-ic" aria-hidden="true">${m.icon}</span>
          <b>${esc(m.title.toUpperCase())}</b><small>${esc(m.sub)}</small>
          <span class="tc-line">${line}</span>
        </button>`;
      }).join('')}</div>
      <section class="card ttop"><h3>TOP 100 · SEASON ${info.season}</h3><p class="sub">Skor terbaik tiap pemain musim ini.</p>
        <div class="row">${ORDER.filter((k) => info.games[k] && !info.games[k].soon).map((k) => `<button class="btn btn-ghost btn-sm" type="button" data-top="${k}">${esc(META[k].title)}</button>`).join('')}</div></section>`;
    $('#login', el)?.addEventListener('click', () => openLogin());
    el.querySelectorAll('[data-g]:not([disabled])').forEach((b) => b.addEventListener('click', () => game(b.dataset.g)));
    el.querySelectorAll('[data-top]').forEach((b) => b.addEventListener('click', () => top(b.dataset.top)));
  }

  // ------------------------------------------------------------------ top 100
  function top(key, back = menu) {
    stop();
    window.scrollTo(0, 0);
    el.innerHTML = `<button class="sub back linkish" type="button" id="back">← Kembali</button>
      <section class="hero"><span class="eyebrow">Season ${info.season}</span><h1>TOP 100 <em>${esc(META[key].title.toUpperCase())}</em></h1></section>
      <div id="list"><p class="loading">Memuat…</p></div>`;
    $('#back', el).onclick = () => back();
    api(`/api/training/${key}/top`).then(({ data }) => {
      if (!alive) return;
      $('#list', el).innerHTML = data.top.length ? `
        ${data.rank ? `<p class="tnote">Peringkat lu: <b>#${data.rank}</b> dari ${fmt(data.total)}</p>` : ''}
        <ol class="tboard">${data.top.map(row).join('')}</ol>` : '<p class="empty">Belum ada skor musim ini. Jadi yang pertama!</p>';
    }).catch((e) => { if (alive) $('#list', el).innerHTML = `<p class="banner bad">${esc(e.message)}</p>`; });
  }

  const row = (r) => `<li class="${r.me ? 'me' : ''}"><span class="rk">#${r.rank}</span><a href="/${esc(r.username)}" data-link>@${esc(r.username)}</a><b>${fmt(r.best)}</b></li>`;

  // ------------------------------------------------------------------ permainan
  function game(key) {
    stop();
    window.scrollTo(0, 0);
    const m = META[key];
    const cfg = info.games[key];
    const brand = (window.GAME?.brand?.name ?? 'Boxing').toUpperCase();
    const look = info.me?.look ?? lookFor('tamu');
    const withTrainer = key !== 'samsak';
    const PX = withTrainer ? 148 : 182;
    const TX = 252;
    const FY = 224;

    el.innerHTML = `
      <div class="tg-head"><button class="sub back linkish" type="button" id="back">← Latihan</button>
        <button class="snd" id="snd" type="button" aria-pressed="false" title="Suara">🔊</button></div>
      <h1 class="tg-title">${m.a} <em>${m.b}</em></h1>
      <div class="tg-stats"><div><small>SKOR</small><b id="sc">0</b></div><div><small>COMBO</small><b id="cb">0</b></div><div><small>DETIK</small><b id="tm">30</b></div></div>
      <div class="tg-time"><i id="tb"></i></div>
      <div class="tg-scene" id="scene">
        <svg viewBox="0 0 400 250" aria-hidden="true">
          <defs>
            <linearGradient id="tgWall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1b1e27"/><stop offset="1" stop-color="#262a35"/></linearGradient>
            <linearGradient id="tgFloor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8a5a32"/><stop offset="1" stop-color="#5a3920"/></linearGradient>
            <radialGradient id="tgSpot" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fff3c4" stop-opacity=".28"/><stop offset="1" stop-color="#fff3c4" stop-opacity="0"/></radialGradient>
            <linearGradient id="tgCone" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff6d8" stop-opacity=".16"/><stop offset="1" stop-color="#fff6d8" stop-opacity="0"/></linearGradient>
            <linearGradient id="tgBag" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#7a1f1a"/><stop offset=".45" stop-color="#cf3f30"/><stop offset="1" stop-color="#7f211b"/></linearGradient>
          </defs>
          <rect width="400" height="196" fill="url(#tgWall)"/>
          ${[40, 120, 280, 360].map((x) => `<rect x="${x - 22}" y="70" width="44" height="58" rx="3" fill="#ffffff08" stroke="#ffffff10"/>`).join('')}
          <text x="200" y="52" text-anchor="middle" class="tg-wall" textLength="340" lengthAdjust="spacingAndGlyphs">PADEPOKAN ${esc(brand)}</text>
          <rect y="128" width="400" height="5" fill="#00000030"/>
          <rect y="196" width="400" height="54" fill="url(#tgFloor)"/>
          ${Array.from({ length: 11 }, (_, i) => `<line x1="${200 + (i - 5) * 30}" y1="196" x2="${200 + (i - 5) * 80}" y2="250" stroke="#00000033"/>`).join('')}
          <line x1="0" y1="214" x2="400" y2="214" stroke="#00000022"/><line x1="0" y1="234" x2="400" y2="234" stroke="#00000022"/>
          <polygon points="165,0 235,0 330,232 70,232" fill="url(#tgCone)"/>
          <ellipse cx="200" cy="226" rx="160" ry="26" fill="url(#tgSpot)"/>
          ${withTrainer ? `<g transform="translate(${TX} ${FY}) scale(.92)"><g id="tr">${boxerMarkup(LOOKS, TRAINER, 'tr')}</g></g>
            <text x="${TX}" y="${FY + 20}" text-anchor="middle" class="tg-tag">PELATIH</text>` : `
            <line x1="262" y1="0" x2="262" y2="10" stroke="#888" stroke-width="3"/>
            <g id="bag"><line x1="262" y1="10" x2="262" y2="56" stroke="#9a9a9a" stroke-width="3" stroke-dasharray="5 3"/>
              <rect x="236" y="56" width="52" height="132" rx="22" fill="url(#tgBag)" stroke="#14161b" stroke-width="3"/>
              <rect x="236" y="96" width="52" height="12" fill="#ffb627" stroke="#14161b" stroke-width="2"/>
              <rect x="236" y="150" width="52" height="7" fill="#00000030"/></g>`}
          <g transform="translate(${PX} ${FY}) scale(.92)"><g id="pl">${boxerMarkup(LOOKS, look, 'pl')}</g></g>
        </svg>
        <div class="tg-win" id="win" hidden><i></i></div>
        <div class="tg-mark" id="mark" hidden></div>
        <div class="tg-fx" id="fx" aria-live="polite"></div>
        <div class="tg-count" id="count" hidden></div>
      </div>
      <div class="tg-ctrl" id="ctrl">${controls(key)}</div>
      <p class="tg-info" id="info">${infoLine(key)}</p>
      <button class="btn btn-accent btn-block tg-go" id="go" type="button">MULAI</button>
      <section class="card tg-how"><h3>CARA MAIN</h3><ul>${m.how.map((h) => `<li>${h}</li>`).join('')}</ul>
        <p class="sub">${starsHint(cfg, key)}</p></section>`;

    // --- aktor
    const svg = $('#scene svg', el);
    const actor = (id, facing) => ({ n: boxerNodes(svg, id), facing, cur: { ...POSES.guard }, from: null, to: null, t0: 0, a: 0.08, h: 0.12, o: 0.18 });
    const pl = actor('pl', 1);
    const tr = withTrainer ? actor('tr', -1) : null;
    const act = (A, pose, a = 0.07, h = 0.14, o = 0.2) => Object.assign(A, { from: { ...A.cur }, to: pose, t0: now(), a, h, o });
    const pose = (A, t) => {
      const bob = { ...POSES.guard, y: Math.sin(t * 5 + (A.facing > 0 ? 0 : 1.7)) * 1.4 };
      let p = bob;
      if (A.to) {
        const e = Math.max(0, t - A.t0);
        if (e < A.a) p = lerpPose(A.from, A.to, e / A.a);
        else if (e < A.a + A.h) p = A.to;
        else if (A.o > 0 && e < A.a + A.h + A.o) p = lerpPose(A.to, bob, (e - A.a - A.h) / A.o);
        else if (A.o < 0) p = A.to; // tahan pose sampai diganti
        else A.to = null;
      }
      A.cur = p;
      applyPose(A.n, p, A.facing);
    };

    // --- suara
    let sfx = null;
    let muted = false;
    try { muted = localStorage.getItem('wb.sound') === '0'; } catch { /* diblokir */ }
    const snd = $('#snd', el);
    const paintSnd = () => { snd.textContent = muted ? '🔇' : '🔊'; snd.setAttribute('aria-pressed', String(muted)); snd.setAttribute('aria-label', muted ? 'Nyalakan suara' : 'Matikan suara'); };
    paintSnd();
    snd.onclick = () => {
      muted = !muted;
      try { localStorage.setItem('wb.sound', muted ? '0' : '1'); } catch { /* abaikan */ }
      sfx?.setMuted(muted);
      paintSnd();
    };
    const S = new Proxy({}, { get: (_, k) => (...args) => { if (sfx && !muted) sfx[k]?.(...args); } });

    // --- keadaan permainan
    const st = { phase: 'idle', t0: 0, score: 0, combo: 0, bestCombo: 0, taps: 0, good: 0, total: 0, token: null };
    let raf = 0;
    const now = () => performance.now() / 1000;
    const paint = () => {
      $('#sc', el).textContent = fmt(st.score);
      $('#cb', el).textContent = st.combo > 1 ? `${st.combo}×` : String(st.combo);
    };
    const fx = (text, cls = '', sub = '') => {
      const box = $('#fx', el);
      const d = document.createElement('div');
      d.className = `fx ${cls}`;
      d.innerHTML = `${esc(text)}${sub ? `<small>${esc(sub)}</small>` : ''}`;
      box.replaceChildren(d);
      setTimeout(() => d.remove(), 750);
    };
    const award = (base, label, cls) => {
      st.combo += 1;
      st.bestCombo = Math.max(st.bestCombo, st.combo);
      st.good += 1;
      st.total += 1;
      const pts = Math.round(base * mult(st.combo));
      st.score += pts;
      fx(label, cls, `+${pts}${st.combo > 1 ? ` · combo ${st.combo}` : ''}`);
      S.ding(st.combo);
      paint();
    };
    const penalty = (label, cls = 'bad', minus = 30) => {
      st.combo = 0;
      st.total += 1;
      st.score = Math.max(0, st.score - minus);
      fx(label, cls, minus ? `-${minus}` : '');
      S.buzz();
      paint();
    };
    const tap = () => { st.taps += 1; };

    const G = { samsak: samsakGame, hindari: hindariGame, tahan: tahanGame }[key]();

    // --- loop
    const frame = () => {
      if (!alive) return;
      const t = now();
      if (st.phase === 'play') {
        const left = Math.max(0, 30 - (t - st.t0));
        $('#tm', el).textContent = String(Math.ceil(left));
        $('#tb', el).style.width = `${(left / 30) * 100}%`;
        G.frame(t, t - st.t0);
        if (left <= 0) end();
      } else {
        G.idle?.(t);
      }
      pose(pl, t);
      if (tr) pose(tr, t);
      raf = requestAnimationFrame(frame);
    };
    raf = requestAnimationFrame(frame);

    async function begin() {
      if (st.phase === 'count' || st.phase === 'play') return;
      if (!sfx) { sfx = createSfx(); sfx.setMuted(muted); }
      sfx.resume();
      const go = $('#go', el);
      go.disabled = true;
      Object.assign(st, { score: 0, combo: 0, bestCombo: 0, taps: 0, good: 0, total: 0, token: null });
      paint();
      if (info.me) {
        try {
          st.token = (await api('/api/training/start', { method: 'POST', body: JSON.stringify({ game: key }) })).data.token;
        } catch (e) {
          go.disabled = false;
          $('#info', el).innerHTML = `<span class="bad">${esc(e.message)}</span>`;
          return;
        }
      }
      if (!alive) return;
      st.phase = 'count';
      go.textContent = 'BERSIAP…';
      const c = $('#count', el);
      c.hidden = false;
      for (const s of ['3', '2', '1', 'GO!']) {
        c.textContent = s;
        c.classList.remove('pop');
        void c.offsetWidth;
        c.classList.add('pop');
        if (s === 'GO!') S.bell(1);
        else S.tick();
        await new Promise((r) => setTimeout(r, 700));
        if (!alive) return;
      }
      c.hidden = true;
      go.textContent = 'SEDANG LATIHAN…';
      st.phase = 'play';
      st.t0 = now();
      G.start(st.t0);
    }

    async function end() {
      st.phase = 'done';
      G.stop?.();
      $('#tm', el).textContent = '0';
      $('#tb', el).style.width = '0%';
      fx('WAKTU HABIS!', 'big');
      S.bell(2);
      const local = { game: key, stat: cfg.stat, score: st.score, stars: starsFor(cfg, st.score) };
      let res = null;
      let err = null;
      await new Promise((r) => setTimeout(r, 900));
      if (st.token) {
        try {
          res = (await api('/api/training/finish', { method: 'POST', body: JSON.stringify({ token: st.token, score: st.score, taps: Math.min(st.taps, MAX_TAPS) }) })).data;
        } catch (e) {
          err = e.message;
        }
      }
      if (!alive) return;
      result(key, { ...local, ...(res ?? {}) }, { bestCombo: st.bestCombo, rate: st.total ? Math.round((st.good / st.total) * 100) : 0, err, saved: !!res });
    }

    // Input umum: tombol keyboard. Tiap game memasang tombolnya sendiri.
    const keydown = (e) => {
      if (e.repeat || e.target.closest?.('input, textarea')) return;
      if (st.phase === 'idle' && e.key === 'Enter') { begin(); return; }
      if (G.key?.(e, true)) e.preventDefault();
    };
    const keyup = (e) => { if (G.key?.(e, false)) e.preventDefault(); };
    window.addEventListener('keydown', keydown);
    window.addEventListener('keyup', keyup);

    $('#go', el).onclick = begin;
    $('#back', el).onclick = () => load();
    stop = () => {
      cancelAnimationFrame(raf);
      window.removeEventListener('keydown', keydown);
      window.removeEventListener('keyup', keyup);
      G.stop?.();
      sfx?.close();
      sfx = null;
      st.phase = 'done';
      stop = () => {};
    };

    // ---------------------------------------------------------------- SAMSAK
    function samsakGame() {
      const needle = $('#needle', el);
      const good = $('#zgood', el);
      const perfect = $('#zperf', el);
      const bag = $('#bag', el);
      let p = 0.1;
      let dir = 1;
      let c = 0.6;
      let last = 0;
      let swing = 0;
      let vel = 0;
      let punches = 0;
      let lastT = now();
      const halfGood = () => Math.max(0.07, 0.12 - st.combo * 0.003);
      const HALF_PERF = 0.04;
      const placeZone = () => {
        do c = rand(0.15, 0.85); while (Math.abs(c - p) < 0.22);
        good.style.left = `${(c - halfGood()) * 100}%`;
        good.style.width = `${halfGood() * 200}%`;
        perfect.style.left = `${(c - HALF_PERF) * 100}%`;
        perfect.style.width = `${HALF_PERF * 200}%`;
      };
      placeZone();
      const move = (t) => {
        const dt = Math.min(0.05, t - lastT);
        lastT = t;
        const speed = st.phase === 'play' ? 0.9 + Math.min(st.combo, 15) * 0.07 : 0.5;
        p += dir * speed * dt;
        if (p > 1) { p = 2 - p; dir = -1; }
        if (p < 0) { p = -p; dir = 1; }
        needle.style.left = `${p * 100}%`;
        // Samsak berayun (pegas teredam), poros di langit-langit.
        vel += (-60 * swing - 4 * vel) * dt;
        swing += vel * dt;
        bag?.setAttribute('transform', `rotate(${swing.toFixed(2)} 262 0)`);
      };
      // kick = tendangan: poin lebih besar, jeda lebih lama, meleset lebih mahal.
      const hit = (kick = false) => {
        if (st.phase !== 'play') return;
        const t = now();
        if (t - last < (kick ? 0.45 : 0.3)) return;
        last = t;
        tap();
        punches += 1;
        const d = Math.abs(p - c);
        const moves = [POSES.jab, POSES.straight, POSES.hook, POSES.straight];
        const kicks = [POSES.kick_body, POSES.kick_low, POSES.kick_body, POSES.kick_high];
        if (d <= HALF_PERF) {
          act(pl, kick ? kicks[punches % kicks.length] : punches % 3 === 0 ? POSES.upper : POSES.hook, kick ? 0.09 : 0.06, 0.12, 0.2);
          vel += kick ? 95 : 70;
          S.punch(true);
          award(kick ? 140 : 100, kick ? 'TENDANGAN PERFECT!' : 'PERFECT!', 'gold');
        } else if (d <= halfGood()) {
          act(pl, kick ? kicks[punches % kicks.length] : moves[punches % moves.length], kick ? 0.09 : 0.06, 0.1, 0.18);
          vel += kick ? 55 : 40;
          S.punch(kick);
          award(kick ? 70 : 50, 'BAGUS!', 'green');
        } else {
          act(pl, kick ? POSES.kick_body : POSES.jab, 0.06, 0.08, 0.2);
          vel += 8;
          S.whoosh();
          penalty('MELESET', 'bad', kick ? 60 : 40);
        }
        placeZone();
      };
      $('#pukul', el).addEventListener('pointerdown', (e) => { e.preventDefault(); hit(false); });
      $('#tendang', el).addEventListener('pointerdown', (e) => { e.preventDefault(); hit(true); });
      $('#scene', el).addEventListener('pointerdown', () => hit(false));
      return {
        start: () => { lastT = now(); placeZone(); },
        idle: move,
        frame: (t) => move(t),
        key: (e, down) => {
          const kick = e.key === 'k' || e.key === 'K' || e.key === 'ArrowUp';
          if (e.code !== 'Space' && !kick) return false;
          if (down) hit(kick);
          return st.phase === 'play';
        },
      };
    }

    // ------------------------------------------------- serangan pelatih (hindari & tahan)
    function attacks(opt) {
      const win = $('#win', el);
      const bar = win.firstElementChild;
      const mark = $('#mark', el);
      let a = null; // serangan aktif
      let next = 0;
      const show = (type) => {
        mark.hidden = false;
        mark.className = `tg-mark ${type}`;
        mark.textContent = type === 'feint' ? '?' : '!';
        const y = type === 'leg' ? (FY - 24) : type === 'body' ? (FY - 52) : (FY - 128);
        mark.style.left = `${((PX + 30) / 400) * 100}%`;
        mark.style.top = `${(y / 250) * 100}%`;
        win.hidden = false;
      };
      const hide = () => { mark.hidden = true; win.hidden = true; };
      const schedule = (t) => { next = t + rand(0.45, 0.95) * Math.max(0.6, 1 - st.combo * 0.02); a = null; hide(); };
      return {
        get active() { return a; },
        start(t) { schedule(t - 0.3); },
        stop() { hide(); a = null; },
        frame(t) {
          if (!a) {
            if (t < next) return;
            const r = Math.random();
            const real = (r - opt.feint) / (1 - opt.feint);
            const type = r < opt.feint ? 'feint' : opt.leg && real < 0.3 ? 'leg' : opt.body && real < (opt.leg ? 0.65 : 0.5) ? 'body' : 'head';
            const w = Math.max(opt.minW, opt.w - Math.min(st.combo, 12) * opt.step);
            a = { type, t0: t, w, done: false };
            show(type);
            act(tr, type === 'leg' ? { ...WIND_BODY, lt: 24, ls: 30 } : type === 'body' ? WIND_BODY : WIND_HEAD, w * 0.8, 10, -1);
            opt.onTele?.(a, t);
            return;
          }
          const left = 1 - (t - a.t0) / a.w;
          bar.style.width = `${Math.max(0, left) * 100}%`;
          if (t >= a.t0 + a.w && !a.done) {
            a.done = true;
            opt.onLand(a, t);
            schedule(t);
          }
        },
        resolve(t) { if (a) { a.done = true; schedule(t); } },
      };
    }

    const throwPunch = (type, landed) => {
      act(tr, type === 'leg' ? POSES.kick_low : type === 'body' ? (Math.random() < 0.5 ? POSES.body : POSES.kick_body) : (Math.random() < 0.5 ? POSES.straight : POSES.hook), 0.06, 0.14, 0.22);
      if (!landed) S.whoosh();
    };
    const feintBack = () => act(tr, { ...POSES.guard, torso: -6, x: 4 }, 0.08, 0.06, 0.2);

    // ---------------------------------------------------------------- HINDARI PELATIH
    function hindariGame() {
      const A = attacks({
        feint: 0.2, body: true, leg: true, w: 1.05, minW: 0.5, step: 0.045,
        onLand(a) {
          if (a.type === 'feint') {
            feintBack();
            award(50, 'TENANG', 'green');
            return;
          }
          throwPunch(a.type, true);
          act(pl, a.type === 'leg' ? POSES.wobble : a.type === 'body' ? { ...POSES.hit, torso: 18, crouch: 10 } : POSES.hit, 0.05, 0.16, 0.25);
          S.punch(a.type === 'head');
          penalty('KENA!', 'bad', 30);
        },
      });
      const press = (which) => {
        if (st.phase !== 'play') return;
        const a = A.active;
        if (!a || a.done) return; // jeda antar serangan: diabaikan
        tap();
        const t = now();
        const move = which === 'duck' ? POSES.duck : which === 'check' ? POSES.check : { ...POSES.dodge, x: -18 };
        const want = { head: 'duck', body: 'back', leg: 'check' }[a.type];
        if (a.type === 'feint') {
          act(pl, move, 0.06, 0.16, 0.2);
          feintBack();
          penalty('KETIPU!', 'gold-bad', 30);
        } else if (which === want) {
          act(pl, which === 'back' ? { ...POSES.dodge, x: -20 } : move, 0.05, 0.22, 0.2);
          throwPunch(a.type, which === 'check');
          if (which === 'check') S.thud();
          award(100, which === 'check' ? 'CHECK!' : 'AMAN!', 'green');
        } else {
          act(pl, move, 0.05, 0.1, 0.2);
          throwPunch(a.type, true);
          setTimeout(() => act(pl, POSES.hit, 0.04, 0.16, 0.25), 60);
          S.punch(true);
          penalty('KENA!', 'bad', 30);
        }
        A.resolve(t);
      };
      const bind = (id, which) => $(id, el).addEventListener('pointerdown', (e) => { e.preventDefault(); press(which); });
      bind('#duck', 'duck');
      bind('#back2', 'back');
      bind('#check', 'check');
      return {
        start: (t) => A.start(t),
        stop: () => A.stop(),
        frame: (t) => A.frame(t),
        key: (e, down) => {
          const which = e.key === 'ArrowDown' ? 'duck' : e.key === 'ArrowLeft' ? 'back' : e.key === 'ArrowUp' ? 'check' : null;
          if (!which) return false;
          if (down) press(which);
          return st.phase === 'play';
        },
      };
    }

    // ---------------------------------------------------------------- TAHAN SERANGAN
    function tahanGame() {
      const btn = $('#hold', el);
      let holding = false;
      let holdT = 0;
      const A = attacks({
        feint: 0.22, body: false, w: 1.25, minW: 0.6, step: 0.05,
        onLand(a) {
          const during = holding && holdT >= a.t0;
          if (a.type === 'feint') {
            feintBack();
            if (holding) penalty('KETIPU!', 'gold-bad', 30);
            else award(50, 'TENANG', 'green');
            return;
          }
          throwPunch('head', true);
          if (holding && !during) {
            act(pl, { ...POSES.hit, fu: 60, ff: 150 }, 0.04, 0.16, 0.25);
            S.punch(true);
            penalty('GUARD JEBOL', 'bad', 20);
          } else if (during && holdT >= a.t0 + a.w * 0.65) {
            act(pl, { ...COVER, x: -4 }, 0.03, 0.12, 0.2);
            S.thud();
            award(120, 'PARRY!', 'gold');
          } else if (during) {
            act(pl, { ...COVER, x: -10, torso: 0 }, 0.03, 0.14, 0.22);
            S.thud();
            award(80, 'TAHAN!', 'green');
          } else {
            act(pl, POSES.hit, 0.04, 0.16, 0.25);
            S.punch(true);
            penalty('KENA!', 'bad', 30);
          }
        },
      });
      const down = () => {
        if (st.phase !== 'play' || holding) return;
        holding = true;
        holdT = now();
        tap();
        btn.classList.add('held');
        act(pl, COVER, 0.06, 10, -1);
      };
      const up = () => {
        if (!holding) return;
        holding = false;
        btn.classList.remove('held');
        if (pl.to === COVER) act(pl, COVER, 0, 0, 0.15);
      };
      const ptrDown = (e) => { e.preventDefault(); e.currentTarget.setPointerCapture?.(e.pointerId); down(); };
      btn.addEventListener('pointerdown', ptrDown);
      $('#scene', el).addEventListener('pointerdown', ptrDown);
      for (const ev of ['pointerup', 'pointercancel']) window.addEventListener(ev, up);
      btn.addEventListener('contextmenu', (e) => e.preventDefault());
      return {
        start: (t) => A.start(t),
        stop: () => { A.stop(); up(); for (const ev of ['pointerup', 'pointercancel']) window.removeEventListener(ev, up); },
        frame: (t) => A.frame(t),
        key: (e, isDown) => {
          if (e.code !== 'Space') return false;
          if (isDown) down();
          else up();
          return st.phase === 'play';
        },
      };
    }
  }

  function controls(key) {
    if (key === 'samsak') {
      return `<div class="tg-bar" aria-hidden="true"><span class="z good" id="zgood"></span><span class="z perf" id="zperf"></span><i id="needle"></i></div>
        <div class="tg-two"><button class="btn tg-btn hot" id="pukul" type="button">PUKUL<small>cepat · Spasi</small></button>
        <button class="btn tg-btn cool" id="tendang" type="button">TENDANG<small>poin besar · K</small></button></div>`;
    }
    if (key === 'hindari') {
      return `<div class="tg-three">
        <button class="btn tg-btn cool" id="duck" type="button">↓ NUNDUK<small>serangan kepala</small></button>
        <button class="btn tg-btn hot" id="back2" type="button">← MUNDUR<small>serangan perut</small></button>
        <button class="btn tg-btn leg" id="check" type="button">↑ CHECK<small>low kick</small></button></div>`;
    }
    return '<button class="btn tg-btn cool tg-hold" id="hold" type="button">🛡️ TAHAN<small>tekan &amp; tahan</small></button>';
  }

  function infoLine(key) {
    if (!info.me) return 'Mode coba: skor tidak disimpan.';
    const g = info.games[key];
    return g.best > 0 ? `Rekor lu Season ${info.season}: <b>${fmt(g.best)}</b>${g.rank ? ` · #${g.rank}` : ''}` : `Belum ada rekor Season ${info.season}. Ayo cetak!`;
  }

  function starsHint(cfg, key) {
    const [a, b, c] = cfg.stars ?? [];
    return `★ ${fmt(a)} · ★★ ${fmt(b)} · ★★★ ${fmt(c)}. Bintang = bonus ${esc(STAT_NAMES[info.games[key].stat])} +1 s/d +3 untuk Malam Gelanggang hari ini.`;
  }

  // ------------------------------------------------------------------ hasil
  function result(key, r, x) {
    stop();
    window.scrollTo(0, 0);
    const m = META[key];
    const nextKey = ORDER.find((k, i) => i > ORDER.indexOf(key) && info.games[k] && !info.games[k].soon)
      ?? ORDER.find((k) => k !== key && info.games[k] && !info.games[k].soon);
    const statName = STAT_NAMES[r.stat] ?? r.stat;
    const lv = r.level;
    const board = r.top ? [...r.top] : [];
    const around = (r.around ?? []).filter((a) => !board.some((b) => b.rank === a.rank));

    el.innerHTML = `
      <section class="tres">
        <span class="eyebrow">${esc(m.title)}</span>
        <h1 class="tg-title">LATIHAN <em>SELESAI!</em></h1>
        ${r.record && r.score > 0 ? '<span class="tbadge big">REKOR BARU!</span>' : ''}
        <div class="tstars" aria-label="${r.stars} bintang">${starRow(r.stars)}</div>
        <div class="tg-stats"><div><small>SKOR</small><b>${fmt(r.score)}</b></div><div><small>COMBO TERBAIK</small><b>${x.bestCombo}</b></div><div><small>${m.rate}</small><b>${x.rate}%</b></div></div>
        ${x.err ? `<p class="banner bad">${esc(x.err)}</p>` : ''}
        ${x.saved ? `
          <div class="card txp">
            <div class="txp-top"><b class="xp-gain">+${r.xp + r.streakXp} XP</b>${r.levelUp ? '<span class="tbadge">NAIK LEVEL!</span>' : ''}</div>
            <p class="sub">Latihan hari ini <b>${r.xpToday}/${r.xpCap}</b> XP${r.streakXp ? ` · streak ${r.streakDays} hari <b>+${r.streakXp}</b>` : ''}</p>
            ${r.xp === 0 && r.xpToday >= r.xpCap ? '<p class="hint">Jatah XP latihan hari ini sudah penuh. Rekor dan bonus stat tetap dihitung.</p>'
              : r.xp === 0 ? '<p class="hint">XP dihitung dari skor terbaik hari ini. Kalahkan skor terbaikmu hari ini untuk XP tambahan.</p>' : ''}
            ${lv ? `<div class="lvbar"><span>Lv ${lv.lv}</span><div class="bar"><i style="width:${lv.need ? Math.round((lv.into / lv.need) * 100) : 100}%"></i></div><small>${lv.need ? `${lv.into}/${lv.need}` : 'MAKS'}</small></div>` : ''}
          </div>
          <p class="tnote">${r.bonus > 0 ? `Malam Gelanggang hari ini: <b>+${r.bonus} ${esc(statName)}</b>${(r.bonusToday?.[r.stat] ?? 0) > r.bonus ? ` (aktif: +${r.bonusToday[r.stat]})` : ''}` : `Raih ★ (skor ${fmt(info.games[key].stars?.[0])}) untuk bonus ${esc(statName)} di Malam Gelanggang hari ini.`}</p>
          ${r.rank ? `<section class="card"><h3>Peringkat #${r.rank} dari ${fmt(r.total)}</h3>
            <ol class="tboard">${board.map(row).join('')}${around.length ? `<li class="gap">…</li>${around.map(row).join('')}` : ''}</ol></section>` : ''}`
          : `<p class="tnote">${info.me ? 'Skor belum tersimpan.' : 'Mode coba: masuk agar XP, rekor, dan bonus stat tersimpan.'} ${r.stars ? `Skor ini dapat ${r.stars} bintang!` : ''}</p>
             ${info.me ? '' : '<button class="btn btn-accent btn-block" id="login" type="button">Masuk / daftar</button>'}`}
        <div class="row tres-btns">
          <button class="btn btn-ghost" type="button" id="topBtn">Lihat Top 100</button>
          <button class="btn btn-ghost" type="button" id="share">Share skor</button>
        </div>
        <div class="row tres-btns">
          <button class="btn btn-accent" type="button" id="again">Coba lagi</button>
          ${nextKey ? `<button class="btn btn-outline" type="button" id="next">Coba ${esc(META[nextKey].title)}</button>` : ''}
        </div>
        <button class="sub back linkish" type="button" id="back">← Semua latihan</button>
      </section>`;

    const refreshThen = (fn) => load(fn);
    $('#again', el).onclick = () => refreshThen(() => game(key));
    $('#next', el)?.addEventListener('click', () => refreshThen(() => game(nextKey)));
    $('#back', el).onclick = () => refreshThen(menu);
    $('#topBtn', el).onclick = () => top(key, () => result(key, r, x));
    $('#login', el)?.addEventListener('click', () => openLogin());
    $('#share', el).onclick = async (e) => {
      const btn = e.currentTarget;
      const brand = window.GAME?.brand?.name ?? '';
      const text = `Skor ${m.title} gue ${fmt(r.score)} ${'⭐'.repeat(r.stars)}${r.rank ? ` (#${r.rank} Season ${info.season})` : ''} di ${brand}! Bisa kalahin?`;
      const url = `${location.origin}/latihan`;
      try {
        if (navigator.share) await navigator.share({ title: `Latihan ${m.title}`, text, url });
        else {
          await navigator.clipboard.writeText(`${text} ${url}`);
          btn.textContent = 'Tersalin!';
        }
      } catch { /* dibatalkan */ }
    };
  }

  load();
  return { cleanup: () => { alive = false; stop(); } };
}
