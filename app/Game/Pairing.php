<?php

namespace App\Game;

/**
 * Memasangkan petarung Fight Night. Kelas murni dan deterministik:
 * input + seed yang sama selalu menghasilkan pasangan yang sama.
 *
 *  - Diurutkan menurut bintang lalu OVR, dengan sedikit acak agar lawan bervariasi.
 *  - Lawan yang sama dengan laga sebelumnya dihindari bila bisa.
 *  - Jumlah ganjil: petarung terakhir melawan NPC acak (bila ada).
 */
final class Pairing
{
    /**
     * @param  list<array{id:int, stars:int, ovr:int, last?:?int}>  $players
     * @param  list<int>  $npcIds
     * @return list<array{0:int, 1:int, 2:int}> [id sudut biru, id sudut merah, seed laga]
     */
    public static function pair(array $players, array $npcIds, int $seed, int $jitter = 40): array
    {
        $rng = new Rng($seed);
        $keyed = [];
        foreach ($players as $p) {
            $keyed[] = $p + ['key' => $p['stars'] * 1000 + $p['ovr'] * 10 + $rng->int($jitter)];
        }
        usort($keyed, fn ($a, $b) => [$b['key'], $a['id']] <=> [$a['key'], $b['id']]);

        $n = count($keyed);
        for ($i = 0; $i + 1 < $n; $i += 2) {
            $x = $keyed[$i];
            $y = $keyed[$i + 1];
            $rematch = ($x['last'] ?? null) === $y['id'] || ($y['last'] ?? null) === $x['id'];
            if ($rematch && $i + 2 < $n) {
                [$keyed[$i + 1], $keyed[$i + 2]] = [$keyed[$i + 2], $keyed[$i + 1]];
            }
        }

        $pairs = [];
        for ($i = 0; $i + 1 < $n; $i += 2) {
            $pairs[] = self::sides($rng, $keyed[$i]['id'], $keyed[$i + 1]['id']);
        }
        if ($n % 2 === 1 && $npcIds) {
            $pairs[] = self::sides($rng, $keyed[$n - 1]['id'], $npcIds[$rng->int(count($npcIds))]);
        }

        return $pairs;
    }

    /** @return array{0:int, 1:int, 2:int} */
    private static function sides(Rng $rng, int $x, int $y): array
    {
        return $rng->int(2) === 0 ? [$x, $y, $rng->next()] : [$y, $x, $rng->next()];
    }
}
