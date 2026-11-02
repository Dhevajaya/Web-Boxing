// Petinju tampak samping untuk arena: badan utuh, sendi bisa digerakkan (pose).
// Menghadap kanan; sudut biru dipakai apa adanya, sudut merah dicerminkan.
//
//   const html = boxerMarkup(catalog, look, 'a')   // <g> SVG
//   const nodes = boxerNodes(svgRoot, 'a')
//   applyPose(nodes, { ...POSES.guard, x: 120 }, facing)
//
// Sudut dalam derajat. Lengan: 0 = lurus ke bawah, positif = mengayun ke depan.
// torso/head: positif = condong ke depan. rot: memutar seluruh badan di kaki (jatuh).

import { findItem } from './looks.js';
import { shade } from './avatar.js';

const INK = '#14161b';
const UPPER = 24;
const FORE = 22;

export const POSES = {
  guard: { torso: 4, head: 0, fu: 28, ff: 148, bu: 16, bf: 152, crouch: 3, spread: 1 },
  jab: { torso: 10, head: 4, fu: 96, ff: 4, bu: 18, bf: 150, crouch: 4, spread: 1.1 },
  straight: { torso: 18, head: 6, fu: 24, ff: 150, bu: 98, bf: 2, crouch: 5, spread: 1.25 },
  hook: { torso: 16, head: 4, fu: 92, ff: 46, bu: 16, bf: 150, crouch: 5, spread: 1.15 },
  upper: { torso: 4, head: -2, fu: 28, ff: 148, bu: 74, bf: 104, crouch: 10, spread: 1.15 },
  body: { torso: 26, head: 8, fu: 62, ff: 14, bu: 20, bf: 150, crouch: 12, spread: 1.3 },
  hit: { torso: -10, head: -22, fu: 30, ff: 140, bu: 22, bf: 140, crouch: 4, spread: 1 },
  big: { torso: -20, head: -38, fu: 50, ff: 90, bu: 40, bf: 100, crouch: 8, spread: 0.9 },
  dodge: { torso: -18, head: -10, fu: 26, ff: 150, bu: 14, bf: 152, crouch: 10, spread: 1.05 },
  duck: { torso: 30, head: 10, fu: 30, ff: 150, bu: 20, bf: 150, crouch: 18, spread: 1.2 },
  win: { torso: -4, head: -8, fu: 172, ff: 8, bu: 168, bf: 12, crouch: 0, spread: 0.9 },
  down: { torso: 0, head: -10, fu: 120, ff: 40, bu: 150, bf: 30, crouch: 0, spread: 0.8 },
  rise: { torso: 30, head: 8, fu: 40, ff: 60, bu: 30, bf: 70, crouch: 26, spread: 1.4 },
  // Kickboxing (v2). lt = sudut paha depan dari bawah (positif ke depan), ls = tekukan lutut.
  kick_low: { torso: -6, head: 0, fu: 34, ff: 140, bu: 44, bf: 116, crouch: 6, spread: 1, lt: 58, ls: 8 },
  kick_body: { torso: -16, head: 4, fu: 42, ff: 128, bu: 54, bf: 106, crouch: 4, spread: 0.9, lt: 94, ls: 10 },
  kick_high: { torso: -30, head: 10, fu: 52, ff: 118, bu: 64, bf: 96, crouch: 2, spread: 0.85, lt: 130, ls: 6 },
  teep: { torso: -12, head: 2, fu: 28, ff: 148, bu: 18, bf: 150, crouch: 2, spread: 0.9, lt: 86, ls: 0 },
  chamber: { torso: -4, head: 0, fu: 30, ff: 148, bu: 20, bf: 150, crouch: 4, spread: 0.95, lt: 82, ls: 112 },
  check: { torso: 2, head: 2, fu: 34, ff: 152, bu: 22, bf: 152, crouch: 2, spread: 0.95, lt: 64, ls: 118 },
  cover: { torso: 8, head: 10, fu: 58, ff: 160, bu: 48, bf: 165, crouch: 9, spread: 1.05 },
  tired: { torso: 16, head: 16, fu: 10, ff: 84, bu: 8, bf: 86, crouch: 11, spread: 1.15 },
  wobble: { torso: -8, head: -14, fu: 24, ff: 120, bu: 18, bf: 130, crouch: 14, spread: 0.8, lt: 18, ls: 40 },
};

