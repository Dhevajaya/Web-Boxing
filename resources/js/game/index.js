// Titik masuk mesin game untuk browser. Aturan versi lama tetap disertakan
// agar pertarungan lama bisa diputar ulang dengan versinya sendiri.
import rulesV1 from '../../game/rules.v1.json';
import rulesV2 from '../../game/rules.v2.json';
import { JURUS_NAMES } from './commentary.js';
import content from '../../game/content.json';
import looks from '../../game/looks.json';
import { simulate as simulateWith, signature, winChance as winChanceWith } from './simulate.js';
import { baseFighter as baseWith, effectiveStats, ovr as ovrWith } from './fighter.js';
import { Rng, fnv1a } from './rng.js';
import { defaultLook, resolveLook, randomLook, findItem } from './looks.js';
import { avatarSvg } from './avatar.js';

export const RULES = { 1: rulesV1, 2: rulesV2 };
export const CURRENT_VERSION = Number(globalThis.window?.GAME?.engine?.version ?? 2);
for (const j of rulesV2.jurus) JURUS_NAMES[j.id] = j.name;

// Katalog v2 untuk tampilan (archetype, jurus, nama atribut).
export const ARCHETYPES = Object.fromEntries(rulesV2.archetypes.map((a) => [a.id, a]));
export const JURUS = Object.fromEntries(rulesV2.jurus.map((j) => [j.id, j]));
export const TECH_NAMES = Object.fromEntries(rulesV2.techniques.map((t) => [t.id, t.name]));

export const rules = (v = CURRENT_VERSION) => {
  const r = RULES[v];
  if (!r) throw new Error(`Aturan mesin versi ${v} tidak tersedia di klien`);
  return r;
};

export const baseFighter = (username, v) => baseWith(rules(v), content, username);
export const effective = (base, points, bonus, v) => effectiveStats(rules(v), base, points, bonus);
export const ovr = (stats, v) => ovrWith(rules(v), stats);
export const simulate = (a, b, seed, v) => simulateWith(rules(v), a, b, seed);
export const winChance = (a, b, runs, v) => winChanceWith(rules(v), a, b, runs);
// Input laga untuk username tanpa data pemain (stat dasar + jurus khas). Sama dengan FighterFactory::defaultInput.
export const defaultInput = (username, v = CURRENT_VERSION) => {
  const R = rules(v);
  const b = baseFighter(username, v);
  const input = { style: b.style, stats: b.base };
  if (R.version >= 2) input.jurus = [R.archetypes.find((a) => a.id === b.style).signature];
  return input;
};

// Tampilan avatar (katalog resources/game/looks.json).
export const LOOKS = looks;
export const lookFor = (username, stored) => resolveLook(looks, stored, username);
export const lookDefault = (username) => defaultLook(looks, username);
export const lookRandom = (level) => randomLook(looks, level);
export const lookItem = (key, id) => findItem(looks, key, id);
export const avatar = (look, opts) => avatarSvg(looks, look, opts);

export { signature, Rng, fnv1a, content };
