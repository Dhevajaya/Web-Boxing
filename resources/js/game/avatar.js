// Menggambar avatar petarung sebagai SVG dari tampilan (hasil resolveLook) + katalog.
// Murni string, tanpa DOM, jadi bisa dipakai di kartu, editor, dan arena (Fase 4).
//
//   avatarSvg(catalog, look, { size: 120, flip: false, title: '@budi' })

import { findItem } from './looks.js';

const INK = '#14161b';

// Campur warna hex dengan hitam (f < 0) atau putih (f > 0).
export function shade(hex, f) {
  const n = parseInt(hex.slice(1), 16);
  const t = f < 0 ? 0 : 255;
  const p = Math.abs(f);
  const ch = (v) => Math.round((t - v) * p + v);
  const r = ch(n >> 16);
  const g = ch((n >> 8) & 255);
  const b = ch(n & 255);
  return `#${((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1)}`;
}

const line = (extra = '') => `stroke="${INK}" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" ${extra}`;

// ---------------------------------------------------------------- rambut

// Bagian yang tergambar DI BELAKANG kepala.
const HAIR_BACK = {
  gondrong: (c) => `<path d="M64 70 C60 40 80 30 100 30 C120 30 140 40 136 70 L140 122 C132 128 120 126 116 118 L84 118 C80 126 68 128 60 122 Z" fill="${c}" ${line()}/>`,
  kuncir: (c) => `<path d="M128 58 C150 58 156 88 146 112 C142 98 136 84 126 76 Z" fill="${c}" ${line()}/>`,
  afro: (c) => `<circle cx="100" cy="58" r="50" fill="${c}" ${line()}/>`,
};

// Bagian yang tergambar DI ATAS kepala (poni, jambul).
const HAIR_FRONT = {
  botak: (c, skin) => `<path d="M82 50 C88 45 96 43 104 44" fill="none" stroke="${shade(skin, 0.35)}" stroke-width="4" stroke-linecap="round"/>`,
  cepak: (c) => `<path d="M68 72 C64 46 82 36 100 36 C118 36 136 46 132 72 C128 62 116 54 100 54 C84 54 72 62 68 72 Z" fill="${c}" ${line()}/>`,
  spike: (c) => `<path d="M67 74 L64 52 L74 56 L74 38 L86 46 L92 28 L101 42 L110 27 L116 45 L128 37 L127 55 L137 52 L133 74 C128 62 116 56 100 56 C84 56 72 62 67 74 Z" fill="${c}" ${line()}/>`,
  belah: (c) => `<path d="M67 78 C62 48 80 34 102 35 C122 36 138 48 133 76 C130 66 126 60 118 57 C108 62 92 64 72 62 C69 66 68 72 67 78 Z" fill="${c}" ${line()}/><path d="M116 40 C112 48 112 54 118 57" fill="none" stroke="${shade(c, 0.25)}" stroke-width="2"/>`,
  keriting: (c) => {
    const d = [[72, 60], [78, 47], [90, 39], [103, 37], [116, 40], [126, 49], [130, 61], [84, 52], [98, 48], [112, 50]];
    return `<g fill="${c}" ${line()}>${d.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="11"/>`).join('')}</g>`
      + `<g fill="${c}">${d.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="9.5"/>`).join('')}</g>`;
  },
  gondrong: (c) => `<path d="M66 82 C62 46 82 34 100 34 C118 34 138 46 134 82 C130 68 124 60 114 56 C110 64 98 66 86 60 C76 64 70 72 66 82 Z" fill="${c}" ${line()}/>`,
  kuncir: (c) => `<path d="M68 72 C64 46 82 36 100 36 C118 36 136 46 132 72 C128 62 116 54 100 54 C84 54 72 62 68 72 Z" fill="${c}" ${line()}/><rect x="122" y="60" width="9" height="9" rx="3" fill="${shade(c, -0.3)}" ${line('stroke-width="2"')}/>`,
  mohawk: (c, skin) => `<path d="M70 68 C70 52 84 44 100 44 C116 44 130 52 130 68" fill="none" stroke="${shade(skin, -0.15)}" stroke-width="5" stroke-linecap="round"/>`
    + `<path d="M90 58 C88 38 92 20 100 12 C108 20 112 38 110 58 Z" fill="${c}" ${line()}/>`,
  afro: (c) => `<path d="M68 70 C70 56 84 50 100 50 C116 50 130 56 132 70 C124 64 112 62 100 62 C88 62 76 64 68 70 Z" fill="${shade(c, -0.15)}"/>`,
};

// ---------------------------------------------------------------- wajah