const KEYS = ['torso', 'head', 'fu', 'ff', 'bu', 'bf', 'crouch', 'spread', 'x', 'y', 'rot', 'lt', 'ls'];
const THIGH = 30;
const SHIN = 34;
const rad = (d) => (d * Math.PI) / 180;

export function lerpPose(a, b, t) {
  const out = {};
  for (const k of KEYS) out[k] = (a[k] ?? 0) + ((b[k] ?? 0) - (a[k] ?? 0)) * t;
  return out;
}

const line = (w = 3) => `stroke="${INK}" stroke-width="${w}" stroke-linejoin="round" stroke-linecap="round"`;

// Rambut tampak samping: kepala berpusat (6,-134) r15, wajah menghadap +x.
const HAIR = {
  botak: () => '',
  cepak: (c) => `<path d="M-9 -134 C-10 -150 4 -153 14 -149 C20 -146 22 -142 21 -139 C14 -143 4 -144 -3 -138 Z" fill="${c}" ${line(2)}/>`,
  spike: (c) => `<path d="M-9 -132 L-16 -144 L-6 -146 L-10 -158 L2 -152 L4 -164 L11 -152 L18 -160 L18 -148 L25 -149 L20 -139 C12 -144 2 -144 -4 -137 Z" fill="${c}" ${line(2)}/>`,
  belah: (c) => `<path d="M-9 -130 C-12 -150 6 -154 16 -150 C22 -147 24 -141 22 -136 C16 -141 10 -142 2 -141 C-2 -138 -6 -134 -9 -130 Z" fill="${c}" ${line(2)}/>`,
  keriting: (c) => [[-6, -140], [0, -148], [9, -150], [17, -146], [-8, -131], [3, -142]].map(([x, y]) => `<circle cx="${x}" cy="${y}" r="7" fill="${c}" ${line(2)}/>`).join('')
    + [[-6, -140], [0, -148], [9, -150], [17, -146], [-8, -131], [3, -142]].map(([x, y]) => `<circle cx="${x}" cy="${y}" r="5.6" fill="${c}"/>`).join(''),
  gondrong: (c) => `<path d="M-10 -136 C-12 -152 6 -154 16 -150 C22 -147 23 -141 21 -138 C14 -142 6 -142 0 -138 C-2 -128 -4 -118 -12 -112 C-16 -120 -14 -130 -10 -136 Z" fill="${c}" ${line(2)}/>`,
  kuncir: (c) => `<path d="M-9 -134 C-10 -150 4 -153 14 -149 C20 -146 22 -142 21 -139 C14 -143 4 -144 -3 -138 Z" fill="${c}" ${line(2)}/><path d="M-9 -140 C-22 -142 -26 -128 -20 -118 C-18 -126 -14 -132 -8 -134 Z" fill="${c}" ${line(2)}/>`,
  mohawk: (c) => `<path d="M-6 -142 C-4 -156 8 -166 18 -160 C14 -156 10 -150 10 -146 Z" fill="${c}" ${line(2)}/>`,
  afro: (c) => `<circle cx="3" cy="-143" r="17" fill="${c}" ${line(2)}/>`,
};

const FACE = {
  serius: () => `<path d="M12 -141 L19 -141" ${line(2)} fill="none"/><circle cx="15.5" cy="-136.5" r="1.8" fill="${INK}"/><path d="M15 -126 L20 -126" ${line(2)} fill="none"/>`,
  senyum: () => `<path d="M12 -142 Q15.5 -144 19 -141.5" ${line(2)} fill="none"/><circle cx="15.5" cy="-136.5" r="1.8" fill="${INK}"/><path d="M14 -127 Q18 -124 21 -127" ${line(2)} fill="none"/>`,
  garang: () => `<path d="M11 -143 L19 -139.5" ${line(2.4)} fill="none"/><path d="M13 -136.5 L18 -136" ${line(2.2)} fill="none"/><path d="M14 -125 Q17 -128 20 -125" ${line(2)} fill="none"/>`,
  santai: () => `<path d="M12 -141 L19 -141" ${line(1.8)} fill="none"/><path d="M13 -136.5 L18 -136.5" ${line(2)} fill="none"/><path d="M15 -126 Q18 -125 20 -127" ${line(2)} fill="none"/>`,
  teriak: () => `<path d="M11 -143 L19 -140" ${line(2.4)} fill="none"/><circle cx="15.5" cy="-136.5" r="1.6" fill="${INK}"/><path d="M14 -129 L21 -129 L20 -123 Q16 -122 14 -125 Z" fill="#5a1717" ${line(1.6)}/>`,
};
const KO_FACE = `<path d="M12.5 -139 L17.5 -134 M17.5 -139 L12.5 -134" ${line(1.8)} fill="none"/><path d="M14 -126 Q17 -128 20 -125" ${line(1.8)} fill="none"/>`;

