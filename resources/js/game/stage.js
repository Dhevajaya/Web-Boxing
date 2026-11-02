// Panggung gelanggang kampung tampak samping: penonton, kamera, efek, jurus, dan pemutar laga.
// Memutar kejadian hasil simulate() menjadi animasi + suara. Mendukung laga v1 (arsip tinju:
// bell, miss, hit, big, kd, ko, end) dan v2 KAMPUNG TENDANG (+ move, set, tired, block, check,
// jurus, leg, card). Animasi selalu mengikuti hasil mesin, tidak pernah mengubahnya.
//
//   const stage = createStage(el, { catalog, looks: { a, b }, names, brand }, result, hooks)
//   stage.play(); stage.pause(); stage.setSpeed(2); stage.skip(); stage.setMuted(true); stage.destroy()
//
// hooks: onEvent(e, i) untuk feed teks, onHp(ha, hb), onStatus(e) napas/bara/kaki, onRound(rd), onDone()
// opts.fx: 'full' | 'lite' | 'off' (efek visual; 'off' = tanpa getar, kilat, slow-motion)

import { boxerMarkup, boxerNodes, applyPose, POSES, lerpPose, setKoFace } from './boxer.js';
import { describe, fallLine, afterCountLine } from './commentary.js';
import { createSfx } from './sfx.js';
import { createVoice } from './voice.js';

const NS = 'http://www.w3.org/2000/svg';
const SCALE = 0.98;          // skala petinju di panggung
const FEET = 198;            // garis kaki di layar
const CX = 200;              // tengah ring
const HOME = 58;             // jarak titik awal dari tengah (unit petinju)
const FALL_X = -22;          // kaki sedikit melewati tengah, jadi badan yang terlentang berada di tengah kanvas
const REACH = { jab: 74, straight: 76, hook: 66, upper: 62, body: 64, kick_low: 74, kick_body: 78, kick_high: 80, teep: 84 };
// id teknik (v1 + v2) -> pose animasi
const PUNCH = {
  jab: 'jab', straight: 'straight', hook_l: 'hook', hook_r: 'hook', upper: 'upper', body: 'body',
  cross: 'straight', hook: 'hook', low_kick: 'kick_low', body_kick: 'kick_body', high_kick: 'kick_high', teep: 'teep',
};
const KICKS = new Set(['kick_low', 'kick_body', 'kick_high', 'teep']);
// jarak abstrak v2 -> setengah jarak antar petarung (unit petinju)
const RANGE_X = { close: 40, mid: 58, far: 80 };
const COUNT_WORDS = ['satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh'];

const ease = (k) => 1 - (1 - k) ** 3;
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

// Penonton: siluet di balik pagar besi, posisi tetap (pseudo-acak dari indeks).
function crowdMarkup() {
  let out = '';
  const rows = [[92, '#2a1f1b', 14], [104, '#33251f', 16], [118, '#3d2c24', 18]];
  rows.forEach(([y, c, r], ri) => {
    for (let i = 0; i < 24; i++) {
      const x = i * 18 + ((ri * 7 + i * 13) % 11) - 4;
      const dy = ((i * 5 + ri * 3) % 7) - 3;
      out += `<g class="fan" style="--d:${(i * 0.13 + ri * 0.31) % 1}s"><circle cx="${x}" cy="${y + dy}" r="${r * 0.42}" fill="${c}"/><rect x="${x - r * 0.75}" y="${y + dy + r * 0.3}" width="${r * 1.5}" height="${r}" rx="${r * 0.5}" fill="${c}"/></g>`;
    }
  });
  return out;
}

