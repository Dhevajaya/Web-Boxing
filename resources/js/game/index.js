// Titik masuk mesin game untuk browser. Aturan versi lama tetap disertakan
// agar pertarungan lama bisa diputar ulang dengan versinya sendiri.
import rulesV1 from '../../game/rules.v1.json';
import content from '../../game/content.json';
import { simulate as simulateWith, signature, winChance as winChanceWith } from './simulate.js';
import { baseFighter as baseWith, effectiveStats, ovr as ovrWith } from './fighter.js';
import { Rng, fnv1a } from './rng.js';

export const RULES = { 1: rulesV1 };
export const CURRENT_VERSION = 1;

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

export { signature, Rng, fnv1a, content };
