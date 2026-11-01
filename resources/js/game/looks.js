// Tampilan avatar dari katalog resources/game/looks.json. Identik dengan app/Game/Looks.php.
import { Rng } from './rng.js';

export function defaultLook(catalog, username) {
  const rng = Rng.fromString(`${catalog.salt}|${String(username).toLowerCase()}`);
  const out = {};
  for (const p of catalog.parts) out[p.key] = p.defaultPool[rng.int(p.defaultPool.length)];
  return out;
}

export function findItem(catalog, key, id) {
  const p = catalog.parts.find((x) => x.key === key);
  return p?.items.find((i) => i.id === id) ?? null;
}

export function resolveLook(catalog, stored, username) {
  const out = defaultLook(catalog, username);
  for (const [k, id] of Object.entries(stored ?? {})) {
    if (typeof id === 'string' && findItem(catalog, k, id)) out[k] = id;
  }
  return out;
}

// Acak di antara item yang sudah terbuka pada level ini.
export function randomLook(catalog, level, rnd = Math.random) {
  const out = {};
  for (const p of catalog.parts) {
    const open = p.items.filter((i) => (i.lv ?? 1) <= level);
    out[p.key] = open[Math.floor(rnd() * open.length)].id;
  }
  return out;
}