// Tembok bata gang, poster sobek, lampu jalan, lampu gantung, pagar besi, lapangan semen.
function sceneMarkup(brand, A, B) {
  const name = esc((brand.name ?? 'Kampung Tendang').toUpperCase());
  const ig = esc(brand.instagram ? `@${brand.instagram}` : '');
  const site = esc((location.host || '').toUpperCase());
  const rope = (x1, y1, x2, y2, c) => `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#0e0c0b" stroke-width="4" stroke-linecap="round"/><line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${c}" stroke-width="2.4" stroke-linecap="round"/>`;
  const post = (x, top, bot, w) => `<rect x="${x - w / 2}" y="${top}" width="${w}" height="${bot - top}" rx="${w / 2.5}" fill="#c9a66b" stroke="#0e0c0b" stroke-width="2"/>${[0.3, 0.62].map((k) => `<line x1="${x - w / 2}" x2="${x + w / 2}" y1="${top + (bot - top) * k}" y2="${top + (bot - top) * k}" stroke="#7a5a2c" stroke-width="1.4"/>`).join('')}`;
  let bricks = '';
  for (let row = 0; row < 7; row++) {
    for (let c = -1; c < 14; c++) {
      const x = c * 32 + (row % 2 ? 16 : 0);
      bricks += `<rect x="${x}" y="${row * 12}" width="30" height="10" rx="1.5" fill="${(row * 3 + c) % 5 ? '#7d2f22' : '#8d3726'}"/>`;
    }
  }
  const lights = Array.from({ length: 13 }, (_, i) => {
    const x = 20 + i * 30;
    const y = 18 + Math.sin((i / 12) * Math.PI) * 14;
    return `<circle cx="${x}" cy="${y}" r="2.6" fill="${i % 3 === 0 ? '#f2b84b' : i % 3 === 1 ? '#e6d8bd' : '#e5683f'}" class="bulb" style="--d:${(i * 0.21) % 1}s"/>`;
  }).join('');
  return `
    <defs>
      <linearGradient id="stBg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0f0d0c"/><stop offset="1" stop-color="#1d1714"/></linearGradient>
      <linearGradient id="stLamp" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f2b84b" stop-opacity=".34"/><stop offset="1" stop-color="#f2b84b" stop-opacity="0"/></linearGradient>
      <linearGradient id="stFloor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5d554c"/><stop offset="1" stop-color="#3e3832"/></linearGradient>
      <linearGradient id="stApron" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2a2320"/><stop offset="1" stop-color="#141110"/></linearGradient>
      <radialGradient id="stGlow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#f2b84b" stop-opacity=".26"/><stop offset="1" stop-color="#f2b84b" stop-opacity="0"/></radialGradient>
    </defs>
    <g class="cam">
      <rect x="-40" y="-40" width="480" height="330" fill="url(#stBg)"/>
      <g opacity=".85">${bricks}</g>
      <rect x="0" y="84" width="400" height="6" fill="#4a1d15"/>
      <g class="posters">
        <g transform="translate(36 20) rotate(-4)"><rect width="52" height="58" fill="#e6d8bd"/><rect x="4" y="4" width="44" height="22" fill="#a63d2a"/><text x="26" y="20" text-anchor="middle" class="st-poster">DUEL</text><text x="26" y="40" text-anchor="middle" class="st-poster-s">MALAM INI</text><path d="M30 58 L52 42 L52 58 Z" fill="#1d1714"/></g>
        <g transform="translate(314 24) rotate(5)"><rect width="50" height="54" fill="#d9c9a6"/><circle cx="25" cy="22" r="13" fill="#141312"/><text x="25" y="47" text-anchor="middle" class="st-poster-s">DICARI JAGOAN</text><path d="M0 0 L16 0 L0 14 Z" fill="#1d1714"/></g>
      </g>
      <path d="M0 14 Q200 46 400 14" fill="none" stroke="#141312" stroke-width="1"/>
      <g>${lights}</g>
      <g class="crowd">${crowdMarkup()}</g>
      <g class="fence" stroke="#141312" stroke-width="2.2">${Array.from({ length: 41 }, (_, i) => `<line x1="${i * 10}" y1="86" x2="${i * 10}" y2="130"/>`).join('')}<line x1="0" y1="90" x2="400" y2="90" stroke-width="3"/><line x1="0" y1="128" x2="400" y2="128" stroke-width="3"/></g>
      <g class="lamp"><rect x="384" y="-10" width="5" height="150" fill="#2b2522"/><path d="M386 2 Q386 -4 364 -2 L356 4" fill="none" stroke="#2b2522" stroke-width="4"/><path d="M346 4 h18 l-3 6 h-12 z" fill="#2b2522"/><ellipse cx="355" cy="11" rx="6" ry="2.4" fill="#ffe2a0"/></g>
      <polygon points="150,-10 250,-10 330,206 70,206" fill="url(#stLamp)"/>
      <polygon points="56,130 344,130 386,206 14,206" fill="url(#stFloor)" stroke="#0e0c0b" stroke-width="2"/>
      <ellipse cx="200" cy="170" rx="104" ry="24" fill="url(#stGlow)"/>
      <ellipse cx="200" cy="170" rx="66" ry="15" fill="none" stroke="#e6d8bd" stroke-width="2.2" stroke-dasharray="7 5" opacity=".7"/>
      <text x="200" y="174" text-anchor="middle" class="st-logo">${name}</text>
      ${post(58, 88, 130, 7)}${post(342, 88, 130, 7)}
      ${rope(58, 98, 342, 98, '#e6d8bd')}${rope(58, 112, 342, 112, '#a63d2a')}
      ${rope(58, 98, 18, 150, '#e6d8bd')}${rope(58, 112, 18, 168, '#a63d2a')}
      ${rope(342, 98, 382, 150, '#e6d8bd')}${rope(342, 112, 382, 168, '#a63d2a')}
      <g class="fighters" transform="translate(${CX} ${FEET}) scale(${SCALE})">${A}${B}</g>
      ${rope(18, 150, 382, 150, '#e6d8bd')}${rope(18, 168, 382, 168, '#a63d2a')}
      ${post(18, 140, 212, 10)}${post(382, 140, 212, 10)}
      <rect x="-40" y="206" width="480" height="80" fill="url(#stApron)"/>
      <rect x="-40" y="206" width="480" height="4" fill="#a63d2a"/>
      <g transform="translate(200 226) rotate(-1.5)"><rect x="-66" y="-12" width="132" height="22" fill="#e6d8bd"/><text y="4" text-anchor="middle" class="st-apron">${name.length > 16 ? name.slice(0, 16) : name}</text></g>
      <text x="24" y="232" class="st-apron-s">${site}</text>
      <text x="376" y="232" text-anchor="end" class="st-apron-s">${ig}</text>
      <g class="fx"></g>
    </g>
    <rect class="dim" x="0" y="0" width="400" height="250" fill="#000" opacity="0" pointer-events="none"/>
    <rect class="flash" x="0" y="0" width="400" height="250" fill="#fff" opacity="0" pointer-events="none"/>
    <g class="banner" opacity="0" pointer-events="none"><rect x="-10" y="96" width="420" height="44" fill="#a63d2a"/><rect x="-10" y="96" width="420" height="4" fill="#f2b84b"/><rect x="-10" y="136" width="420" height="4" fill="#f2b84b"/><text x="200" y="111" text-anchor="middle" class="st-banner-s"></text><text x="200" y="132" text-anchor="middle" class="st-banner"></text></g>
    <g class="big" pointer-events="none"><text x="200" y="88" text-anchor="middle"></text></g>`;
}

