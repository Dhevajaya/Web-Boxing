// Mesin laga KAMPUNG TENDANG (aturan v2). Kembaran persis app/Game/FightSimulatorV2.php:
// hanya integer, acak hanya dari Rng, dan URUTAN pemanggilan acak harus sama.
import { Rng } from './rng.js';

const idiv = (a, b) => Math.trunc(a / b);
const other = (s) => (s === 'a' ? 'b' : 'a');

export function simulateV2(R, a, b, seed) {
  const F = R.fight;
  const rng = new Rng(seed);
  const tech = Object.fromEntries(R.techniques.map((t) => [t.id, t]));
  const jurusDef = Object.fromEntries(R.jurus.map((j) => [j.id, j]));
  const archetype = (id) => R.archetypes.find((x) => x.id === id) ?? R.archetypes[0];
  const equip = (ids = []) => {
    const out = [];
    for (const id of ids) if (jurusDef[id] && out.length < R.jurusSlots) out.push(jurusDef[id]);
    return out;
  };

  const st = { a: a.stats, b: b.stats };
  const arch = { a: archetype(a.style), b: archetype(b.style) };
  const jur = { a: equip(a.jurus), b: equip(b.jurus) };
  const S = F.stamina;
  const hp = { a: F.hp, b: F.hp };
  const staMax = { a: S.base + S.perStat * st.a.stamina, b: S.base + S.perStat * st.b.stamina };
  const sta = { ...staMax };
  const leg = { a: 0, b: 0 };
  const meter = { a: 0, b: 0 };
  const kd = { a: 0, b: 0 };
  const clean = { a: 0, b: 0 };
  const guardUp = { a: false, b: false };
  const counterUp = { a: false, b: false };
  const dealt = { a: 0, b: 0 };
  let dealtRound = { a: 0, b: 0 };
  let kdRound = { a: 0, b: 0 };
  let pts = { a: 0, b: 0 };
  let used = { a: false, b: false };
  let range = F.startRange;
  let rd = 1;
  let over = null;
  const events = [];
  const score = { a: 0, b: 0 };
  const rounds = [];

  const push = (e) => events.push({
    ...e, rd, ha: hp.a, hb: hp.b, na: sta.a, nb: sta.b, ma: meter.a, mb: meter.b, la: leg.a, lb: leg.b, r: range,
  });
  const ranges = R.ranges;
  const toward = (from, to) => { const i = ranges.indexOf(from); const j = ranges.indexOf(to); return ranges[i + Math.sign(j - i)]; };
  const closer = (x, y) => ranges.indexOf(x) < ranges.indexOf(y);
  const speed = (w) => {
    let s = st[w].speed;
    if (leg[w] >= F.leg.slowAt) s = idiv(s * F.leg.slowSpeedPm, 1000);
    return Math.max(1, s);
  };
  const gain = (w, n) => {
    const pm = arch[w].passive.meterPm ?? 1000;
    meter[w] = Math.min(R.meter.max, meter[w] + idiv(n * pm, 1000));
  };
  const cost = (w, T) => {
    const pm = T.cat === 'hand' ? (arch[w].passive.handStaminaPm ?? 1000) : 1000;
    return Math.max(1, idiv(T.stamina * pm, 1000));
  };
  const legDmg = (T, D) => Math.max(1, idiv(T.leg * (1000 - F.leg.balanceSubPm * D.balance), 1000));
  const hurtLeg = (w, n) => {
    const before = leg[w];
    leg[w] = Math.min(F.leg.max, leg[w] + n);
    if (before < F.leg.slowAt && leg[w] >= F.leg.slowAt) push({ t: 'leg', who: w });
  };
  const stop = (win, how, p, why) => {
    push({ t: 'ko', who: win, p, how, why });
    over = { win, how };
  };

  const readyJurus = (w, kinds, trigger) => {
    if (used[w]) return null;
    const opp = other(w);
    for (const J of jur[w]) {
      if (!kinds.includes(J.kind) || (J.trigger ?? null) !== trigger) continue;
      if (meter[w] < J.meter || sta[w] < J.stamina || !J.ranges.includes(range)) continue;
      const req = J.requires ?? {};
      if (req.round !== undefined && rd < req.round) continue;
      if (req.oppHpBelowPm !== undefined && hp[opp] * 1000 >= req.oppHpBelowPm * F.hp) continue;
      return J;
    }
    return null;
  };

  const runJurus = (w, J) => {
    meter[w] -= J.meter;
    sta[w] = Math.max(0, sta[w] - J.stamina);
    used[w] = true;
    push({ t: 'jurus', who: w, j: J.id });
    for (const step of J.steps) {
      attack(w, tech[step], J);
      if (over) return;
    }
  };

  function attack(atk, T, J) {
    const def = other(atk);
    const A = st[atk];
    const D = st[def];
    const jid = J ? J.id : null;

    if (!J && T.checkable) {
      const C = R.defenses.check;
      if (rng.int(1000) < C.chanceBase + C.perStat * D.balance) {
        const refl = idiv(C.reflectLeg * (1000 - F.leg.balanceSubPm * A.balance), 1000);
        hurtLeg(atk, Math.max(1, refl));
        gain(def, C.meter);
        push({ t: 'check', who: atk, p: T.id });
        if (leg[atk] >= F.leg.tkoAt) { stop(def, 'TKO', T.id, 'leg'); return; }
        if (!over) {
          const cj = readyJurus(def, ['counter'], 'after_check');
          if (cj) runJurus(def, cj);
        }
        return;
      }
    }

    const H = F.hit;
    let chance = H.base + T.acc + H.accPerStat * (A.accuracy - H.center) - H.evadePerStat * (D.agility - H.center);
    if (T.cat === 'kick') chance += arch[atk].passive.kickAcc ?? 0;
    const counter = counterUp[atk];
    counterUp[atk] = false;
    if (counter) chance += arch[atk].passive.counterAcc ?? 0;
    if (J) chance += J.accBonus + F.big.jurusTechPerStat * (A.technique - H.center);
    if (hp[atk] < hp[def]) chance += F.composure.losingAccPerStat * A.composure;
    if (sta[atk] < F.stamina.tiredBelow) chance = idiv(chance * (F.stamina.tiredAccPm + F.stamina.tiredComposurePm * A.composure), 1000);
    chance = Math.max(H.min, Math.min(H.max, chance));

    if (rng.int(1000) >= chance) {
      gain(def, R.defenses.evade.meter);
      if (arch[def].passive.counterAcc) counterUp[def] = true;
      push({ t: 'miss', who: atk, p: T.id, j: jid });
      if (!J) {
        const cj = readyJurus(def, ['counter'], 'after_evade');
        if (cj) runJurus(def, cj);
      }
      return;
    }

    const Dm = F.damage;
    let d = idiv(T.dmg * (Dm.strengthAdd + A.strength) * (Dm.enduranceSub - D.endurance), 10000);
    if (T.cat === 'kick') d = idiv(d * (1000 - F.leg.balanceKickPm * (D.balance - H.center)), 1000);
    if (J) d = idiv(d * Math.min(J.dmgPm, R.jurusDmgCapPm), 1000);
    if (counter) d = idiv(d * (arch[atk].passive.counterDmgPm ?? 1000), 1000);
    if (sta[atk] < F.stamina.tiredBelow) d = idiv(d * (F.stamina.tiredDmgPm + F.stamina.tiredComposurePm * A.composure), 1000);
    if (rd === F.rounds) d = idiv(d * (1000 + F.composure.lastRoundDmgPerStat * A.composure), 1000);
    d = idiv(d * (Dm.jitterMin + rng.int(Dm.jitterRange)), 100);

    const G = R.defenses.guard;
    const gc = G.chanceBase + G.perStat * D.defense + (guardUp[def] ? G.upBonus : 0);
    guardUp[def] = false;
    if (rng.int(1000) < gc) {
      d = Math.max(1, idiv(d * G.dmgTakenPm, 1000));
      hp[def] = Math.max(0, hp[def] - d);
      dealt[atk] += d;
      dealtRound[atk] += d;
      gain(def, G.meter);
      if (T.leg) hurtLeg(def, idiv(legDmg(T, D), 2));
      push({ t: 'block', who: atk, p: T.id, d, j: jid });
      if (hp[def] === 0) stop(atk, 'KO', T.id, 'hp');
      else if (leg[def] >= F.leg.tkoAt) stop(atk, 'TKO', T.id, 'leg');
      return;
    }

    const B = F.big;
    const big = rng.int(1000) < B.base + B.techniquePerStat * A.technique;
    if (big) d = idiv(d * B.multPm, 1000);
    d = Math.max(1, d);
    hp[def] = Math.max(0, hp[def] - d);
    dealt[atk] += d;
    dealtRound[atk] += d;
    pts[atk] += T.score;
    clean[atk]++;
    gain(atk, R.meter.cleanHit + idiv(A.technique, R.meter.techniqueDiv));
    gain(def, R.meter.takeHit);
    if (T.push || J?.push) range = toward(range, 'far');
    if (T.leg) hurtLeg(def, legDmg(T, D));

    if (hp[def] === 0) {
      push({ t: 'ko', who: atk, p: T.id, d, j: jid, how: 'KO', why: 'hp' });
      over = { win: atk, how: 'KO' };
      return;
    }

    const K = F.knockdown;
    const kdTry = (big && hp[def] < K.hpBelow) || (d >= K.heavyDmg && hp[def] < K.heavyHpBelow);
    if (kdTry && rng.int(1000) < (K.enduranceRef - D.endurance - idiv(D.balance, K.balanceDiv)) * K.k) {
      kd[def]++;
      kdRound[def]++;
      if (kdRound[def] >= K.maxPerRoundTko) {
        push({ t: 'ko', who: atk, p: T.id, d, j: jid, how: 'TKO', why: 'kd' });
        over = { win: atk, how: 'TKO' };
        return;
      }
      const low = hp[def];
      hp[def] = Math.min(F.hp, hp[def] + K.recover + K.balanceRecoverPerStat * D.balance);
      push({ t: 'kd', who: atk, p: T.id, d, j: jid, low });
      return;
    }

    push({ t: big ? 'big' : 'hit', who: atk, p: T.id, d, j: jid });
    if (leg[def] >= F.leg.tkoAt) stop(atk, 'TKO', T.id, 'leg');
  }

  const setBreath = (w) => {
    const Dd = R.defenses.set;
    sta[w] = Math.min(staMax[w], sta[w] + Dd.stamina);
    gain(w, Dd.meter);
    guardUp[w] = true;
    push({ t: 'set', who: w });
  };

  const move = (w, to) => {
    const step = arch[other(w)].passive.stepPm ?? 0;
    if (step > 0 && closer(to, range) && rng.int(1000) < step) {
      push({ t: 'move', who: w, r: range, x: 1 });
      return;
    }
    range = to;
    push({ t: 'move', who: w, r: to });
  };

  function tick() {
    const spA = speed('a');
    const spB = speed('b');
    const atk = rng.int(spA + spB) < spA ? 'a' : 'b';

    const j = readyJurus(atk, ['combo', 'finisher'], null);
    if (j) { runJurus(atk, j); return; }

    const A = arch[atk];
    const p = A.prefs;
    const cat = ['hand', 'kick', 'defend', 'step'][rng.weighted([p.hand, p.kick, p.defend, p.step])];
    if (cat === 'defend') { setBreath(atk); return; }
    if (cat === 'step') {
      if (A.rangePref === range) { setBreath(atk); return; }
      move(atk, toward(range, A.rangePref));
      return;
    }
    const options = (c) => R.techniques.filter((t) => t.cat === c && t.ranges.includes(range));
    let opts = options(cat);
    if (!opts.length) opts = options(cat === 'hand' ? 'kick' : 'hand');
    if (!opts.length) { move(atk, toward(range, 'mid')); return; }
    const T = opts[rng.int(opts.length)];
    const c = cost(atk, T);
    if (sta[atk] < c) {
      push({ t: 'tired', who: atk });
      sta[atk] = Math.min(staMax[atk], sta[atk] + R.defenses.set.stamina);
      return;
    }
    sta[atk] -= c;
    attack(atk, T, null);
  }

  const result = (win, how) => ({
    win, how, rd, scoreA: score.a, scoreB: score.b, version: R.version, rounds, clean, events,
  });

  for (rd = 1; rd <= F.rounds; rd++) {
    kdRound = { a: 0, b: 0 };
    pts = { a: 0, b: 0 };
    used = { a: false, b: false };
    dealtRound = { a: 0, b: 0 };
    push({ t: 'bell' });
    for (let t = 0; t < F.ticks; t++) {
      tick();
      if (over) return result(over.win, over.how);
      for (const s of ['a', 'b']) sta[s] = Math.min(staMax[s], sta[s] + S.recoverTick);
    }
    const sc = F.scoring;
    const pa = pts.a + kdRound.b * sc.knockdownPoints;
    const pb = pts.b + kdRound.a * sc.knockdownPoints;
    const wa = pa > pb || (pa === pb && dealtRound.a >= dealtRound.b);
    const wb = pb > pa || (pa === pb && dealtRound.b >= dealtRound.a);
    const ra = (wa ? sc.winner : sc.loser) - kdRound.a * sc.perKnockdown;
    const rb = (wb ? sc.winner : sc.loser) - kdRound.b * sc.perKnockdown;
    score.a += ra;
    score.b += rb;
    rounds.push([ra, rb]);
    push({ t: 'card', sa: ra, sb: rb, pa, pb });
    if (rd < F.rounds) {
      for (const s of ['a', 'b']) {
        hp[s] = Math.min(F.hp, hp[s] + F.recoverHp.base + F.recoverHp.perStat * st[s].endurance);
        sta[s] = Math.min(staMax[s], sta[s] + S.recoverRound);
      }
    }
  }
  rd = F.rounds;
  let win = score.a === score.b ? 'draw' : score.a > score.b ? 'a' : 'b';
  if (win === 'draw' && dealt.a !== dealt.b) win = dealt.a > dealt.b ? 'a' : 'b';
  else if (win === 'draw' && clean.a !== clean.b) win = clean.a > clean.b ? 'a' : 'b';
  push({ t: 'end', sa: score.a, sb: score.b, win });
  return result(win, 'Angka');
}
