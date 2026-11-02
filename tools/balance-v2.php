<?php

/*
 * Uji keseimbangan aturan v2: ribuan laga acak, laporkan cara menang, jatuh, jurus, archetype.
 *   php tools/balance-v2.php [jumlah=10000]
 */

require __DIR__.'/engine-bootstrap.php';

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rng;

$n = (int) ($argv[1] ?? 10000);
$rules = engine_rules(2);
$R = $rules->version(2);
$factory = new FighterFactory($rules);
$sim = new FightSimulator($rules);
$gen = new Rng(777);
$name = function () use ($gen) {
    $s = '';
    for ($i = 0, $l = 4 + $gen->int(10); $i < $l; $i++) {
        $s .= 'abcdefghijklmnopqrstuvwxyz0123456789'[$gen->int(36)];
    }

    return $s;
};
$sigOf = [];
foreach ($R['archetypes'] as $a) {
    $sigOf[$a['id']] = $a['signature'];
}
$side = function (?string $arch = null, int $pts = 0) use ($factory, $name, $gen, $R, $sigOf) {
    $b = $factory->base($name(), 2);
    $style = $arch ?? $b['style'];
    $points = [];
    for ($i = 0; $i < $pts; $i++) {
        $k = $R['stats'][$gen->int(10)];
        $points[$k] = ($points[$k] ?? 0) + 1;
    }

    return ['style' => $style, 'stats' => $factory->effective($b['base'], $points, [], 2), 'jurus' => [$sigOf[$style], ['patah_ranting', 'sambar_badan'][$gen->int(2)]]];
};

$how = $win = $rdEnd = [];
$ev = [];
$archW = $archN = [];
$tkoLeg = $draw = 0;
$jurusHit = 0;
$endHp = 0;
for ($i = 0; $i < $n; $i++) {
    $a = $side();
    $b = $side();
    $r = $sim->simulate($a, $b, $gen->next(), 2);
    $how[$r['how']] = ($how[$r['how']] ?? 0) + 1;
    $rdEnd[$r['rd']] = ($rdEnd[$r['rd']] ?? 0) + 1;
    if ($r['win'] === 'draw') {
        $draw++;
    }
    foreach ($r['events'] as $e) {
        $ev[$e['t']] = ($ev[$e['t']] ?? 0) + 1;
        if ($e['t'] === 'ko' && ($e['why'] ?? '') === 'leg') {
            $tkoLeg++;
        }
        if (! empty($e['j']) && in_array($e['t'], ['hit', 'big', 'kd', 'ko'], true)) {
            $jurusHit++;
        }
    }
    foreach (['a' => $a, 'b' => $b] as $s => $x) {
        $archN[$x['style']] = ($archN[$x['style']] ?? 0) + 1;
        if ($r['win'] === $s) {
            $archW[$x['style']] = ($archW[$x['style']] ?? 0) + 1;
        }
    }
    $last = end($r['events']);
    $endHp += $last['ha'] + $last['hb'];
}
$pct = fn ($x) => sprintf('%5.1f%%', 100 * $x / $n);
echo "Laga: {$n}\n";
foreach ($how as $k => $v) {
    echo "  {$k}: ".$pct($v)."\n";
}
echo '  seri: '.$pct($draw).'   TKO kaki: '.$pct($tkoLeg)."\n";
ksort($rdEnd);
echo '  selesai di ronde: '.json_encode($rdEnd)."\n";
echo '  HP rata-rata di akhir: '.intdiv($endHp, 2 * $n)."\n";
echo "Kejadian per laga:\n";
arsort($ev);
foreach ($ev as $k => $v) {
    printf("  %-6s %6.2f\n", $k, $v / $n);
}
printf("  serangan jurus kena per laga: %.2f\n", $jurusHit / $n);
echo "Menang per archetype (vs acak):\n";
foreach ($archN as $k => $v) {
    printf("  %-17s %5.1f%%  (%d laga)\n", $k, 100 * ($archW[$k] ?? 0) / $v, $v);
}

// Head-to-head archetype dengan stat sama.
echo "Head-to-head (stat dasar acak, archetype dipaksa, 2000 laga per pasangan), % menang baris:\n";
$ids = array_keys($sigOf);
echo str_pad('', 18);
foreach ($ids as $c) {
    echo str_pad(substr($c, 0, 9), 10);
}
echo "\n";
foreach ($ids as $x) {
    echo str_pad($x, 18);
    foreach ($ids as $y) {
        $w = 0;
        for ($i = 0; $i < 2000; $i++) {
            $a = $side($x);
            $b = $side($y);
            $b['stats'] = $a['stats'];
            $r = $sim->simulate($a, $b, $gen->next(), 2);
            $w += $r['win'] === 'a' ? 2 : ($r['win'] === 'draw' ? 1 : 0);
        }
        echo str_pad(sprintf('%.0f', $w / 40), 10);
    }
    echo "\n";
}

// Napas: seberapa sering petarung kelelahan (napas < tiredBelow) di tiap ronde.
$tired = [1 => 0, 2 => 0, 3 => 0];
$cnt = [1 => 0, 2 => 0, 3 => 0];
for ($i = 0; $i < 2000; $i++) {
    $r = $sim->simulate($side(), $side(), $gen->next(), 2);
    foreach ($r['events'] as $e) {
        if (! in_array($e['t'], ['hit', 'miss', 'block', 'big'], true)) {
            continue;
        }
        $cnt[$e['rd']]++;
        $n_ = $e['who'] === 'a' ? $e['na'] : $e['nb'];
        if ($n_ < $R['fight']['stamina']['tiredBelow']) {
            $tired[$e['rd']]++;
        }
    }
}
echo 'Serangan dalam keadaan lelah per ronde: ';
foreach ($tired as $rd => $t) {
    printf('R%d %.1f%%  ', $rd, $cnt[$rd] ? 100 * $t / $cnt[$rd] : 0);
}
echo "\n";

// Pengaruh poin stat: petarung dengan N poin tambahan (acak) melawan 0 poin.
foreach ([10, 30, 60] as $p) {
    $w = 0;
    for ($i = 0; $i < 2000; $i++) {
        $r = $sim->simulate($side(null, $p), $side(), $gen->next(), 2);
        $w += $r['win'] === 'a' ? 1 : 0;
    }
    printf("+%d poin vs 0: menang %.1f%%\n", $p, $w / 20);
}
// Pengaruh tiap atribut: +20 di satu atribut, sisanya sama.
echo 'Atribut +20 (lawan identik): ';
foreach ($R['stats'] as $k) {
    $w = 0;
    for ($i = 0; $i < 1500; $i++) {
        $a = $side('petarung_komplet');
        $b = $a;
        $a['stats'][$k] = min(99, $a['stats'][$k] + 20);
        $r = $sim->simulate($a, $b, $gen->next(), 2);
        $w += $r['win'] === 'a' ? 1 : 0;
    }
    printf('%s %.0f%%  ', $k, $w / 15);
}
echo "\n";