const FACES = {
  serius: () => `
    <path d="M80 69 L94 70 M106 70 L120 69" ${line('stroke-width="3.5" fill="none"')}/>
    <ellipse cx="88" cy="80" rx="3.6" ry="4.2" fill="${INK}"/><ellipse cx="112" cy="80" rx="3.6" ry="4.2" fill="${INK}"/>
    <path d="M92 103 L108 103" ${line('fill="none"')}/>`,
  senyum: () => `
    <path d="M80 70 Q87 65 94 69 M106 69 Q113 65 120 70" ${line('stroke-width="3.5" fill="none"')}/>
    <ellipse cx="88" cy="80" rx="3.6" ry="4.2" fill="${INK}"/><ellipse cx="112" cy="80" rx="3.6" ry="4.2" fill="${INK}"/>
    <path d="M88 100 Q100 111 112 100" ${line('fill="#fff"')}/>`,
  garang: () => `
    <path d="M79 66 L95 73 M105 73 L121 66" ${line('stroke-width="4" fill="none"')}/>
    <path d="M83 80 Q88 77 93 80 Q88 83 83 80 Z M107 80 Q112 77 117 80 Q112 83 107 80 Z" fill="${INK}" ${line('stroke-width="2"')}/>
    <path d="M90 106 Q100 99 110 106" ${line('fill="none"')}/>`,
  santai: () => `
    <path d="M80 70 L94 71 M106 71 L120 70" ${line('stroke-width="3" fill="none"')}/>
    <path d="M83 80 L93 80 M107 80 L117 80" ${line('stroke-width="3.5" fill="none"')}/>
    <path d="M92 103 Q101 107 110 100" ${line('fill="none"')}/>`,
  teriak: () => `
    <path d="M79 66 L95 72 M105 72 L121 66" ${line('stroke-width="4" fill="none"')}/>
    <ellipse cx="88" cy="80" rx="3.2" ry="3.2" fill="${INK}"/><ellipse cx="112" cy="80" rx="3.2" ry="3.2" fill="${INK}"/>
    <path d="M89 97 Q100 93 111 97 Q110 113 100 114 Q90 113 89 97 Z" fill="#5a1717" ${line()}/>
    <path d="M91 99 Q100 96 109 99" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>`,
};

const BEARDS = {
  tidak: () => '',
  kumis: (c) => `<path d="M88 97 Q94 92 100 95 Q106 92 112 97 Q106 99 100 97 Q94 99 88 97 Z" fill="${c}" ${line('stroke-width="2"')}/>`,
  janggut: (c) => `<path d="M90 108 Q100 112 110 108 Q110 120 100 124 Q90 120 90 108 Z" fill="${c}" ${line('stroke-width="2.5"')}/>`,
  brewok: (c) => `<path d="M69 86 C70 104 82 122 100 123 C118 122 130 104 131 86 C126 96 122 100 116 100 C112 96 106 95 100 95 C94 95 88 96 84 100 C78 100 74 96 69 86 Z M91 103 Q100 109 109 103 Q100 113 91 103 Z" fill="${c}" fill-rule="evenodd" ${line('stroke-width="2.5"')}/>`,
};

// ---------------------------------------------------------------- aksesori

const EXTRAS_UNDER_HAIR = {
  ikat: (g) => `<path d="M67 66 C80 58 120 58 133 66 L132 75 C120 68 80 68 68 75 Z" fill="${g}" ${line('stroke-width="2.5"')}/>
    <path d="M132 69 L146 63 L142 74 Z M132 71 L148 76 L139 80 Z" fill="${shade(g, -0.15)}" ${line('stroke-width="2"')}/>`,
};

const EXTRAS_OVER = {
  plester: () => `<g transform="rotate(-28 118 95)"><rect x="109" y="91" width="18" height="8" rx="3" fill="#f2dcb2" ${line('stroke-width="2"')}/><path d="M115 93 L115 97 M118 93 L118 97 M121 93 L121 97" stroke="#c9a978" stroke-width="1.4"/></g>`,
  pelindung: (g) => `<path d="M62 92 C56 50 78 30 100 30 C122 30 144 50 138 92 L136 112 C132 118 124 118 122 110 L124 84 C124 70 114 60 100 60 C86 60 76 70 76 84 L78 110 C76 118 68 118 64 112 Z" fill="${g}" ${line()}/>
    <path d="M84 41 C92 36 108 36 116 41" fill="none" stroke="${shade(g, 0.35)}" stroke-width="4" stroke-linecap="round"/>
    <path d="M78 66 C86 60 114 60 122 66" fill="none" stroke="${shade(g, -0.3)}" stroke-width="3"/>`,
  kacamata: () => `<path d="M77 75 L97 75 L95 86 Q87 90 79 86 Z M103 75 L123 75 L121 86 Q113 90 105 86 Z" fill="#101114" ${line('stroke-width="2.5"')}/>
    <path d="M97 77 L103 77 M77 76 L69 74 M123 76 L131 74" ${line('stroke-width="2.5" fill="none"')}/>
    <path d="M81 78 L86 78 M107 78 L112 78" stroke="#ffffff" stroke-opacity=".55" stroke-width="2" stroke-linecap="round"/>`,
  mahkota: () => `<path d="M76 44 L72 18 L87 30 L100 12 L113 30 L128 18 L124 44 Z" fill="#f2b52e" ${line()}/>
    <circle cx="100" cy="32" r="4" fill="#d8352a"/><circle cx="86" cy="37" r="3" fill="#2c6be0"/><circle cx="114" cy="37" r="3" fill="#25935f"/>`,
};

