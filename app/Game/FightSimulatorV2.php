<?php

namespace App\Game;

/**
 * Mesin laga KAMPUNG TENDANG (aturan v2, kickboxing original).
 *
 * Kembaran persis resources/js/game/simulate2.js: hanya integer, acak hanya dari Rng,
 * dan URUTAN pemanggilan acak harus sama. Setiap perubahan di sini wajib diulang di JS
 * lalu diuji dengan tests/fixtures/engine_vectors.json (php tools/make-vectors.php).
 *
 * Input per sisi: ['style' => archetype, 'stats' => 10 atribut, 'jurus' => [id, id]].
 * Satu ronde = beberapa tick; tiap tick satu petarung mengambil inisiatif dan beraksi.
 */
final class FightSimulatorV2
{
    private array $R;

    private array $F;

    private Rng $rng;

    private array $st;

    private array $arch;

    private array $jur;

    private array $tech;

    private array $jurusDef;

    private array $hp;

    private array $sta;

    private array $staMax;

    private array $leg;

    private array $meter;

    private array $kd;

    private array $kdRound;

    private array $pts;

    private array $clean;

    private array $used;

    private array $guardUp;

    private array $counterUp;

    private array $dealt;

    private array $dealtRound;

    private string $range;

    private int $rd;

    private array $events;

    private ?array $over;

    public function simulate(array $R, array $a, array $b, int $seed): array
    {
        $this->R = $R;
        $this->F = $F = $R['fight'];
        $this->rng = new Rng($seed);
        $this->tech = [];
        foreach ($R['techniques'] as $t) {
            $this->tech[$t['id']] = $t;
        }
        $this->jurusDef = [];
        foreach ($R['jurus'] as $j) {
            $this->jurusDef[$j['id']] = $j;
        }
        $this->st = ['a' => $a['stats'], 'b' => $b['stats']];
        $this->arch = ['a' => $this->archetype($a['style'] ?? null), 'b' => $this->archetype($b['style'] ?? null)];
        $this->jur = ['a' => $this->equip($a['jurus'] ?? []), 'b' => $this->equip($b['jurus'] ?? [])];
        $this->hp = ['a' => $F['hp'], 'b' => $F['hp']];
        $S = $F['stamina'];
        $this->staMax = ['a' => $S['base'] + $S['perStat'] * $this->st['a']['stamina'], 'b' => $S['base'] + $S['perStat'] * $this->st['b']['stamina']];
        $this->sta = $this->staMax;
        $this->leg = ['a' => 0, 'b' => 0];
        $this->meter = ['a' => 0, 'b' => 0];
        $this->kd = ['a' => 0, 'b' => 0];
        $this->clean = ['a' => 0, 'b' => 0];
        $this->guardUp = ['a' => false, 'b' => false];
        $this->counterUp = ['a' => false, 'b' => false];
        $this->dealt = ['a' => 0, 'b' => 0];
        $this->range = $F['startRange'];
        $this->events = [];
        $this->over = null;
        $score = ['a' => 0, 'b' => 0];
        $rounds = [];

        for ($this->rd = 1; $this->rd <= $F['rounds']; $this->rd++) {
            $this->kdRound = ['a' => 0, 'b' => 0];
            $this->pts = ['a' => 0, 'b' => 0];
            $this->used = ['a' => false, 'b' => false];
            $this->dealtRound = ['a' => 0, 'b' => 0];
            $this->push(['t' => 'bell']);

            for ($tick = 0; $tick < $F['ticks']; $tick++) {
                $this->tick();
                if ($this->over) {
                    return $this->result($this->over['win'], $this->over['how'], $score, $rounds);
                }
                foreach (['a', 'b'] as $s) {
                    $this->sta[$s] = min($this->staMax[$s], $this->sta[$s] + $S['recoverTick']);
                }
            }

            // Kartu ronde: poin serangan bersih + poin jatuh. Pemenang 10, kalah 9; tiap jatuh -1.
            $sc = $F['scoring'];
            $pa = $this->pts['a'] + $this->kdRound['b'] * $sc['knockdownPoints'];
            $pb = $this->pts['b'] + $this->kdRound['a'] * $sc['knockdownPoints'];
            // Poin sama: yang memberi kerusakan lebih banyak menang ronde.
            $wa = $pa > $pb || ($pa === $pb && $this->dealtRound['a'] >= $this->dealtRound['b']);
            $wb = $pb > $pa || ($pa === $pb && $this->dealtRound['b'] >= $this->dealtRound['a']);
            $ra = ($wa ? $sc['winner'] : $sc['loser']) - $this->kdRound['a'] * $sc['perKnockdown'];
            $rb = ($wb ? $sc['winner'] : $sc['loser']) - $this->kdRound['b'] * $sc['perKnockdown'];
            $score['a'] += $ra;
            $score['b'] += $rb;
            $rounds[] = [$ra, $rb];
            $this->push(['t' => 'card', 'sa' => $ra, 'sb' => $rb, 'pa' => $pa, 'pb' => $pb]);

            if ($this->rd < $F['rounds']) {
                foreach (['a', 'b'] as $s) {
                    $this->hp[$s] = min($F['hp'], $this->hp[$s] + $F['recoverHp']['base'] + $F['recoverHp']['perStat'] * $this->st[$s]['endurance']);
                    $this->sta[$s] = min($this->staMax[$s], $this->sta[$s] + $S['recoverRound']);
                }
            }
        }
        $this->rd = $F['rounds'];

        $win = $score['a'] === $score['b'] ? 'draw' : ($score['a'] > $score['b'] ? 'a' : 'b');
        if ($win === 'draw' && $this->dealt['a'] !== $this->dealt['b']) {
            $win = $this->dealt['a'] > $this->dealt['b'] ? 'a' : 'b';
        } elseif ($win === 'draw' && $this->clean['a'] !== $this->clean['b']) {
            $win = $this->clean['a'] > $this->clean['b'] ? 'a' : 'b';
        }
        $this->push(['t' => 'end', 'sa' => $score['a'], 'sb' => $score['b'], 'win' => $win]);

        return $this->result($win, 'Angka', $score, $rounds);
    }

