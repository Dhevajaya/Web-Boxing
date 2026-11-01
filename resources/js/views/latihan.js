// Latihan: lima mini-game 30 detik. Skor dihitung di sini, server membatasi dan memberi XP + bonus stat.
// Tamu bisa mencoba (mode latihan bebas, tanpa hadiah).
import { $, esc, api, STAT_NAMES } from '../ui.js';
import { avatar, lookFor } from '../game/index.js';
import { session, openLogin } from '../auth.js';

const GAMES = {
  samsak: { icon: '🥊', title: 'Samsak', how: 'Ketuk samsak secepat mungkin. Tiap ketukan = 1 poin.' },
  hindari: { icon: '💨', title: 'Hindari', how: 'Sarung tinju datang dari kiri atau kanan. Ketuk tombol di sisi SEBALIKNYA sebelum kena.' },
  lompat: { icon: '🪢', title: 'Lompat tali', how: 'Ketuk tepat saat tali menyentuh lantai (zona hijau). Makin lama makin cepat!' },
  tahan: { icon: '🛡️', title: 'Tahan', how: 'TAHAN tombol saat lampu merah (serangan), LEPAS saat hijau. Dinilai tiap setengah detik.' },
  situp: { icon: '🔁', title: 'Sit-up', how: 'Ketuk Kiri dan Kanan bergantian. Ketukan sisi yang sama dua kali tidak dihitung.' },
};

const MAX_TAPS_PER_SEC = 15;

