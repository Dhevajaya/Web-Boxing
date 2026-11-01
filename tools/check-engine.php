<?php

/*
 * Memeriksa implementasi PHP terhadap tests/fixtures/engine_vectors.json
 * tanpa Laravel/Composer. Pasangan JavaScript-nya: node --test tests/js
 *
 *   php tools/check-engine.php
 */

require __DIR__.'/engine-bootstrap.php';

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rng;

$vec = json_decode(file_get_contents(__DIR__.'/../tests/fixtures/engine_vectors.json'), true, flags: JSON_THROW_ON_ERROR);
$fail = 0;
$check = function (bool $ok, string $what) use (&$fail) {
    if (! $ok) {
        $fail++;
        fwrite(STDERR, "GAGAL: {$what}\n");
    }
};

foreach ($vec['rng'] as $t) {
    $r = new Rng($t['seed']);
    $got = [$r->next(), $r->next(), $r->next(), $r->next(), $r->next()];
    $check($got === $t['next'], "rng seed {$t['seed']}");
}
foreach ($vec['fnv'] as $t) {
    $check(Rng::fnv1a($t['s']) === $t['h'], "fnv1a '{$t['s']}'");
}

$cache = [];
foreach ($vec['fighters'] as $t) {
    $rules = $cache[$t['v']] ??= engine_rules($t['v']);
    $f = new FighterFactory($rules);
    $b = $f->base($t['username'], $t['v']);
    $got = ['style' => $b['style'], 'base' => $b['base'], 'nick' => $b['nick'], 'kota' => $b['kota'], 'kelas' => $b['kelas'], 'ovr' => $f->ovr($b['base'], $t['v'])];
    $check($got == $t['expect'], "petarung {$t['username']}");
}

foreach ($vec['fights'] as $i => $t) {
    $rules = $cache[$t['v']] ??= engine_rules($t['v']);
    $r = (new FightSimulator($rules))->simulate($t['a'], $t['b'], $t['seed'], $t['v']);
    $check(FightSimulator::signature($r) === $t['expect']['sig'] && $r['win'] === $t['expect']['win'], "laga #{$i} (seed {$t['seed']})");
}

$n = count($vec['rng']) + count($vec['fnv']) + count($vec['fighters']) + count($vec['fights']);
if ($fail) {
    fwrite(STDERR, "{$fail} dari {$n} vektor GAGAL\n");
    exit(1);
}
echo "OK: {$n} vektor cocok (PHP)\n";