export function createStage(el, { catalog, looks, names, brand = {}, fx: fxLevel = 'full', jurusNames = {} }, r, hooks = {}) {
  const reduce = fxLevel === 'off' || matchMedia('(prefers-reduced-motion: reduce)').matches;
  const lite = reduce || fxLevel === 'lite';
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 400 250');
  svg.setAttribute('class', 'stage-svg');
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', `Arena ${names.a} melawan ${names.b}`);
  svg.innerHTML = sceneMarkup(brand, boxerMarkup(catalog, looks.a, 'a'), boxerMarkup(catalog, looks.b, 'b'));
  el.replaceChildren(svg);

  const cam = svg.querySelector('.cam');
  const fx = svg.querySelector('.fx');
  const flashEl = svg.querySelector('.flash');
  const dimEl = svg.querySelector('.dim');
  const bannerG = svg.querySelector('.banner');
  const [bannerSub, bannerT] = bannerG.querySelectorAll('text');
  const bigG = svg.querySelector('.big');
  const bigT = bigG.querySelector('text');
  const crowdEl = svg.querySelector('.crowd');

  const F = {
    a: { n: boxerNodes(svg, 'a'), facing: 1, home: -HOME },
    b: { n: boxerNodes(svg, 'b'), facing: -1, home: HOME },
  };
  for (const s of ['a', 'b']) {
    const f = F[s];
    f.cur = { ...POSES.guard, x: f.home * 1.5, y: 0, rot: 0 };
    f.from = f.cur;
    f.to = f.cur;
    f.t0 = 0;
    f.dur = 1;
    f.idle = true;
  }

  const sfx = createSfx();
  const voice = createVoice();
  let muted = false;
  try { muted = localStorage.getItem('wb.sound') === '0'; } catch { /* penyimpanan diblokir */ }
  sfx.setMuted(muted);
  voice.setMuted(muted);

  // Jam sendiri: berhenti saat jeda, dipercepat oleh speed. Semua tween & tunggu memakai jam ini.
  let clock = 0;
  let last = performance.now();
  let speed = 1;
  let slowmo = 1;
  let playing = false;
  let skipping = false;
  let alive = true;
  let raf = 0;
  const waits = [];
  const effects = [];
  const camS = { z: 1, px: CX, py: 125, from: null, t0: 0, dur: 1, shake: 0 };

  const wait = (ms) => (skipping ? Promise.resolve() : new Promise((res) => waits.push({ t: clock + ms, res })));
  const pose = (side, target, dur = 160) => {
    const f = F[side];
    f.from = { ...f.cur };
    f.to = { ...f.cur, ...target };
    f.t0 = clock;
    f.dur = skipping ? 0.01 : dur;
  };
  const say = (text, opts) => { if (!skipping && (speed === 1 || opts?.urgent)) voice.say(text, opts); };
  const play = (fn) => { if (!skipping) fn(); };

  // Titik layar untuk efek: kepala / badan petarung.
  const point = (side, part = 'head') => {
    const f = F[side];
    const lx = f.cur.x + f.facing * (part === 'head' ? 8 : part === 'leg' ? 12 : 10);
    const ly = part === 'head' ? -122 : part === 'leg' ? -34 : -82;
    return [CX + lx * SCALE, FEET + ly * SCALE];
  };

  function addFx(node, dur, update) {
    fx.appendChild(node);
    effects.push({ node, t0: clock, dur: skipping ? 0.01 : dur, update });
  }
  const mk = (tag, attrs, html) => {
    const e = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v);
    if (html) e.innerHTML = html;
    return e;
  };

  function burst(x, y, big) {
    if (skipping) return;
    const pts = [];
    const n = big ? 12 : 9;
    for (let i = 0; i < n * 2; i++) {
      const a = (i / (n * 2)) * Math.PI * 2;
      const rr = i % 2 ? (big ? 9 : 6) : (big ? 22 : 13);
      pts.push(`${(Math.cos(a) * rr).toFixed(1)},${(Math.sin(a) * rr).toFixed(1)}`);
    }
    const g = mk('g', {}, `<polygon points="${pts.join(' ')}" fill="${big ? '#ffd23f' : '#fff3c4'}" stroke="#ff5a36" stroke-width="2.5" stroke-linejoin="round"/>${big ? '<text y="5" text-anchor="middle" class="st-pow">BAM</text>' : ''}`);
    addFx(g, big ? 520 : 300, (k) => {
      const s = 0.4 + ease(Math.min(1, k * 2.2)) * (big ? 1.1 : 0.9);
      g.setAttribute('transform', `translate(${x} ${y}) scale(${s}) rotate(${k * 25})`);
      g.setAttribute('opacity', String(k < 0.6 ? 1 : 1 - (k - 0.6) / 0.4));
    });
  }

  function whooshFx(x, y, dir) {
    if (skipping) return;
    const g = mk('g', {}, `<path d="M${-14 * dir} 10 Q${-4 * dir} -14 ${16 * dir} -8" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".8"/><text x="${6 * dir}" y="-14" text-anchor="middle" class="st-wush">WUSH</text>`);
    addFx(g, 380, (k) => {
      g.setAttribute('transform', `translate(${x} ${y - k * 8})`);
      g.setAttribute('opacity', String(1 - k));
    });
  }

  function big(text, { color = '#ffd23f', dur = 900, size = 48, y = 88, sub = false } = {}) {
    if (skipping) return;
    bigT.textContent = text;
    bigT.setAttribute('y', String(y));
    bigT.setAttribute('class', `st-big${sub ? ' sub' : ''}`);
    bigT.style.fill = color;
    bigT.style.fontSize = `${size}px`;
    const t0 = clock;
    effects.push({
      node: null, t0, dur,
      update: (k) => {
        const s = k < 0.18 ? 0.5 + (k / 0.18) * 0.7 : k < 0.3 ? 1.2 - ((k - 0.18) / 0.12) * 0.2 : 1;
        bigG.setAttribute('transform', `translate(200 ${y}) scale(${s}) translate(-200 ${-y})`);
        bigG.setAttribute('opacity', String(k > 0.8 ? (1 - k) / 0.2 : 1));
      },
    });
  }

  function flash(strength = 0.6) {
    if (skipping || reduce) return;
    effects.push({ node: null, t0: clock, dur: 260, update: (k) => flashEl.setAttribute('opacity', String(strength * (1 - k))) });
  }

  // Jejak gerak jurus: busur bercahaya mengikuti arah serangan.
  function trail(x, y, dir, color = '#f2b84b') {
    if (skipping || lite) return;
    const g = mk('g', {}, `<path d="M${-34 * dir} 18 Q${-8 * dir} -26 ${24 * dir} -6" fill="none" stroke="${color}" stroke-width="7" stroke-linecap="round" opacity=".55"/><path d="M${-30 * dir} 14 Q${-8 * dir} -20 ${20 * dir} -6" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>`);
    addFx(g, 360, (k) => {
      g.setAttribute('transform', `translate(${x} ${y}) scale(${0.8 + k * 0.4})`);
      g.setAttribute('opacity', String(1 - k));
    });
  }

  // Percikan tangkis (biru dingin) dan debu di lantai.
  function spark(x, y) {
    if (skipping) return;
    const lines = Array.from({ length: 7 }, (_, i) => {
      const a = (i / 7) * Math.PI * 2;
      return `<line x1="${Math.cos(a) * 4}" y1="${Math.sin(a) * 4}" x2="${Math.cos(a) * 13}" y2="${Math.sin(a) * 13}" stroke="#e6d8bd" stroke-width="2.4" stroke-linecap="round"/>`;
    }).join('');
    const g = mk('g', {}, lines);
    addFx(g, 260, (k) => {
      g.setAttribute('transform', `translate(${x} ${y}) scale(${0.6 + k * 0.7})`);
      g.setAttribute('opacity', String(1 - k));
    });
  }
  function dust(x) {
    if (skipping || lite) return;
    const g = mk('g', {}, [0, 1, 2, 3].map((i) => `<circle cx="${(i - 1.5) * 9}" cy="0" r="${5 + (i % 2) * 2}" fill="#8a7f72" opacity=".55"/>`).join(''));
    addFx(g, 520, (k) => {
      g.setAttribute('transform', `translate(${x} ${FEET - 2 - k * 8}) scale(${0.6 + k})`);
      g.setAttribute('opacity', String(1 - k));
    });
  }
  function tag(text, x, y, color = '#e6d8bd') {
    if (skipping) return;
    const g = mk('g', {}, `<text text-anchor="middle" class="st-tag" fill="${color}">${esc(text)}</text>`);
    addFx(g, 700, (k) => {
      g.setAttribute('transform', `translate(${x} ${y - k * 14})`);
      g.setAttribute('opacity', String(k > 0.7 ? (1 - k) / 0.3 : 1));
    });
  }
  // Banner nama jurus + layar meredup (efek level 3).
  function jurusBanner(who, name, level) {
    if (skipping) return;
    bannerSub.textContent = `${names[who]} · JURUS`;
    bannerT.textContent = name.toUpperCase();
    const dur = level >= 3 ? 1300 : 900;
    effects.push({
      node: null, t0: clock, dur,
      update: (k) => {
        const o = k < 0.15 ? k / 0.15 : k > 0.8 ? (1 - k) / 0.2 : 1;
        bannerG.setAttribute('opacity', String(o));
        bannerG.setAttribute('transform', `translate(${(1 - Math.min(1, k * 6)) * -60} 0)`);
        if (!lite) dimEl.setAttribute('opacity', String(o * (level >= 3 ? 0.45 : 0.25)));
      },
    });
  }

  function zoom(x, y, z, dur) {
    if (skipping) return;
    camS.from = { z: camS.z, px: camS.px, py: camS.py };
    camS.to = reduce ? { z: 1, px: CX, py: 125 } : { z, px: x, py: y };
    camS.t0 = clock;
    camS.dur = dur;
  }
  const shake = (amt) => { if (!skipping && !reduce) camS.shake = Math.max(camS.shake, amt); };
  const excite = (on) => crowdEl.classList.toggle('hype', on);

  // ------------------------------------------------------------ loop

  function frame(now) {
    if (!alive) return;
    const dt = Math.min(100, now - last);
    last = now;
    if (playing) clock += dt * speed * slowmo;

    for (let i = waits.length - 1; i >= 0; i--) {
      if (skipping || waits[i].t <= clock) {
        waits[i].res();
        waits.splice(i, 1);
      }
    }

    for (const s of ['a', 'b']) {
      const f = F[s];
      const k = Math.min(1, (clock - f.t0) / f.dur);
      f.cur = lerpPose(f.from, f.to, ease(k));
      const bob = f.idle && !reduce ? Math.abs(Math.sin((clock / 1000) * 5.2 + (s === 'b' ? 1.3 : 0))) * -2.4 : 0;
      applyPose(f.n, { ...f.cur, y: (f.cur.y ?? 0) + bob }, f.facing);
    }

    if (camS.from) {
      const k = Math.min(1, (clock - camS.t0) / camS.dur);
      const e = ease(k);
      camS.z = camS.from.z + (camS.to.z - camS.from.z) * e;
      camS.px = camS.from.px + (camS.to.px - camS.from.px) * e;
      camS.py = camS.from.py + (camS.to.py - camS.from.py) * e;
      if (k >= 1) camS.from = null;
    }
    const sx = camS.shake ? (Math.random() - 0.5) * camS.shake : 0;
    const sy = camS.shake ? (Math.random() - 0.5) * camS.shake : 0;
    camS.shake = Math.max(0, camS.shake - dt * 0.03);
    // Fokus kamera tetap di dalam panggung.
    const z = camS.z;
    const px = Math.min(400 - 200 / z, Math.max(200 / z, camS.px));
    const py = Math.min(250 - 125 / z, Math.max(125 / z, camS.py));
    cam.setAttribute('transform', `translate(${200 + sx} ${125 + sy}) scale(${z}) translate(${-px} ${-py})`);

    for (let i = effects.length - 1; i >= 0; i--) {
      const ef = effects[i];
      const k = Math.min(1, (clock - ef.t0) / ef.dur);
      ef.update(k);
      if (k >= 1) {
        ef.node?.remove();
        effects.splice(i, 1);
      }
    }
    raf = requestAnimationFrame(frame);
  }
  raf = requestAnimationFrame(frame);

  // ------------------------------------------------------------ kejadian

  const other = (s) => (s === 'a' ? 'b' : 'a');
  let jurusColor = '#f2b84b';
  const JURUS_COLORS = ['#f2b84b', '#e5683f', '#7fd1ff', '#e6d8bd', '#ff9d3c'];

  // --- kejadian v2 di luar serangan

  async function move(e, i) {
    hooks.onEvent?.(e, i);
    hooks.onStatus?.(e);
    const who = e.who;
    const half = RANGE_X[e.r] ?? HOME;
    const mid = (F.a.cur.x + F.b.cur.x) / 2;
    F[who].idle = false;
    if (e.x) {
      // Mencoba mendekat, digagalkan tendangan dorong/langkah lawan.
      pose(who, { ...POSES.guard, x: F[who].cur.x + F[who].facing * 14 }, 160);
      await wait(170);
      pose(who, { ...POSES.guard, x: mid + F[who].home / HOME * half }, 200);
      tag('DITAHAN', CX + F[who].cur.x * SCALE, FEET - 160, '#e6d8bd');
      await wait(220);
    } else {
      pose('a', { ...POSES.guard, x: mid - half }, 260);
      pose('b', { ...POSES.guard, x: mid + half }, 260);
      await wait(280);
    }
    F[who].idle = true;
  }

  async function breathe(e, i) {
    if (i % 3 === 0) hooks.onEvent?.(e, i);
    hooks.onStatus?.(e);
    const f = F[e.who];
    f.idle = false;
    pose(e.who, { ...(e.t === 'tired' ? POSES.tired : POSES.cover), x: f.cur.x - f.facing * 6 }, 200);
    if (e.t === 'tired') {
      hooks.onEvent?.(e, i);
      const [x, y] = point(e.who, 'head');
      tag('NGOS-NGOSAN', x, y - 16, '#e6d8bd');
      say(describe(e, names));
      await wait(560);
    } else {
      await wait(260);
    }
    pose(e.who, { ...POSES.guard }, 200);
    await wait(160);
    f.idle = true;
  }

  async function jurus(e, i) {
    hooks.onEvent?.(e, i);
    hooks.onStatus?.(e);
    const name = jurusNames[e.j] ?? e.j;
    jurusColor = JURUS_COLORS[(e.j?.length ?? 0) % JURUS_COLORS.length];
    const f = F[e.who];
    f.idle = false;
    pose(e.who, { ...POSES.chamber, lt: 30, ls: 50, torso: 10, x: f.cur.x - f.facing * 8 }, 220);
    jurusBanner(e.who, name, 3);
    play(() => { sfx.roar(0.9, 1); sfx.jurus(); });
    const [x, y] = point(e.who, 'body');
    zoom(x, y, 1.35, 260);
    say(`${names[e.who]}! Jurus ${name}!`, { urgent: true });
    await wait(1100);
    if (!lite) slowmo = 0.55; // langkah jurus berikutnya diputar lambat
  }

  async function legWobble(e, i) {
    hooks.onEvent?.(e, i);
    hooks.onStatus?.(e);
    const f = F[e.who];
    f.idle = false;
    pose(e.who, { ...POSES.wobble, x: f.cur.x }, 220);
    const [x, y] = point(e.who, 'leg');
    tag('KAKI GOYAH!', x, y - 30, '#e5683f');
    say(describe(e, names), { urgent: true });
    await wait(600);
    pose(e.who, { ...POSES.guard }, 240);
    await wait(200);
    f.idle = true;
  }

  async function card(e) {
    hooks.onEvent?.(e, -1);
    big(`R${e.rd}  ${e.sa} - ${e.sb}`, { color: '#e6d8bd', dur: 1100, size: 30, y: 92 });
    await wait(900);
  }
  const corner = (side, d) => -F[side].facing * d; // posisi d unit dari tengah, ke arah sudut sendiri

  async function exchange(e, i) {
    const atk = e.who;
    const def = other(atk);
    const kind = e.t === 'check' ? 'kick_low' : (PUNCH[e.p] ?? 'jab');
    const kick = KICKS.has(kind);
    const A = F[atk];
    const D = F[def];
    // Kedua petarung berhadapan dengan jarak sesuai jangkauan serangan.
    const mid = (A.cur.x + D.cur.x) / 2;
    const reach = REACH[kind];
    const ax = mid - A.facing * reach / 2;
    const dx = mid + A.facing * reach / 2;
    A.idle = false;
    D.idle = false;
    const windup = kick && kind !== 'kick_low' ? { ...POSES.chamber, x: ax - A.facing * 4 } : { ...POSES.guard, x: ax - A.facing * 4, crouch: 6 };
    pose(atk, windup, kick ? 130 : 110);
    pose(def, { x: dx }, 140);
    await wait(kick ? 130 : 110);

    play(() => sfx.whoosh());
    pose(atk, { ...POSES[kind], x: ax }, kick ? 120 : 95);
    if (e.j) {
      const [tx, ty] = point(def, kind === 'kick_low' ? 'leg' : kind === 'kick_body' || kind === 'body' ? 'body' : 'head');
      trail(tx - A.facing * 12, ty, A.facing, jurusColor);
    }
    await wait(kick ? 120 : 95);
    const fall = e.t === 'kd' || e.t === 'ko';
    hooks.onEvent?.(e, i, fall ? fallLine(e, names) : null);
    hooks.onStatus?.(e);

    if (e.t === 'check') {
      pose(def, { ...POSES.check, x: dx }, 90);
      const [x, y] = point(def, 'leg');
      spark(x, y);
      play(() => sfx.thud());
      tag('CHECK!', x, y - 18, '#f2b84b');
      if (i % 2 === 0) say(describe(e, names));
      await wait(300);
      pose(atk, { ...POSES.wobble, x: ax - A.facing * 8 }, 160);
      await wait(220);
    } else if (e.t === 'block') {
      pose(def, { ...POSES.cover, x: dx + A.facing * 6 }, 80);
      const [x, y] = point(def, kind === 'kick_low' ? 'leg' : kind === 'kick_body' || kind === 'body' ? 'body' : 'head');
      spark(x - A.facing * 8, y);
      play(() => sfx.thud());
      hooks.onHp?.(e.ha, e.hb);
      if (i % 4 === 0) say(describe(e, names));
      await wait(260);
    } else if (e.t === 'miss') {
      const dodge = kind === 'body' || kind === 'upper' || kind === 'kick_low' ? 'dodge' : kind === 'kick_high' ? 'duck' : (i % 2 ? 'duck' : 'dodge');
      pose(def, { ...POSES[dodge], x: dx + A.facing * 10 }, 130);
      const [x, y] = point(def, 'head');
      whooshFx(x, y, A.facing);
      if (i % 3 === 0) say(describe(e, names));
      await wait(260);
    } else {
      const heavy = e.t !== 'hit';
      const [x, y] = point(def, kind === 'kick_low' ? 'leg' : kind === 'body' || kind === 'kick_body' || kind === 'teep' ? 'body' : 'head');
      play(() => sfx.punch(heavy || kick));
      if (kind === 'kick_low') dust(x);
      hooks.onHp?.(e.ha, e.hb);
      burst(x, y, heavy);
      if (heavy) {
        flash(0.55);
        zoom(x, y, 1.45, 160);
        shake(7);
        play(() => sfx.roar(e.t === 'big' ? 0.6 : 1));
        excite(true);
        pose(def, { ...POSES.big, x: dx + A.facing * 18 }, 170);
        say(fall ? fallLine(e, names) : describe(e, names), { urgent: fall });
      } else {
        pose(def, { ...POSES.hit, x: dx + A.facing * (kind === 'teep' ? 26 : 7) }, 110);
        play(() => sfx.roar(0.15, 0.3));
        if (i % 3 === 1) say(describe(e, names));
      }
      await wait(heavy ? 520 : 260);
      if (heavy) zoom(CX, 125, 1, 380);
    }
    excite(false);

    if (e.t === 'kd' || e.t === 'ko') return knockdown(e, atk, def);

    // Kembali ke kuda-kuda, jarak sesuai jarak abstrak (v2) atau normal (v1).
    const back = (A.cur.x + D.cur.x) / 2;
    const half = e.r ? RANGE_X[e.r] - 6 : 48;
    pose(atk, { ...POSES.guard, x: back - A.facing * half }, 230);
    pose(def, { ...POSES.guard, x: back + A.facing * half }, 260);
    await wait(240);
    A.idle = true;
    D.idle = true;
    await wait(e.t === 'big' ? 160 : 60);
  }

  async function knockdown(e, atk, def) {
    const D = F[def];
    const A = F[atk];
    // Jatuh terlentang ke arah sudutnya sendiri.
    pose(def, { ...POSES.down, x: corner(def, FALL_X), y: -14, rot: 90, crouch: 0 }, 380);
    setKoFace(D.n, true);
    await wait(360);
    play(() => sfx.thud());
    shake(9);
    play(() => sfx.roar(1, 1.2));
    excite(true);
    big(e.why === 'leg' ? 'KAKI TAK SANGGUP!' : 'JATUH!', { color: '#e5683f', dur: 800, size: e.why === 'leg' ? 30 : 40 });
    // Penyerang mundur ke sudut netral.
    pose(atk, { ...POSES.guard, x: corner(atk, 100) }, 500);
    A.idle = true;
    await wait(700);

    const ko = e.t === 'ko';
    const tko = ko && e.how === 'TKO';
    const upTo = ko ? (tko ? (e.why === 'leg' ? 2 : 4) : 10) : 3 + ((e.d ?? 0) + e.rd) % 5; // hitungan saat jatuh tapi bangkit: 3..7
    for (let c = 1; c <= upTo; c++) {
      big(String(c), { color: '#ffffff', dur: 620, size: 56, y: 80 });
      play(() => sfx.tick());
      if (c <= 3 || ko) say(COUNT_WORDS[c - 1], { urgent: true, rate: 1.3 });
      await wait(620);
    }

    if (!ko) {
      big('BANGKIT!', { color: '#ffd23f', dur: 1000, size: 44 });
      play(() => sfx.roar(0.7, 0.8));
      hooks.onEvent?.(e, -1, afterCountLine(e, names));
      say(afterCountLine(e, names), { urgent: true });
      setKoFace(D.n, false);
      pose(def, { ...POSES.rise, rot: 20, y: 0 }, 360);
      await wait(380);
      pose(def, { ...POSES.guard, rot: 0, x: corner(def, HOME) }, 320);
      pose(atk, { ...POSES.guard, x: corner(atk, HOME) }, 420);
      await wait(520);
      D.idle = true;
      excite(false);
      return;
    }

    // KO / TKO
    big(tko ? 'TKO!' : 'KO!', { color: '#ffd23f', dur: 2400, size: 76, y: 92 });
    play(() => { sfx.bell(3); sfx.cheer(); });
    hooks.onEvent?.(e, -1, afterCountLine(e, names));
    say(afterCountLine(e, names), { urgent: true });
    A.idle = false;
    pose(atk, { ...POSES.win, x: corner(atk, 92) }, 420);
    await wait(500);
    for (let j = 0; j < 3; j++) {
      pose(atk, { y: -10 }, 180);
      await wait(200);
      pose(atk, { y: 0 }, 180);
      await wait(220);
    }
  }

  async function bell(e) {
    slowmo = 1;
    hooks.onRound?.(e.rd);
    hooks.onEvent?.(e, -1);
    hooks.onHp?.(e.ha, e.hb);
    hooks.onStatus?.(e);
    for (const s of ['a', 'b']) {
      F[s].idle = true;
      setKoFace(F[s].n, false);
      pose(s, { ...POSES.guard, x: F[s].home * (e.rd === 1 ? 1.5 : 1.3), rot: 0, y: 0 }, 300);
    }
    play(() => sfx.bell(1));
    big('DING!', { color: '#ffd23f', dur: 900, size: 46 });
    big(`RONDE ${e.rd}`, { color: '#ffffff', dur: 1100, size: 34, y: 92 });
    say(describe(e, names), { urgent: true });
    await wait(e.rd === 1 ? 900 : 700);
    for (const s of ['a', 'b']) pose(s, { x: e.r ? -F[s].facing * RANGE_X[e.r] : F[s].home }, 500);
    await wait(500);
  }

  async function end(e) {
    slowmo = 1;
    hooks.onEvent?.(e, -1);
    hooks.onHp?.(e.ha, e.hb);
    hooks.onStatus?.(e);
    play(() => sfx.bell(3));
    big('WAKTU HABIS', { color: '#ffffff', dur: 1300, size: 32 });
    say(describe(e, names), { urgent: true });
    for (const s of ['a', 'b']) pose(s, { ...POSES.guard, x: F[s].home * 1.2 }, 400);
    await wait(1400);
    if (r.win === 'draw') {
      big('SERI', { color: '#ffffff', dur: 2000, size: 60 });
      return;
    }
    const w = r.win;
    F[w].idle = false;
    pose(w, { ...POSES.win, x: F[w].home * 0.4 }, 420);
    pose(other(w), { ...POSES.hit, x: F[other(w)].home * 1.3 }, 420);
    play(() => sfx.cheer());
    big('MENANG ANGKA', { color: '#ffd23f', dur: 2000, size: 34 });
    await wait(900);
  }

  async function run() {
    play(() => sfx.startCrowd());
    for (let i = 0; i < r.events.length && alive; i++) {
      const e = r.events[i];
      if (!e.j && e.t !== 'jurus') slowmo = 1;
      if (e.t === 'bell') await bell(e);
      else if (e.t === 'end') await end(e);
      else if (e.t === 'card') await card(e);
      else if (e.t === 'move') await move(e, i);
      else if (e.t === 'set' || e.t === 'tired') await breathe(e, i);
      else if (e.t === 'jurus') await jurus(e, i);
      else if (e.t === 'leg') await legWobble(e, i);
      else await exchange(e, i);
    }
    if (!alive) return;
    finalize();
    hooks.onDone?.();
  }

  // Posisi akhir (dipakai juga saat "Lewati").
  function finalize() {
    const lastE = r.events[r.events.length - 1];
    hooks.onHp?.(lastE.ha, lastE.hb);
    hooks.onStatus?.(lastE);
    slowmo = 1;
    dimEl.setAttribute('opacity', '0');
    bannerG.setAttribute('opacity', '0');
    camS.from = null;
    camS.z = 1;
    camS.px = CX;
    camS.py = 125;
    if (r.win === 'draw') return;
    const w = r.win;
    const l = other(w);
    F[w].idle = false;
    const ko = r.how !== 'Angka';
    F[w].from = F[w].to = F[w].cur = { ...POSES.win, x: ko ? corner(w, 92) : F[w].home * 0.4, y: 0, rot: 0 };
    if (ko) {
      F[l].from = F[l].to = F[l].cur = { ...POSES.down, x: corner(l, FALL_X), y: -14, rot: 90 };
      setKoFace(F[l].n, true);
    } else {
      F[l].from = F[l].to = F[l].cur = { ...POSES.hit, x: F[l].home * 1.3, y: 0, rot: 0 };
    }
  }

  let started = false;
  return {
    play() {
      sfx.resume();
      playing = true;
      if (!started) {
        started = true;
        run();
      }
    },
    pause() { playing = false; voice.stop(); },
    get playing() { return playing; },
    setSpeed(s) { speed = s; if (s > 1) voice.stop(); },
    skip() {
      skipping = true;
      voice.stop();
      playing = true;
      for (const w of waits.splice(0)) w.res();
    },
    get muted() { return muted; },
    setMuted(m) {
      muted = m;
      try { localStorage.setItem('wb.sound', m ? '0' : '1'); } catch { /* abaikan */ }
      sfx.setMuted(m);
      voice.setMuted(m);
      if (!m) sfx.resume();
    },
    voiceAvailable: () => voice.available,
    // Browser memblokir audio sampai pengguna menyentuh halaman (mis. link dibuka langsung).
    audioBlocked: () => sfx.ctx?.state === 'suspended',
    resume() {
      sfx.resume();
      if (started && !skipping) sfx.startCrowd();
    },
    destroy() {
      alive = false;
      cancelAnimationFrame(raf);
      voice.stop();
      sfx.close();
      for (const w of waits.splice(0)) w.res();
    },
  };
}
