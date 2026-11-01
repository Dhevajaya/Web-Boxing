// Petarung dasar dari username. Identik dengan app/Game/FighterFactory.php.
import { Rng } from './rng.js';

export function baseFighter(rules, content, username) {
  const f = rules.fighter;
  const u = String(username).toLowerCase();
  const rng = Rng.fromString(`${f.salt}|${u}`);

  const style = rules.styles[rng.int(rules.styles.length)];
  const base = {};
  for (const k of rules.stats) {
    const v = f.baseMin + rng.int(f.baseRange) + (style.bias[k] ?? 0);
    base[k] = Math.max(f.clampMin, Math.min(f.clampMax, v));
  }

  return {
    username: u,
    style: style.id,
    styleName: style.name,
    base,
    nick: content.nicks[rng.int(content.nicks.length)],
    kota: content.kota[rng.int(content.kota.length)],
    kelas: content.kelas[rng.int(content.kelas.length)],
  };
}

export function effectiveStats(rules, base, points = {}, bonus = {}) {
  const out = {};
  for (const k of rules.stats) {
    out[k] = Math.min(rules.statMax, Math.max(1, (base[k] | 0) + (points[k] | 0) + (bonus[k] | 0)));
  }
  return out;
}

export function ovr(rules, stats) {
  let sum = 0;
  for (const [k, w] of Object.entries(rules.ovr)) sum += w * stats[k];
  return Math.floor((sum + 50) / 100);
}