    // ------------------------------------------------------------------ satu tick

    private function tick(): void
    {
        $spA = $this->speed('a');
        $spB = $this->speed('b');
        $atk = $this->rng->int($spA + $spB) < $spA ? 'a' : 'b';
        $def = $this->other($atk);

        // Jurus kombinasi/pamungkas keluar begitu meter cukup dan syarat terpenuhi.
        $j = $this->readyJurus($atk, ['combo', 'finisher'], null);
        if ($j) {
            $this->runJurus($atk, $j);

            return;
        }

        $A = $this->arch[$atk];
        $p = $A['prefs'];
        $cat = ['hand', 'kick', 'defend', 'step'][$this->rng->weighted([$p['hand'], $p['kick'], $p['defend'], $p['step']])];

        if ($cat === 'defend') {
            $this->setBreath($atk);

            return;
        }
        if ($cat === 'step') {
            $want = $A['rangePref'];
            if ($want === $this->range) {
                $this->setBreath($atk);

                return;
            }
            $this->move($atk, $this->toward($this->range, $want));

            return;
        }

        $opts = $this->options($cat);
        if (! $opts) {
            // Jarak tidak cocok: pakai jenis serangan lain yang bisa, baru mendekat bila tidak ada.
            $opts = $this->options($cat === 'hand' ? 'kick' : 'hand');
        }
        if (! $opts) {
            $this->move($atk, $this->toward($this->range, 'mid'));

            return;
        }
        $T = $opts[$this->rng->int(count($opts))];
        $cost = $this->cost($atk, $T);
        if ($this->sta[$atk] < $cost) {
            $this->push(['t' => 'tired', 'who' => $atk]);
            $this->sta[$atk] = min($this->staMax[$atk], $this->sta[$atk] + $this->R['defenses']['set']['stamina']);

            return;
        }
        $this->sta[$atk] -= $cost;
        $this->attack($atk, $T, null);
    }

    private function options(string $cat): array
    {
        $opts = [];
        foreach ($this->R['techniques'] as $t) {
            if ($t['cat'] === $cat && in_array($this->range, $t['ranges'], true)) {
                $opts[] = $t;
            }
        }

        return $opts;
    }

    private function setBreath(string $who): void
    {
        $D = $this->R['defenses']['set'];
        $this->sta[$who] = min($this->staMax[$who], $this->sta[$who] + $D['stamina']);
        $this->gain($who, $D['meter']);
        $this->guardUp[$who] = true;
        $this->push(['t' => 'set', 'who' => $who]);
    }

    private function move(string $who, string $to): void
    {
        $opp = $this->other($who);
        $step = $this->arch[$opp]['passive']['stepPm'] ?? 0;
        // Jaga Jarak: lawan yang mendekat bisa digagalkan.
        if ($step > 0 && $this->closer($to, $this->range) && $this->rng->int(1000) < $step) {
            $this->push(['t' => 'move', 'who' => $who, 'r' => $this->range, 'x' => 1]);

            return;
        }
        $this->range = $to;
        $this->push(['t' => 'move', 'who' => $who, 'r' => $to]);
    }

