// Test kesetaraan mesin JavaScript terhadap vektor yang dibuat oleh PHP.
//   node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Rng, fnv1a } from '../../resources/js/game/rng.js';
import { baseFighter, ovr } from '../../resources/js/game/fighter.js';
import { simulate, signature } from '../../resources/js/game/simulate.js';

const root = new URL('../../', import.meta.url);
const read = (p) => JSON.parse(readFileSync(new URL(p, root), 'utf8'));
const vec = read('tests/fixtures/engine_vectors.json');
const content = read('resources/game/content.json');
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
