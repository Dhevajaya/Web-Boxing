// Mesin pertarungan deterministik. Identik dengan app/Game/FightSimulator.php.
// Aturan: hanya integer (idiv), acak hanya dari Rng dengan urutan panggilan yang sama.
import { Rng, fnv1a } from './rng.js';
import { simulateV2 } from './simulate2.js';

const idiv = (a, b) => Math.trunc(a / b);

function styleWeights(rules, style) {
  const s = rules.styles.find((x) => x.id === style);
  return (s ?? rules.styles[0]).weights;
}

export function simulate(rules, a, b, seed) {
  if (rules.version >= 2) return simulateV2(rules, a, b, seed);
  const F = rules.fight;
  const rng = new Rng(seed);
  const st = { a: a.stats, b: b.stats };
  const weights = { a: styleWeights(rules, a.style), b: styleWeights(rules, b.style) };
  const hp = { a: F.hp, b: F.hp };
  const kd = { a: 0, b: 0 };
  const score = { a: 0, b: 0 };
  const events = [];
  const { hit: H, damage: D, big: BIG, knockdown: KD } = F;

  const finish = (win, how, rd) => ({
    win, how, rd, scoreA: score.a, scoreB: score.b, version: rules.version, events,
  });

  for (let rd = 1; rd <= F.rounds; rd++) {
    events.push({ t: 'bell', rd, ha: hp.a, hb: hp.b });
    const dealt = { a: 0, b: 0 };
    const kdRound = { a: 0, b: 0 };

    for (let x = 0; x < F.exchanges; x++) {
      const atk = rng.int(st.a.speed + st.b.speed) < st.a.speed ? 'a' : 'b';
      const def = atk === 'a' ? 'b' : 'a';
      const S = st[atk];
      const T = st[def];

      const P = rules.punches[rng.weighted(weights[atk])];
      const chance = Math.max(H.min, Math.min(H.max, H.base + H.k * (S.speed - T.defense) + P.acc));

      if (rng.int(1000) >= chance) {
        events.push({ t: 'miss', rd, who: atk, p: P.id, ha: hp.a, hb: hp.b });
        continue;
      }

      const fatigue = Math.max(500, 1000 - (rd - 1) * (100 - S.stamina) * F.fatigue.perStaminaPerRound);
      let d = idiv(P.dmg * (D.powerAdd + S.power) * (D.chinSub - T.chin), 10000);
      d = idiv(d * fatigue, 1000);
      d = idiv(d * (D.jitterMin + rng.int(D.jitterRange)), 100);
      const big = rng.int(1000) < BIG.base + S.power;
      if (big) d = idiv(d * BIG.multNum, BIG.multDen);
      d = Math.max(1, d);

      hp[def] = Math.max(0, hp[def] - d);
      dealt[atk] += d;

      if (hp[def] === 0) {
        events.push({ t: 'ko', rd, who: atk, p: P.id, d, ha: hp.a, hb: hp.b, how: 'KO' });
        return finish(atk, 'KO', rd);
      }

      if (big && hp[def] < KD.hpBelow && rng.int(1000) < (KD.chinRef - T.chin) * KD.k) {
        kd[def]++;
        kdRound[def]++;
        if (kd[def] >= KD.maxBeforeTko) {
          events.push({ t: 'ko', rd, who: atk, p: P.id, d, ha: hp.a, hb: hp.b, how: 'TKO' });
          return finish(atk, 'TKO', rd);
        }
        const low = hp[def];
        hp[def] = Math.min(F.hp, hp[def] + KD.recover);
        events.push({ t: 'kd', rd, who: atk, p: P.id, d, low, ha: hp.a, hb: hp.b });
        continue;
      }

      events.push({ t: big ? 'big' : 'hit', rd, who: atk, p: P.id, d, ha: hp.a, hb: hp.b });
    }

    const sc = F.scoring;
    const ra = dealt.a >= dealt.b ? sc.winner : sc.loser;
    const rb = dealt.b >= dealt.a ? sc.winner : sc.loser;
    score.a += ra - kdRound.a * sc.perKnockdown;
    score.b += rb - kdRound.b * sc.perKnockdown;

    if (rd < F.rounds) {
      for (const s of ['a', 'b']) {
        hp[s] = Math.min(F.hp, hp[s] + F.recoverBetweenRounds.base + st[s].stamina);
      }
    }
  }

  events.push({ t: 'end', rd: F.rounds, ha: hp.a, hb: hp.b, sa: score.a, sb: score.b });
  const win = score.a === score.b ? 'draw' : score.a > score.b ? 'a' : 'b';
  return finish(win, 'Angka', F.rounds);
}

// Sidik jari keluaran untuk test kesetaraan PHP dan JavaScript.
export function signature(result) {
  const parts = [result.win, result.how, result.rd, result.scoreA, result.scoreB];
  const v2 = (result.version ?? 1) >= 2;
  for (const e of result.events) {
    const f = [e.t, e.rd, e.who ?? '', e.p ?? '', e.d ?? '', e.ha, e.hb];
    if (v2) f.push(e.j ?? '', e.r, e.na, e.nb, e.ma, e.mb, e.la, e.lb);
    parts.push(f.join(','));
  }
  return fnv1a(parts.join('|')).toString(16).padStart(8, '0');
}

// Peluang menang kasar lewat simulasi berulang (untuk tampilan, bukan untuk hasil resmi).
export function winChance(rules, a, b, runs = 300) {
  let w = 0;
  for (let i = 0; i < runs; i++) {
    const r = simulate(rules, a, b, (i * 2654435761) >>> 0);
    if (r.win === 'a') w += 1;
    else if (r.win === 'draw') w += 0.5;
  }
  return w / runs;
}