const BEARD = {
  tidak: () => '',
  kumis: (c) => `<path d="M14 -129.5 Q18 -132 22 -129.5 Q18 -128.5 14 -129.5 Z" fill="${c}" ${line(1.2)}/>`,
  janggut: (c) => `<path d="M12 -124 Q16 -118 21 -122 Q20 -116 14 -116 Q10 -118 12 -124 Z" fill="${c}" ${line(1.4)}/>`,
  brewok: (c) => `<path d="M0 -136 C0 -124 8 -116 16 -117 C21 -118 22 -122 21 -124 C17 -122 14 -124 13 -128 C8 -128 4 -132 3 -137 Z" fill="${c}" ${line(1.4)}/>`,
};

const EXTRA = {
  tidak: () => '',
  ikat: (g) => `<path d="M-9 -141 C0 -147 14 -148 21 -144 L21 -139 C14 -142 0 -142 -9 -136 Z" fill="${g}" ${line(1.6)}/><path d="M-8 -139 L-20 -134 L-17 -144 Z" fill="${shade(g, -0.15)}" ${line(1.4)}/>`,
  plester: () => `<rect x="9" y="-131" width="9" height="4" rx="1.5" transform="rotate(-25 13 -129)" fill="#f2dcb2" ${line(1)}/>`,
  pelindung: (g) => `<path d="M-11 -128 C-14 -150 4 -156 16 -151 C24 -147 25 -140 23 -136 L12 -138 C8 -138 6 -132 6 -124 L-2 -118 C-8 -118 -10 -122 -11 -128 Z" fill="${g}" ${line(2)}/>`,
  kacamata: () => `<path d="M10 -139 L21 -139 L20 -134 Q15 -132 11 -134 Z" fill="#101114" ${line(1.4)}/><path d="M10 -138 L-1 -136" ${line(1.4)} fill="none"/>`,
  mahkota: () => `<path d="M-6 -149 L-8 -162 L0 -155 L6 -166 L12 -155 L20 -162 L18 -149 Z" fill="#f2b52e" ${line(1.8)}/>`,
};

function armMarkup(id, which, skin, glove, far) {
  const s = far ? shade(skin, -0.22) : skin;
  const g = far ? shade(glove, -0.25) : glove;
  return `<g data-j="${id}-${which}u"><path d="M0 0 L0 ${UPPER}" stroke="${INK}" stroke-width="12.5" stroke-linecap="round"/><path d="M0 0 L0 ${UPPER}" stroke="${s}" stroke-width="9.5" stroke-linecap="round"/>
    <g data-j="${id}-${which}f" transform="translate(0 ${UPPER})"><path d="M0 0 L0 ${FORE}" stroke="${INK}" stroke-width="11.5" stroke-linecap="round"/><path d="M0 0 L0 ${FORE}" stroke="${s}" stroke-width="8.5" stroke-linecap="round"/>
      <rect x="-7" y="${FORE - 3}" width="14" height="6" rx="2" fill="#f1efe9" ${line(2)}/>
      <ellipse cx="0" cy="${FORE + 11}" rx="12.5" ry="13.5" fill="${g}" ${line(2.4)}/>
      <path d="M8 ${FORE + 6} Q13 ${FORE + 10} 9 ${FORE + 17}" stroke="${shade(g, -0.3)}" stroke-width="2.2" stroke-linecap="round" fill="none"/>
      <path d="M-6 ${FORE + 4} Q-1 ${FORE + 1} 4 ${FORE + 3}" stroke="${shade(g, 0.4)}" stroke-width="2.4" stroke-linecap="round" fill="none"/>
    </g></g>`;
}

