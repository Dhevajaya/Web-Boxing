// Test kesetaraan mesin JavaScript terhadap vektor yang dibuat oleh PHP.
//   node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Rng, fnv1a } from '../../resources/js/game/rng.js';
import { baseFighter, ovr } from '../../resources/js/game/fighter.js';
import { simulate, signature } from '../../resources/js/game/simulate.js';
import { defaultLook, resolveLook, randomLook } from '../../resources/js/game/looks.js';
import { avatarSvg } from '../../resources/js/game/avatar.js';

const root = new URL('../../', import.meta.url);
const read = (p) => JSON.parse(readFileSync(new URL(p, root), 'utf8'));
const vec = read('tests/fixtures/engine_vectors.json');
const content = read('resources/game/content.json');
const looks = read('resources/game/looks.json');
const rulesCache = {};
const rules = (v) => (rulesCache[v] ??= read(`resources/game/rules.v${v}.json`));

test('rng mulberry32 sama dengan PHP', () => {
  for (const t of vec.rng) {
    const r = new Rng(t.seed);
    assert.deepEqual([r.next(), r.next(), r.next(), r.next(), r.next()], t.next, `seed ${t.seed}`);
  }
});

test('fnv1a sama dengan PHP', () => {
  for (const t of vec.fnv) assert.equal(fnv1a(t.s), t.h, `fnv1a "${t.s}"`);
});

test('petarung dasar dari username sama dengan PHP', () => {
  for (const t of vec.fighters) {
    const R = rules(t.v);
    const b = baseFighter(R, content, t.username);
    assert.deepEqual(
      { style: b.style, base: b.base, nick: b.nick, kota: b.kota, kelas: b.kelas, ovr: ovr(R, b.base) },
      t.expect,
      t.username,
    );
  }
});

test('hasil pertarungan sama dengan PHP', () => {
  for (const [i, t] of vec.fights.entries()) {
    const r = simulate(rules(t.v), t.a, t.b, t.seed);
    assert.equal(r.win, t.expect.win, `laga #${i} pemenang`);
    assert.equal(r.events.length, t.expect.events, `laga #${i} jumlah kejadian`);
    assert.equal(signature(r), t.expect.sig, `laga #${i} sidik jari`);
  }
});

test('tampilan bawaan dari username sama dengan PHP', () => {
  assert.ok(vec.looks?.length > 0, 'vektor tampilan ada');
  for (const t of vec.looks) assert.deepEqual(defaultLook(looks, t.username), t.expect, t.username);
});

test('katalog tampilan: ID unik, pool bawaan hanya berisi item tanpa kunci', () => {
  for (const p of looks.parts) {
    const ids = p.items.map((i) => i.id);
    assert.equal(new Set(ids).size, ids.length, `ID ganda di ${p.key}`);
    for (const id of p.defaultPool) {
      const item = p.items.find((i) => i.id === id);
      assert.ok(item, `${p.key}.${id} di defaultPool tidak ada di items`);
      assert.ok((item.lv ?? 1) <= 1 && !item.supporter, `${p.key}.${id} terkunci, jangan masuk defaultPool`);
    }
  }
});

test('avatar tergambar untuk setiap item tanpa galat', () => {
  const base = defaultLook(looks, 'budi');
  for (const p of looks.parts) {
    for (const item of p.items) {
      const svg = avatarSvg(looks, { ...base, [p.key]: item.id }, { title: `<b>${p.key}</b>` });
      assert.match(svg, /^<svg[\s\S]*<\/svg>$/);
      assert.ok(!svg.includes('undefined'), `${p.key}.${item.id} menghasilkan "undefined"`);
      assert.ok(!svg.includes("<b>") && svg.includes("&lt;b&gt;"), "judul di-escape");
    }
  }
  assert.deepEqual(resolveLook(looks, { hair: 'tidak_ada', skin: 'gelap' }, 'budi'), { ...base, skin: 'gelap' });
  const r = randomLook(looks, 1, () => 0.999);
  for (const p of looks.parts) assert.ok((p.items.find((i) => i.id === r[p.key]).lv ?? 1) <= 1);
});
