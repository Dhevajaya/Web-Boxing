<?php

/*
 * Membuat tests/fixtures/engine_vectors.json dari implementasi PHP.
 *
 *   php tools/make-vectors.php
 *
 * Jalankan HANYA saat menambah versi aturan baru. Vektor versi lama tidak boleh
 * berubah: kalau berubah, berarti rumus lama ikut teredit dan replay laga lama akan rusak.
 */

require __DIR__.'/engine-bootstrap.php';

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rng;

$out = ['generated_by' => 'tools/make-vectors.php', 'rng' => [], 'fnv' => [], 'fighters' => [], 'fights' => []];

foreach ([0, 1, 42, 123456789, 4294967295, 2654435761] as $seed) {
    $r = new Rng($seed);
    $out['rng'][] = ['seed' => $seed, 'next' => [$r->next(), $r->next(), $r->next(), $r->next(), $r->next()]];
}
foreach (['', 'a', 'gelud', 'v1|budi_99', 'Ring Kita 🥊'] as $s) {
    $out['fnv'][] = ['s' => $s, 'h' => Rng::fnv1a($s)];
}

foreach ([1] as $version) {
    $rules = engine_rules($version);
    $factory = new FighterFactory($rules);
    $sim = new FightSimulator($rules);
    $gen = new Rng(20261101 + $version);
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789._';
    $randName = function () use ($gen, $alphabet) {
        $n = '';
        $len = 3 + $gen->int(14);
        for ($i = 0; $i < $len; $i++) {
            $n .= $alphabet[$gen->int(strlen($alphabet))];
        }

        return $n;
    };

    for ($i = 0; $i < 100; $i++) {
        $u = $randName();
        $b = $factory->base($u, $version);
        $out['fighters'][] = ['v' => $version, 'username' => $u, 'expect' => [
            'style' => $b['style'], 'base' => $b['base'], 'nick' => $b['nick'], 'kota' => $b['kota'], 'kelas' => $b['kelas'],
            'ovr' => $factory->ovr($b['base'], $version),
        ]];
    }

    $stats = $rules->version($version)['stats'];
    $side = function () use ($factory, $gen, $randName, $stats, $version) {
        $b = $factory->base($randName(), $version);
        $points = [];
        foreach ($stats as $k) {
            $points[$k] = $gen->int(12);
        }
        $bonus = [$stats[$gen->int(count($stats))] => $gen->int(4)];

        return ['style' => $b['style'], 'stats' => $factory->effective($b['base'], $points, $bonus, $version)];
    };

    for ($i = 0; $i < 400; $i++) {
        $a = $side();
        $b = $side();
        $seed = $gen->next();
        $r = $sim->simulate($a, $b, $seed, $version);
        $out['fights'][] = ['v' => $version, 'a' => $a, 'b' => $b, 'seed' => $seed, 'expect' => [
            'win' => $r['win'], 'how' => $r['how'], 'rd' => $r['rd'], 'scoreA' => $r['scoreA'], 'scoreB' => $r['scoreB'],
            'events' => count($r['events']), 'sig' => FightSimulator::signature($r),
        ]];
    }
}

$file = __DIR__.'/../tests/fixtures/engine_vectors.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
echo 'Ditulis: '.realpath($file).' ('.count($out['fights'])." laga, ".count($out['fighters'])." petarung)\n";
