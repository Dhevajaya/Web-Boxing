<?php

namespace App\Game;

/**
 * Mesin pertarungan deterministik.
 *
 *   simulate($a, $b, $seed, $version) -> hasil + daftar kejadian
 *
 * $a dan $b: ['stats' => [power, speed, stamina, defense, chin], 'style' => 'petinju'].
 * Input yang sama SELALU menghasilkan keluaran yang sama, di PHP maupun
 * di resources/js/game/simulate.js. Karena itu:
 *   - hanya aritmetika integer (intdiv), tanpa float;
 *   - acak hanya dari App\Game\Rng, dipanggil dalam urutan yang identik di JS;
 *   - perubahan rumus = berkas rules.v{n}.json baru, bukan mengedit versi lama.
 */
final class FightSimulator
{
    public function __construct(private readonly Rules $rules) {}

    /**
     * @return array{win:string, how:string, rd:int, scoreA:int, scoreB:int, version:int, events:list<array>}
     */
    public function simulate(array $a, array $b, int $seed, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        if ($R['version'] >= 2) {
            return (new FightSimulatorV2)->simulate($R, $a, $b, $seed);
        }
        $F = $R['fight'];
        $rng = new Rng($seed);

        $st = ['a' => $a['stats'], 'b' => $b['stats']];
        $weights = ['a' => $this->styleWeights($R, $a['style'] ?? null), 'b' => $this->styleWeights($R, $b['style'] ?? null)];
        $hp = ['a' => $F['hp'], 'b' => $F['hp']];
        $kd = ['a' => 0, 'b' => 0];
        $score = ['a' => 0, 'b' => 0];
        $events = [];

        $H = $F['hit'];
        $D = $F['damage'];
        $BIG = $F['big'];
        $KD = $F['knockdown'];

        for ($rd = 1; $rd <= $F['rounds']; $rd++) {
            $events[] = ['t' => 'bell', 'rd' => $rd, 'ha' => $hp['a'], 'hb' => $hp['b']];
            $dealt = ['a' => 0, 'b' => 0];
            $kdRound = ['a' => 0, 'b' => 0];

            for ($x = 0; $x < $F['exchanges']; $x++) {
                $atk = $rng->int($st['a']['speed'] + $st['b']['speed']) < $st['a']['speed'] ? 'a' : 'b';
                $def = $atk === 'a' ? 'b' : 'a';
                $S = $st[$atk];
                $T = $st[$def];

                $P = $R['punches'][$rng->weighted($weights[$atk])];
                $chance = max($H['min'], min($H['max'], $H['base'] + $H['k'] * ($S['speed'] - $T['defense']) + $P['acc']));

                if ($rng->int(1000) >= $chance) {
                    $events[] = ['t' => 'miss', 'rd' => $rd, 'who' => $atk, 'p' => $P['id'], 'ha' => $hp['a'], 'hb' => $hp['b']];

                    continue;
                }

                $fatigue = max(500, 1000 - ($rd - 1) * (100 - $S['stamina']) * $F['fatigue']['perStaminaPerRound']);
                $d = intdiv($P['dmg'] * ($D['powerAdd'] + $S['power']) * ($D['chinSub'] - $T['chin']), 10000);
                $d = intdiv($d * $fatigue, 1000);
                $d = intdiv($d * ($D['jitterMin'] + $rng->int($D['jitterRange'])), 100);
                $big = $rng->int(1000) < $BIG['base'] + $S['power'];
                if ($big) {
                    $d = intdiv($d * $BIG['multNum'], $BIG['multDen']);
                }
                $d = max(1, $d);

                $hp[$def] = max(0, $hp[$def] - $d);
                $dealt[$atk] += $d;

                if ($hp[$def] === 0) {
                    $events[] = ['t' => 'ko', 'rd' => $rd, 'who' => $atk, 'p' => $P['id'], 'd' => $d, 'ha' => $hp['a'], 'hb' => $hp['b'], 'how' => 'KO'];

                    return $this->finish($atk, 'KO', $rd, $score, $events, $R['version']);
                }

                if ($big && $hp[$def] < $KD['hpBelow'] && $rng->int(1000) < ($KD['chinRef'] - $T['chin']) * $KD['k']) {
                    $kd[$def]++;
                    $kdRound[$def]++;
                    if ($kd[$def] >= $KD['maxBeforeTko']) {
                        $events[] = ['t' => 'ko', 'rd' => $rd, 'who' => $atk, 'p' => $P['id'], 'd' => $d, 'ha' => $hp['a'], 'hb' => $hp['b'], 'how' => 'TKO'];

                        return $this->finish($atk, 'TKO', $rd, $score, $events, $R['version']);
                    }
                    $low = $hp[$def];
                    $hp[$def] = min($F['hp'], $hp[$def] + $KD['recover']);
                    $events[] = ['t' => 'kd', 'rd' => $rd, 'who' => $atk, 'p' => $P['id'], 'd' => $d, 'low' => $low, 'ha' => $hp['a'], 'hb' => $hp['b']];

                    continue;
                }

                $events[] = ['t' => $big ? 'big' : 'hit', 'rd' => $rd, 'who' => $atk, 'p' => $P['id'], 'd' => $d, 'ha' => $hp['a'], 'hb' => $hp['b']];
            }

            // Sistem 10 poin: yang memberi damage lebih banyak dapat 10, lawan 9; knockdown -1.
            $sc = $F['scoring'];
            $ra = $dealt['a'] >= $dealt['b'] ? $sc['winner'] : $sc['loser'];
            $rb = $dealt['b'] >= $dealt['a'] ? $sc['winner'] : $sc['loser'];
            $score['a'] += $ra - $kdRound['a'] * $sc['perKnockdown'];
            $score['b'] += $rb - $kdRound['b'] * $sc['perKnockdown'];

            if ($rd < $F['rounds']) {
                foreach (['a', 'b'] as $s) {
                    $hp[$s] = min($F['hp'], $hp[$s] + $F['recoverBetweenRounds']['base'] + $st[$s]['stamina']);
                }
            }
        }

        $events[] = ['t' => 'end', 'rd' => $F['rounds'], 'ha' => $hp['a'], 'hb' => $hp['b'], 'sa' => $score['a'], 'sb' => $score['b']];
        $win = $score['a'] === $score['b'] ? 'draw' : ($score['a'] > $score['b'] ? 'a' : 'b');

        return $this->finish($win, 'Angka', $F['rounds'], $score, $events, $R['version']);
    }

