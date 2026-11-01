<?php

/*
 * Statistik keseimbangan mesin: rasio KO/TKO/angka/seri, rata-rata ronde,
 * dan seberapa sering petarung ber-OVR lebih rendah menang.
 * Pakai saat menyetel rules.v{n}.json.
 *
 *   php tools/engine-stats.php [versi] [jumlah_laga] [poin_maks_per_stat]
 */

require __DIR__.'/engine-bootstrap.php';

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rng;

$version = (int) ($argv[1] ?? 1);
$n = (int) ($argv[2] ?? 20000);
$maxPts = (int) ($argv[3] ?? 0);

$rules = engine_rules($version);
$f = new FighterFactory($rules);
$sim = new FightSimulator($rules);
$gen = new Rng(777);
$keys = $rules->version($version)['stats'];

$side = function () use ($f, $gen, $keys, $maxPts, $version) {
    $b = $f->base('u'.$gen->next(), $version);
    $pts = [];
    foreach ($keys as $k) {
        $pts[$k] = $maxPts ? $gen->int($maxPts + 1) : 0;
    }
    $st = $f->effective($b['base'], $pts, [], $version);

    return ['style' => $b['style'], 'stats' => $st, 'ovr' => $f->ovr($st, $version)];
};

$how = ['KO' => 0, 'TKO' => 0, 'Angka' => 0];
$draw = 0;
$rdSum = 0;
$diffGames = 0;
$upsets = 0;
$bigGap = 0;
$bigGapUpsets = 0;
$kds = 0;

for ($i = 0; $i < $n; $i++) {
    $a = $side();
    $b = $side();
    $r = $sim->simulate($a, $b, $gen->next(), $version);
    $how[$r['how']]++;
    $rdSum += $r['rd'];
    if ($r['win'] === 'draw') {
        $draw++;
    }
    foreach ($r['events'] as $e) {
        if ($e['t'] === 'kd') {
            $kds++;
        }
    }
    if ($a['ovr'] !== $b['ovr'] && $r['win'] !== 'draw') {
        $diffGames++;
        $lowWon = ($a['ovr'] < $b['ovr']) === ($r['win'] === 'a');
        $upsets += $lowWon ? 1 : 0;
        if (abs($a['ovr'] - $b['ovr']) >= 8) {
            $bigGap++;
            $bigGapUpsets += $lowWon ? 1 : 0;
        }
    }
}

$pct = fn ($x, $t) => $t ? number_format(100 * $x / $t, 1).'%' : '-';
printf("Versi %d, %d laga, poin acak 0..%d per stat\n", $version, $n, $maxPts);
printf("KO %s | TKO %s | Angka %s | Seri %s\n", $pct($how['KO'], $n), $pct($how['TKO'], $n), $pct($how['Angka'], $n), $pct($draw, $n));
printf("Rata-rata ronde selesai: %.2f | knockdown per laga: %.2f\n", $rdSum / $n, $kds / $n);
printf("OVR lebih rendah menang: %s | dengan selisih OVR >= 8: %s (%d laga)\n", $pct($upsets, $diffGames), $pct($bigGapUpsets, $bigGap), $bigGap);