export function boxerMarkup(catalog, look, id) {
  const color = (k, d) => findItem(catalog, k, look[k])?.color ?? d;
  const skin = color('skin', '#c48a59');
  const hairC = color('hairColor', '#1c1918');
  const glove = color('gloves', '#d8352a');
  const trunks = color('trunks', '#2459c4');
  const skinLo = shade(skin, -0.2);
  const helmet = look.extra === 'pelindung';

  // Kaki dihitung ulang di applyPose (lutut menekuk saat jongkok).
  const leg = (k, c) => `<path data-j="${id}-${k}" d="" fill="none" stroke="${INK}" stroke-width="15" stroke-linecap="round" stroke-linejoin="round"/>
    <path data-j="${id}-${k}s" d="" fill="none" stroke="${c}" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/>`;
  // Kaki telanjang dengan perban pergelangan (khas kickboxing kampung).
  const shoe = (k, c) => `<g data-j="${id}-${k}"><path d="M-7 -9 L5 -9 Q16 -6 16 -1 L16 0 L-8 0 Z" fill="${c}" ${line(2.2)}/><rect x="-8" y="-11" width="12" height="5" rx="1.5" fill="#e6d8bd" ${line(1.6)}/></g>`;

  return `<g data-j="${id}-root">
    <ellipse data-j="${id}-shadow" cx="0" cy="2" rx="30" ry="5" fill="#000" opacity=".35"/>
    <g data-j="${id}-body">
      ${leg('lb', skinLo)}${shoe('sb', skinLo)}
      ${leg('lf', skin)}${shoe('sf', skin)}
      <g data-j="${id}-hips">
        <path d="M-17 -16 L17 -16 L21 10 L3 12 L0 4 L-3 12 L-20 10 Z" fill="${trunks}" ${line(2.4)}/>
        <path d="M-17 -16 L17 -16 L17 -10 L-17 -10 Z" fill="${shade(trunks, 0.35)}" ${line(2)}/>
        <g data-j="${id}-torso">
          <g data-j="${id}-bsh" transform="translate(-2 -46)">${armMarkup(id, 'b', skin, glove, true)}</g>
          <path d="M-14 -14 C-18 -30 -18 -44 -14 -50 L14 -52 C18 -44 19 -28 15 -14 Z" fill="${skin}" ${line(2.6)}/>
          <path d="M2 -40 Q10 -38 14 -32" stroke="${skinLo}" stroke-width="2.4" fill="none" stroke-linecap="round"/>
          <path d="M-3 -62 L-3 -48 L7 -48 L7 -62 Z" fill="${skinLo}" ${line(2.2)}/>
          <g data-j="${id}-head" transform="translate(2 -58)">
            <g transform="translate(0 118)">
              ${helmet ? '' : (look.hair === 'gondrong' || look.hair === 'kuncir' ? (HAIR[look.hair]?.(hairC) ?? '') : '')}
              <circle cx="6" cy="-134" r="15" fill="${skin}" ${line(2.6)}/>
              <path d="M19 -138 Q24 -134 20 -130" fill="${skin}" ${line(2)}/>
              <ellipse cx="0" cy="-133" rx="3.4" ry="4.6" fill="${skin}" ${line(1.8)}/>
              ${BEARD[look.beard]?.(hairC) ?? ''}
              <g data-j="${id}-face">${FACE[look.face]?.() ?? FACE.serius()}</g>
              <g data-j="${id}-koface" style="display:none">${KO_FACE}</g>
              ${look.extra === 'ikat' ? EXTRA.ikat(glove) : ''}
              ${helmet ? '' : (look.hair !== 'gondrong' && look.hair !== 'kuncir' ? (HAIR[look.hair]?.(hairC) ?? '') : HAIR.cepak(hairC))}
              ${look.extra && look.extra !== 'ikat' ? (EXTRA[look.extra]?.(glove) ?? '') : ''}
            </g>
          </g>
          <g data-j="${id}-fsh" transform="translate(4 -46)">${armMarkup(id, 'f', skin, glove, false)}</g>
        </g>
      </g>
    </g>
  </g>`;
}