    // ------------------------------------------------------------------ serangan

    /** @param array|null $J jurus yang sedang berjalan (bonus akurasi, pengali kerusakan) */
    private function attack(string $atk, array $T, ?array $J): void
    {
        $def = $this->other($atk);
        $F = $this->F;
        $A = $this->st[$atk];
        $D = $this->st[$def];
        $jid = $J['id'] ?? null;

        // Check: hanya untuk low kick, hanya di luar jurus.
        if (! $J && ! empty($T['checkable'])) {
            $C = $this->R['defenses']['check'];
            if ($this->rng->int(1000) < $C['chanceBase'] + $C['perStat'] * $D['balance']) {
                $refl = intdiv($C['reflectLeg'] * (1000 - $F['leg']['balanceSubPm'] * $A['balance']), 1000);
                $this->hurtLeg($atk, max(1, $refl));
                $this->gain($def, $C['meter']);
                $this->push(['t' => 'check', 'who' => $atk, 'p' => $T['id']]);
                if ($this->leg[$atk] >= $F['leg']['tkoAt']) {
                    $this->stop($def, 'TKO', $T['id'], 'leg');

                    return;
                }
                if (! $this->over && ($cj = $this->readyJurus($def, ['counter'], 'after_check'))) {
                    $this->runJurus($def, $cj);
                }

                return;
            }
        }

        $H = $F['hit'];
        $chance = $H['base'] + $T['acc'] + $H['accPerStat'] * ($A['accuracy'] - $H['center']) - $H['evadePerStat'] * ($D['agility'] - $H['center']);
        if ($T['cat'] === 'kick') {
            $chance += $this->arch[$atk]['passive']['kickAcc'] ?? 0;
        }
        $counter = $this->counterUp[$atk];
        $this->counterUp[$atk] = false;
        if ($counter) {
            $chance += $this->arch[$atk]['passive']['counterAcc'] ?? 0;
        }
        if ($J) {
            $chance += $J['accBonus'] + $F['big']['jurusTechPerStat'] * ($A['technique'] - $H['center']);
        }
        if ($this->hp[$atk] < $this->hp[$def]) {
            $chance += $F['composure']['losingAccPerStat'] * $A['composure'];
        }
        if ($this->sta[$atk] < $F['stamina']['tiredBelow']) {
            $chance = intdiv($chance * ($F['stamina']['tiredAccPm'] + $F['stamina']['tiredComposurePm'] * $A['composure']), 1000);
        }
        $chance = max($H['min'], min($H['max'], $chance));

        if ($this->rng->int(1000) >= $chance) {
            $this->gain($def, $this->R['defenses']['evade']['meter']);
            if (! empty($this->arch[$def]['passive']['counterAcc'])) {
                $this->counterUp[$def] = true;
            }
            $this->push(['t' => 'miss', 'who' => $atk, 'p' => $T['id'], 'j' => $jid]);
            if (! $J && ($cj = $this->readyJurus($def, ['counter'], 'after_evade'))) {
                $this->runJurus($def, $cj);
            }

            return;
        }

        // Kerusakan dasar.
        $Dm = $F['damage'];
        $d = intdiv($T['dmg'] * ($Dm['strengthAdd'] + $A['strength']) * ($Dm['enduranceSub'] - $D['endurance']), 10000);
        if ($T['cat'] === 'kick') {
            $d = intdiv($d * (1000 - $F['leg']['balanceKickPm'] * ($D['balance'] - $H['center'])), 1000);
        }
        if ($J) {
            $d = intdiv($d * min($J['dmgPm'], $this->R['jurusDmgCapPm']), 1000);
        }
        if ($counter) {
            $d = intdiv($d * ($this->arch[$atk]['passive']['counterDmgPm'] ?? 1000), 1000);
        }
        if ($this->sta[$atk] < $F['stamina']['tiredBelow']) {
            $d = intdiv($d * ($F['stamina']['tiredDmgPm'] + $F['stamina']['tiredComposurePm'] * $A['composure']), 1000);
        }
        if ($this->rd === $F['rounds']) {
            $d = intdiv($d * (1000 + $F['composure']['lastRoundDmgPerStat'] * $A['composure']), 1000);
        }
        $d = intdiv($d * ($Dm['jitterMin'] + $this->rng->int($Dm['jitterRange'])), 100);

        // Guard.
        $G = $this->R['defenses']['guard'];
        $gc = $G['chanceBase'] + $G['perStat'] * $D['defense'] + ($this->guardUp[$def] ? $G['upBonus'] : 0);
        $this->guardUp[$def] = false;
        if ($this->rng->int(1000) < $gc) {
            $d = max(1, intdiv($d * $G['dmgTakenPm'], 1000));
            $this->hp[$def] = max(0, $this->hp[$def] - $d);
            $this->dealt[$atk] += $d;
            $this->dealtRound[$atk] += $d;
            $this->gain($def, $G['meter']);
            if (! empty($T['leg'])) {
                $this->hurtLeg($def, intdiv($this->legDmg($T, $D), 2));
            }
            $this->push(['t' => 'block', 'who' => $atk, 'p' => $T['id'], 'd' => $d, 'j' => $jid]);
            if ($this->hp[$def] === 0) {
                $this->stop($atk, 'KO', $T['id'], 'hp');
            } elseif ($this->leg[$def] >= $F['leg']['tkoAt']) {
                $this->stop($atk, 'TKO', $T['id'], 'leg');
            }

            return;
        }

        $B = $F['big'];
        $big = $this->rng->int(1000) < $B['base'] + $B['techniquePerStat'] * $A['technique'];
        if ($big) {
            $d = intdiv($d * $B['multPm'], 1000);
        }
        $d = max(1, $d);
        $this->hp[$def] = max(0, $this->hp[$def] - $d);
        $this->dealt[$atk] += $d;
        $this->dealtRound[$atk] += $d;
        $this->pts[$atk] += $T['score'];
        $this->clean[$atk]++;
        $this->gain($atk, $this->R['meter']['cleanHit'] + intdiv($A['technique'], $this->R['meter']['techniqueDiv']));
        $this->gain($def, $this->R['meter']['takeHit']);
        if (! empty($T['push']) || ! empty($J['push'])) {
            $this->range = $this->toward($this->range, 'far');
        }
        if (! empty($T['leg'])) {
            $this->hurtLeg($def, $this->legDmg($T, $D));
        }

        if ($this->hp[$def] === 0) {
            $this->push(['t' => 'ko', 'who' => $atk, 'p' => $T['id'], 'd' => $d, 'j' => $jid, 'how' => 'KO', 'why' => 'hp']);
            $this->over = ['win' => $atk, 'how' => 'KO'];

            return;
        }

        $K = $F['knockdown'];
        // Jatuh: pukulan keras saat HP di bawah hpBelow, atau serangan berat saat HP sudah tipis.
        $kdTry = ($big && $this->hp[$def] < $K['hpBelow']) || ($d >= $K['heavyDmg'] && $this->hp[$def] < $K['heavyHpBelow']);
        if ($kdTry && $this->rng->int(1000) < ($K['enduranceRef'] - $D['endurance'] - intdiv($D['balance'], $K['balanceDiv'])) * $K['k']) {
            $this->kd[$def]++;
            $this->kdRound[$def]++;
            if ($this->kdRound[$def] >= $K['maxPerRoundTko']) {
                $this->push(['t' => 'ko', 'who' => $atk, 'p' => $T['id'], 'd' => $d, 'j' => $jid, 'how' => 'TKO', 'why' => 'kd']);
                $this->over = ['win' => $atk, 'how' => 'TKO'];

                return;
            }
            $low = $this->hp[$def];
            $this->hp[$def] = min($F['hp'], $this->hp[$def] + $K['recover'] + $K['balanceRecoverPerStat'] * $D['balance']);
            $this->push(['t' => 'kd', 'who' => $atk, 'p' => $T['id'], 'd' => $d, 'j' => $jid, 'low' => $low]);

            return;
        }

        $this->push(['t' => $big ? 'big' : 'hit', 'who' => $atk, 'p' => $T['id'], 'd' => $d, 'j' => $jid]);
        if ($this->leg[$def] >= $F['leg']['tkoAt']) {
            $this->stop($atk, 'TKO', $T['id'], 'leg');
        }
    }