export function latihanView(el) {
  let alive = true;
  let info = null;
  let stopGame = () => {};
  document.title = `Latihan · ${window.GAME?.brand?.name ?? ''}`;

  const load = () => api('/api/training', { cache: 'no-store' }).then(({ data }) => { info = data; if (alive) menu(); })
    .catch((e) => { if (alive) el.innerHTML = `<p class="banner bad">${esc(e.message)}</p>`; });

  function pips(n, max) {
    return `<span class="pips" aria-label="Bonus +${n}">${Array.from({ length: max }, (_, i) => `<i class="${i < n ? 'on' : ''}"></i>`).join('')}</span>`;
  }

  function menu() {
    stopGame();
    const t = info.today;
    el.innerHTML = `
      <section class="hero"><span class="eyebrow">${session.me ? 'Hadiah hari ini' : 'Mode coba'}</span><h1>Lati<em>han</em></h1>
        <p>${session.me ? `Tiap game memberi sampai ${info.xp_cap} XP per hari, dan skor tinggi memberi bonus stat (+1 sampai +${info.bonus_max}) untuk semua laga hari ini.` : 'Coba gratis. Masuk agar XP dan bonus stat tersimpan.'}</p></section>
      ${session.me ? '' : '<button class="btn btn-accent btn-block" id="login" type="button">Masuk / daftar</button>'}
      <div class="games">${Object.entries(GAMES).map(([key, g]) => {
        const cfg = info.games[key];
        const d = t?.[key];
        return `<button class="game" type="button" data-g="${key}">
          <span class="g-ic" aria-hidden="true">${g.icon}</span>
          <span class="g-id"><b>${g.title}</b><small>${STAT_NAMES[cfg.stat]}</small></span>
          ${d ? `<span class="g-st">${pips(d.bonusToday, info.bonus_max)}<small>${d.xpToday}/${info.xp_cap} XP · terbaik ${d.best}</small></span>` : ''}
        </button>`;
      }).join('')}</div>
      ${t ? `<p class="hint">Bonus aktif hari ini: ${Object.entries(t).filter(([, d]) => d.bonusToday).map(([k, d]) => `<b>${STAT_NAMES[info.games[k].stat]} +${d.bonusToday}</b>`).join(', ') || 'belum ada'}. Berlaku untuk Fight Night, tantangan, dan piala sampai pukul 24.00 WIB.</p>` : ''}`;
    $('#login', el)?.addEventListener('click', () => openLogin());
    el.querySelectorAll('[data-g]').forEach((b) => b.addEventListener('click', () => intro(b.dataset.g)));
  }

  function intro(key) {
    const g = GAMES[key];
    const cfg = info.games[key];
    const d = info.today?.[key];
    el.innerHTML = `
      <button class="sub back linkish" type="button" id="back">← Pilih game</button>
      <section class="card">
        <span class="eyebrow">${STAT_NAMES[cfg.stat]}</span>
        <h2>${g.icon} ${g.title}</h2>
        <p>${g.how}</p>
        <p class="hint">${info.seconds} detik. Bonus stat: +1 di skor ${cfg.bonus[0]}, +2 di ${cfg.bonus[1]}, +3 di ${cfg.bonus[2]}.${d ? ` Terbaikmu hari ini: <b>${d.best}</b>.` : ''}</p>
        <button class="btn btn-accent btn-block" id="go" type="button">Mulai</button>
        <p class="m-status" id="err" hidden></p>
      </section>`;
    $('#back', el).onclick = menu;
    $('#go', el).onclick = async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      let token = null;
      if (session.me) {
        try {
          token = (await api('/api/training/start', { method: 'POST', body: JSON.stringify({ game: key }) })).data.token;
        } catch (err) {
          const p = $('#err', el);
          if (p) { p.hidden = false; p.textContent = err.message; }
          btn.disabled = false;
          return;
        }
      }
      if (alive) run(key, token);
    };
  }

  function run(key, token) {
    const g = GAMES[key];
    el.innerHTML = `
      <section class="card play">
        <div class="play-top"><b>${g.icon} ${g.title}</b><span class="score" id="score">0</span></div>
        <div class="timebar"><i id="time"></i></div>
        <div class="stage" id="stage"><div class="count" id="count">3</div></div>
      </section>`;
    const stage = $('#stage', el);
    const scoreEl = $('#score', el);
    const state = { score: 0, taps: 0, recent: [], running: false };
    // Ketukan lebih cepat dari batas server diabaikan, supaya skor jujur tidak ditolak.
    const tap = () => {
      const now = performance.now();
      state.recent = state.recent.filter((x) => now - x < 1000);
      if (state.recent.length >= MAX_TAPS_PER_SEC - 1) return false;
      state.recent.push(now);
      state.taps++;
      return true;
    };
    const add = (n = 1) => { state.score += n; scoreEl.textContent = state.score; };

    let raf = 0;
    let timers = [];
    let cleanupGame = () => {};
    stopGame = () => { cancelAnimationFrame(raf); timers.forEach(clearTimeout); cleanupGame(); stopGame = () => {}; };

    let c = 3;
    const countdown = () => {
      if (!alive) return;
      if (c > 0) { $('#count', el).textContent = c--; timers.push(setTimeout(countdown, 700)); return; }
      stage.innerHTML = '';
      state.running = true;
      const start = performance.now();
      const ms = info.seconds * 1000;
      cleanupGame = MOUNT[key](stage, { tap, add, state, timers, elapsed: () => performance.now() - start }) || (() => {});
      const frame = () => {
        const t = performance.now() - start;
        const bar = $('#time', el);
        if (bar) bar.style.width = `${Math.max(0, 100 - (t / ms) * 100)}%`;
        if (t >= ms) return finish();
        raf = requestAnimationFrame(frame);
      };
      raf = requestAnimationFrame(frame);
    };
    countdown();

    async function finish() {
      state.running = false;
      stopGame();
      if (!alive) return;
      const score = Math.round(state.score);
      let res = null;
      let err = null;
      if (token) {
        try {
          res = (await api('/api/training/finish', { method: 'POST', body: JSON.stringify({ token, score, taps: state.taps }) })).data;
          if (info.today) info.today[key] = { best: res.best, xpToday: res.xpToday, bonusToday: res.bonusToday, plays: res.plays };
        } catch (e) { err = e.message; }
      }
      if (!alive) return;
      el.innerHTML = `
        <section class="card done">
          <span class="eyebrow">${g.icon} ${g.title}</span>
          <div class="result"><b>${score}</b><span>poin</span></div>
          ${res ? `<div class="record"><div><b>+${res.xp}</b><small>XP</small></div><div><b>+${res.bonus}</b><small>Bonus ${STAT_NAMES[info.games[key].stat]}</small></div><div><b>${res.best}</b><small>Terbaik</small></div></div>
            ${res.xp === 0 && res.xpToday >= info.xp_cap ? `<p class="hint">Jatah XP game ini hari ini sudah penuh (${info.xp_cap}). Bonus stat tetap bisa naik.</p>` : ''}` : ''}
          ${err ? `<p class="m-status">${esc(err)}</p>` : ''}
          ${!session.me ? '<p class="hint">Mode coba: masuk agar skor memberi XP dan bonus stat.</p>' : ''}
          <div class="row"><button class="btn btn-ghost" id="menu" type="button">Pilih game</button><button class="btn btn-accent" id="again" type="button">Main lagi</button></div>
        </section>`;
      $('#menu', el).onclick = menu;
      $('#again', el).onclick = () => intro(key);
    }
  }

  // ------------------------------------------------------------ mini-game

  const MOUNT = {
    samsak(stage, { tap, add }) {
      stage.innerHTML = '<button class="bag" type="button" aria-label="Ketuk samsak"><span class="bag-body"></span></button>';
      const bag = stage.querySelector('.bag');
      const hit = (e) => {
        e.preventDefault();
        if (!tap()) return;
        add();
        bag.classList.remove('swing');
        void bag.offsetWidth;
        bag.classList.add('swing');
      };
      bag.addEventListener('pointerdown', hit);
      return () => bag.removeEventListener('pointerdown', hit);
    },

    hindari(stage, { tap, add, timers, elapsed, state }) {
      const me = session.me?.username ?? 'tamu';
      stage.innerHTML = `
        <div class="dodge-ring"><span class="glove l" hidden>🥊</span><div class="dodge-me">${avatar(lookFor(me), { size: 110 })}</div><span class="glove r" hidden>🥊</span></div>
        <div class="row dodge-btns"><button class="btn btn-ghost" data-s="l" type="button">← Elak</button><button class="btn btn-ghost" data-s="r" type="button">Elak →</button></div>`;
      const meEl = stage.querySelector('.dodge-me');
      let from = null;
      let answered = true;
      const next = () => {
        if (!state.running) return;
        const t = elapsed();
        const window = Math.max(550, 950 - t / 60);
        from = Math.random() < 0.5 ? 'l' : 'r';
        answered = false;
        stage.querySelectorAll('.glove').forEach((x) => { x.hidden = !x.classList.contains(from); x.classList.remove('fly'); void x.offsetWidth; x.classList.add('fly'); x.style.animationDuration = `${window}ms`; });
        timers.push(setTimeout(() => {
          if (!answered) { meEl.classList.remove('hurt'); void meEl.offsetWidth; meEl.classList.add('hurt'); }
          answered = true;
          stage.querySelectorAll('.glove').forEach((x) => { x.hidden = true; });
          timers.push(setTimeout(next, 180 + Math.random() * 250));
        }, window));
      };
      const press = (e) => {
        if (!tap()) return;
        if (answered) return;
        answered = true;
        const side = e.currentTarget.dataset.s;
        if ((from === 'l' && side === 'r') || (from === 'r' && side === 'l')) {
          add();
          meEl.style.setProperty('--dx', side === 'r' ? '30px' : '-30px');
          meEl.classList.remove('slip'); void meEl.offsetWidth; meEl.classList.add('slip');
        } else {
          meEl.classList.remove('hurt'); void meEl.offsetWidth; meEl.classList.add('hurt');
        }
      };
      stage.querySelectorAll('[data-s]').forEach((b) => b.addEventListener('pointerdown', press));
      timers.push(setTimeout(next, 300));
    },

    lompat(stage, { tap, add, elapsed, state }) {
      stage.innerHTML = '<div class="rope-wrap"><div class="rope" id="rope"></div><div class="jumper">🧍</div><div class="floor"><i class="zone"></i></div></div><button class="btn btn-accent btn-block jump-btn" type="button">LOMPAT</button>';
      const rope = stage.querySelector('#rope');
      const jumper = stage.querySelector('.jumper');
      const zone = stage.querySelector('.zone');
      let cycle = -1;
      let jumpedCycle = -2;
      let phase = 0;
      let raf = 0;
      let acc = 0;
      let last = performance.now();
      const loop = (now) => {
        const period = Math.max(600, 1000 - elapsed() / 75);
        acc += (now - last) / period;
        last = now;
        phase = acc % 1;
        cycle = Math.floor(acc);
        rope.style.transform = `rotateX(${phase * 360}deg)`;
        zone.classList.toggle('on', phase > 0.82 || phase < 0.08);
        if (state.running) raf = requestAnimationFrame(loop);
      };
      raf = requestAnimationFrame(loop);
      const jump = (e) => {
        e.preventDefault();
        if (!tap()) return;
        const inZone = phase > 0.82 || phase < 0.08;
        const c = phase > 0.82 ? cycle + 1 : cycle;
        jumper.classList.remove('up', 'trip'); void jumper.offsetWidth;
        if (inZone && c !== jumpedCycle) { jumpedCycle = c; add(); jumper.classList.add('up'); } else jumper.classList.add('trip');
      };
      const btn = stage.querySelector('.jump-btn');
      btn.addEventListener('pointerdown', jump);
      return () => { cancelAnimationFrame(raf); btn.removeEventListener('pointerdown', jump); };
    },

    tahan(stage, { tap, add, timers, state }) {
      stage.innerHTML = '<div class="light safe" id="light">AMAN</div><button class="btn btn-ghost btn-block guard" type="button">🛡️ TAHAN DI SINI</button>';
      const light = stage.querySelector('#light');
      const guard = stage.querySelector('.guard');
      let attack = false;
      let holding = false;
      const flip = () => {
        if (!state.running) return;
        attack = !attack;
        light.className = `light ${attack ? 'attack' : 'safe'}`;
        light.textContent = attack ? 'SERANGAN!' : 'AMAN';
        timers.push(setTimeout(flip, 900 + Math.random() * 1600));
      };
      const sample = () => {
        if (!state.running) return;
        if (holding === attack) add();
        timers.push(setTimeout(sample, 500));
      };
      const down = (e) => { e.preventDefault(); tap(); holding = true; guard.classList.add('held'); };
      const up = () => { holding = false; guard.classList.remove('held'); };
      guard.addEventListener('pointerdown', down);
      addEventListener('pointerup', up);
      addEventListener('pointercancel', up);
      timers.push(setTimeout(flip, 1200), setTimeout(sample, 500));
      return () => { removeEventListener('pointerup', up); removeEventListener('pointercancel', up); };
    },

    situp(stage, { tap, add }) {
      stage.innerHTML = '<div class="situp-fig" id="fig">🙇</div><div class="row situp-btns"><button class="btn btn-ghost" data-s="l" type="button">Kiri</button><button class="btn btn-ghost" data-s="r" type="button">Kanan</button></div>';
      const fig = stage.querySelector('#fig');
      let lastSide = null;
      const press = (e) => {
        e.preventDefault();
        if (!tap()) return;
        const s = e.currentTarget.dataset.s;
        if (s !== lastSide) { add(); fig.classList.toggle('up'); }
        lastSide = s;
      };
      stage.querySelectorAll('[data-s]').forEach((b) => b.addEventListener('pointerdown', press));
    },
  };

  el.innerHTML = '<p class="loading">Menyiapkan sasana latihan…</p>';
  load();
  return { cleanup: () => { alive = false; stopGame(); } };
}