    /** Sidik jari keluaran untuk test kesetaraan PHP dan JavaScript. */
    public static function signature(array $result): string
    {
        $parts = [$result['win'], $result['how'], $result['rd'], $result['scoreA'], $result['scoreB']];
        $v2 = ($result['version'] ?? 1) >= 2;
        foreach ($result['events'] as $e) {
            $f = [$e['t'], $e['rd'], $e['who'] ?? '', $e['p'] ?? '', $e['d'] ?? '', $e['ha'], $e['hb']];
            if ($v2) {
                array_push($f, $e['j'] ?? '', $e['r'], $e['na'], $e['nb'], $e['ma'], $e['mb'], $e['la'], $e['lb']);
            }
            $parts[] = implode(',', $f);
        }

        return sprintf('%08x', Rng::fnv1a(implode('|', $parts)));
    }

    private function styleWeights(array $R, ?string $style): array
    {
        foreach ($R['styles'] as $s) {
            if ($s['id'] === $style) {
                return $s['weights'];
            }
        }

        return $R['styles'][0]['weights'];
    }

    private function finish(string $win, string $how, int $rd, array $score, array $events, int $version): array
    {
        return [
            'win' => $win,
            'how' => $how,
            'rd' => $rd,
            'scoreA' => $score['a'],
            'scoreB' => $score['b'],
            'version' => $version,
            'events' => $events,
        ];
    }
}