    private function runJurus(string $who, array $J): void
    {
        $this->meter[$who] -= $J['meter'];
        $this->sta[$who] = max(0, $this->sta[$who] - $J['stamina']);
        $this->used[$who] = true;
        $this->push(['t' => 'jurus', 'who' => $who, 'j' => $J['id']]);
        foreach ($J['steps'] as $step) {
            $this->attack($who, $this->tech[$step], $J);
            if ($this->over) {
                return;
            }
        }
    }

    private function readyJurus(string $who, array $kinds, ?string $trigger): ?array
    {
        if ($this->used[$who]) {
            return null;
        }
        $opp = $this->other($who);
        foreach ($this->jur[$who] as $J) {
            if (! in_array($J['kind'], $kinds, true) || ($J['trigger'] ?? null) !== $trigger) {
                continue;
            }
            if ($this->meter[$who] < $J['meter'] || $this->sta[$who] < $J['stamina'] || ! in_array($this->range, $J['ranges'], true)) {
                continue;
            }
            $req = $J['requires'] ?? [];
            if (isset($req['round']) && $this->rd < $req['round']) {
                continue;
            }
            if (isset($req['oppHpBelowPm']) && $this->hp[$opp] * 1000 >= $req['oppHpBelowPm'] * $this->F['hp']) {
                continue;
            }

            return $J;
        }

        return null;
    }