export function boxerNodes(root, id) {
  const q = (k) => root.querySelector(`[data-j="${id}-${k}"]`);
  return {
    root: q('root'), body: q('body'), shadow: q('shadow'), hips: q('hips'), torso: q('torso'), head: q('head'),
    fu: q('fu'), ff: q('ff'), bu: q('bu'), bf: q('bf'),
    lf: q('lf'), lfs: q('lfs'), lb: q('lb'), lbs: q('lbs'), sf: q('sf'), sb: q('sb'),
    face: q('face'), koface: q('koface'),
  };
}

// facing: 1 = menghadap kanan, -1 = menghadap kiri (dicerminkan).
export function applyPose(n, p, facing = 1) {
  const hipY = -62 + (p.crouch ?? 0);
  const spread = p.spread ?? 1;
  const footF = 18 * spread;
  const footB = -16 * spread;
  // Lutut: titik tengah pinggul-kaki digeser ke depan sesuai jongkok.
  const knee = (hx, fx, fwd) => [(hx + fx) / 2 + fwd * (2 + (p.crouch ?? 0) * 0.5), (hipY + -4) / 2];
  const kf = knee(5, footF, 1);
  const kb = knee(-5, footB, 1);
  // Kaki depan menendang: hip -> lutut -> kaki dari sudut lt/ls, dicampur halus dengan kaki berdiri.
  let kneeF = kf;
  let footFx = footF;
  let footFy = -6;
  let shoeRot = 0;
  const lt = p.lt ?? 0;
  if (lt > 0.5) {
    const w = Math.min(1, lt / 22);
    const hy = hipY + 4;
    const kx = 5 + THIGH * Math.sin(rad(lt));
    const ky = hy + THIGH * Math.cos(rad(lt));
    const sa = lt - (p.ls ?? 0);
    const fx = kx + SHIN * Math.sin(rad(sa));
    const fy = ky + SHIN * Math.cos(rad(sa));
    kneeF = [kf[0] + (kx - kf[0]) * w, kf[1] + (ky - kf[1]) * w];
    footFx = footF + (fx - footF) * w;
    footFy = -6 + (fy + 6) * w;
    shoeRot = -sa * w;
  }
  const dF = `M5 ${hipY + 4} L${kneeF[0].toFixed(1)} ${kneeF[1].toFixed(1)} L${footFx.toFixed(1)} ${footFy.toFixed(1)}`;
  const dB = `M-5 ${hipY + 4} L${kb[0]} ${kb[1]} L${footB} -6`;
  n.lf.setAttribute('d', dF);
  n.lfs.setAttribute('d', dF);
  n.lb.setAttribute('d', dB);
  n.lbs.setAttribute('d', dB);
  n.sf.setAttribute('transform', `translate(${footFx.toFixed(1)} ${footFy.toFixed(1)}) rotate(${shoeRot.toFixed(1)}) translate(0 6)`);
  n.sb.setAttribute('transform', `translate(${footB} 0)`);
  n.hips.setAttribute('transform', `translate(0 ${hipY + 16})`);
  n.torso.setAttribute('transform', `rotate(${p.torso ?? 0} 0 -14)`);
  n.head.setAttribute('transform', `translate(2 -58) rotate(${p.head ?? 0} 0 0)`);
  n.fu.setAttribute('transform', `rotate(${-(p.fu ?? 0)})`);
  n.ff.setAttribute('transform', `translate(0 ${UPPER}) rotate(${-(p.ff ?? 0)})`);
  n.bu.setAttribute('transform', `rotate(${-(p.bu ?? 0)})`);
  n.bf.setAttribute('transform', `translate(0 ${UPPER}) rotate(${-(p.bf ?? 0)})`);
  n.body.setAttribute('transform', `rotate(${-(p.rot ?? 0)} 0 0)`);
  n.root.setAttribute('transform', `translate(${p.x ?? 0} ${p.y ?? 0}) scale(${facing} 1)`);
  n.shadow.setAttribute('rx', String(30 + Math.abs(p.rot ?? 0) * 0.5));
}

export function setKoFace(n, on) {
  n.face.style.display = on ? 'none' : '';
  n.koface.style.display = on ? '' : 'none';
}