// ---------------------------------------------------------------- tubuh

function glove(cx, cy, color, mirror) {
  const s = mirror ? -1 : 1;
  const hi = shade(color, 0.35);
  const lo = shade(color, -0.25);
  return `<g transform="translate(${cx} ${cy}) scale(${s} 1) rotate(-12)">
    <rect x="-17" y="18" width="30" height="16" rx="5" fill="#f1efe9" ${line()}/>
    <path d="M-12 22 L8 22 M-12 28 L8 28" stroke="#bdb9ad" stroke-width="2"/>
    <path d="M-24 4 C-26 -18 -10 -27 4 -26 C20 -25 28 -12 26 4 C25 16 16 22 2 22 C-12 22 -23 16 -24 4 Z" fill="${color}" ${line()}/>
    <path d="M14 0 C24 -2 30 8 24 16 C20 20 12 18 10 12" fill="${lo}" ${line()}/>
    <path d="M-14 -12 C-10 -18 -2 -20 4 -19" fill="none" stroke="${hi}" stroke-width="4" stroke-linecap="round"/>
  </g>`;
}

export function avatarSvg(catalog, look, { size = 120, flip = false, title = '' } = {}) {
  const color = (k, fallback) => findItem(catalog, k, look[k])?.color ?? fallback;
  const skin = color('skin', '#c48a59');
  const hairC = color('hairColor', '#1c1918');
  const gloves = color('gloves', '#d8352a');
  const trunks = color('trunks', '#2459c4');
  const skinLo = shade(skin, -0.18);
  const hair = look.hair;
  const extra = look.extra;
  const helmet = extra === 'pelindung';

  const body = `
    <path d="M14 200 C18 160 46 136 100 134 C154 136 182 160 186 200 Z" fill="${skin}" ${line()}/>
    <path d="M70 164 C84 170 116 170 130 164" fill="none" stroke="${skinLo}" stroke-width="3" stroke-linecap="round"/>
    <path d="M21 200 L23 181 Q100 168 177 181 L179 200 Z" fill="${trunks}" ${line()}/>
    <path d="M24 186 Q100 174 176 186" fill="none" stroke="${shade(trunks, 0.4)}" stroke-width="4"/>
    <path d="M86 104 L86 136 Q100 144 114 136 L114 104 Z" fill="${skinLo}" ${line()}/>`;

  const head = `
    <ellipse cx="67" cy="86" rx="7" ry="10" fill="${skin}" ${line()}/>
    <ellipse cx="133" cy="86" rx="7" ry="10" fill="${skin}" ${line()}/>
    <path d="M68 76 C68 50 82 40 100 40 C118 40 132 50 132 76 C132 104 120 122 100 122 C80 122 68 104 68 76 Z" fill="${skin}" ${line()}/>
    <path d="M100 82 Q97 90 99 93 Q102 94 104 92" fill="none" stroke="${skinLo}" stroke-width="3" stroke-linecap="round"/>`;

  const parts = [
    body,
    helmet ? '' : (HAIR_BACK[hair]?.(hairC) ?? ''),
    head,
    BEARDS[look.beard]?.(hairC) ?? '',
    FACES[look.face]?.() ?? FACES.serius(),
    helmet ? '' : (EXTRAS_UNDER_HAIR[extra]?.(gloves) ?? ''),
    helmet ? '' : (HAIR_FRONT[hair]?.(hairC, skin) ?? ''),
    EXTRAS_OVER[extra]?.(gloves) ?? '',
    glove(60, 156, gloves, false),
    glove(140, 156, gloves, true),
  ].join('');

  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
  return `<svg class="avatar" viewBox="0 0 200 200" width="${size}" height="${size}" role="img" aria-label="${esc(title || 'Avatar petarung')}" xmlns="http://www.w3.org/2000/svg">`
    + `${title ? `<title>${esc(title)}</title>` : ''}`
    + `<g${flip ? ' transform="translate(200 0) scale(-1 1)"' : ''}>${parts}</g></svg>`;
}