    // ------------------------------------------------------------------ bantuan

    private function stop(string $win, string $how, string $p, string $why): void
    {
        $this->push(['t' => 'ko', 'who' => $win, 'p' => $p, 'how' => $how, 'why' => $why]);
        $this->over = ['win' => $win, 'how' => $how];
    }

    private function legDmg(array $T, array $D): int
    {
        return max(1, intdiv($T['leg'] * (1000 - $this->F['leg']['balanceSubPm'] * $D['balance']), 1000));
    }

    private function hurtLeg(string $who, int $n): void
    {
        $L = $this->F['leg'];
        $before = $this->leg[$who];
        $this->leg[$who] = min($L['max'], $this->leg[$who] + $n);
        if ($before < $L['slowAt'] && $this->leg[$who] >= $L['slowAt']) {
            $this->push(['t' => 'leg', 'who' => $who]);
        }
    }

    private function gain(string $who, int $n): void
    {
        $pm = $this->arch[$who]['passive']['meterPm'] ?? 1000;
        $this->meter[$who] = min($this->R['meter']['max'], $this->meter[$who] + intdiv($n * $pm, 1000));
    }

    private function cost(string $who, array $T): int
    {
        $pm = $T['cat'] === 'hand' ? ($this->arch[$who]['passive']['handStaminaPm'] ?? 1000) : 1000;

        return max(1, intdiv($T['stamina'] * $pm, 1000));
    }

    private function speed(string $who): int
    {
        $s = $this->st[$who]['speed'];
        if ($this->leg[$who] >= $this->F['leg']['slowAt']) {
            $s = intdiv($s * $this->F['leg']['slowSpeedPm'], 1000);
        }

        return max(1, $s);
    }

    private function toward(string $from, string $to): string
    {
        $r = $this->R['ranges'];
        $i = array_search($from, $r, true);
        $j = array_search($to, $r, true);

        return $r[$i + ($j <=> $i)];
    }

    private function closer(string $a, string $b): bool
    {
        return array_search($a, $this->R['ranges'], true) < array_search($b, $this->R['ranges'], true);
    }

    private function other(string $s): string
    {
        return $s === 'a' ? 'b' : 'a';
    }

    private function archetype(?string $id): array
    {
        foreach ($this->R['archetypes'] as $a) {
            if ($a['id'] === $id) {
                return $a;
            }
        }

        return $this->R['archetypes'][0];
    }

    /** Jurus terpasang yang dikenal aturan, maksimal jurusSlots, urutan dipertahankan. */
    private function equip(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->jurusDef[$id]) && count($out) < $this->R['jurusSlots']) {
                $out[] = $this->jurusDef[$id];
            }
        }

        return $out;
    }

    private function push(array $e): void
    {
        $this->events[] = $e + [
            'rd' => $this->rd, 'ha' => $this->hp['a'], 'hb' => $this->hp['b'],
            'na' => $this->sta['a'], 'nb' => $this->sta['b'], 'ma' => $this->meter['a'], 'mb' => $this->meter['b'],
            'la' => $this->leg['a'], 'lb' => $this->leg['b'], 'r' => $this->range,
        ];
    }

    private function result(string $win, string $how, array $score, array $rounds): array
    {
        return [
            'win' => $win, 'how' => $how, 'rd' => $this->rd,
            'scoreA' => $score['a'], 'scoreB' => $score['b'],
            'version' => $this->R['version'],
            'rounds' => $rounds,
            'clean' => $this->clean,
            'events' => $this->events,
        ];
    }
}
