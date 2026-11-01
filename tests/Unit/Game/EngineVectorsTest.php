<?php

namespace Tests\Unit\Game;

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rng;
use App\Game\Rules;
use PHPUnit\Framework\TestCase;

/**
 * Vektor yang sama dicek oleh tests/js/engine.test.mjs, jadi lulus di sini
 * dan di sana berarti PHP dan JavaScript menghasilkan pertarungan identik.
 */
class EngineVectorsTest extends TestCase
{
    private static array $vec;

    public static function setUpBeforeClass(): void
    {
        self::$vec = json_decode(file_get_contents(__DIR__.'/../../fixtures/engine_vectors.json'), true);
    }

    private function rules(int $v): Rules
    {
        return Rules::load(__DIR__.'/../../../resources/game', $v);
    }

    public function test_rng_and_hash(): void
    {
        foreach (self::$vec['rng'] as $t) {
            $r = new Rng($t['seed']);
            $this->assertSame($t['next'], [$r->next(), $r->next(), $r->next(), $r->next(), $r->next()]);
        }
        foreach (self::$vec['fnv'] as $t) {
            $this->assertSame($t['h'], Rng::fnv1a($t['s']));
        }
    }

    public function test_base_fighters(): void
    {
        foreach (self::$vec['fighters'] as $t) {
            $f = new FighterFactory($this->rules($t['v']));
            $b = $f->base($t['username'], $t['v']);
            $this->assertEquals($t['expect'], [
                'style' => $b['style'], 'base' => $b['base'], 'nick' => $b['nick'],
                'kota' => $b['kota'], 'kelas' => $b['kelas'], 'ovr' => $f->ovr($b['base'], $t['v']),
            ], $t['username']);
        }
    }

    public function test_fights(): void
    {
        $sim = [];
        foreach (self::$vec['fights'] as $i => $t) {
            $sim[$t['v']] ??= new FightSimulator($this->rules($t['v']));
            $r = $sim[$t['v']]->simulate($t['a'], $t['b'], $t['seed'], $t['v']);
            $this->assertSame($t['expect']['sig'], FightSimulator::signature($r), "laga #{$i}");
        }
    }

    public function test_same_input_same_output(): void
    {
        $sim = new FightSimulator($this->rules(1));
        $a = ['style' => 'penyerang', 'stats' => ['power' => 70, 'speed' => 50, 'stamina' => 55, 'defense' => 45, 'chin' => 60]];
        $b = ['style' => 'penghindar', 'stats' => ['power' => 45, 'speed' => 70, 'stamina' => 60, 'defense' => 65, 'chin' => 45]];

        $this->assertSame($sim->simulate($a, $b, 12345), $sim->simulate($a, $b, 12345));
        $this->assertNotSame(
            FightSimulator::signature($sim->simulate($a, $b, 1)),
            FightSimulator::signature($sim->simulate($a, $b, 2)),
        );
    }

    public function test_hp_never_negative_and_winner_consistent(): void
    {
        $sim = new FightSimulator($this->rules(1));
        $gen = new Rng(99);
        for ($i = 0; $i < 300; $i++) {
            $mk = fn () => ['style' => 'petinju', 'stats' => [
                'power' => 35 + $gen->int(65), 'speed' => 35 + $gen->int(65), 'stamina' => 35 + $gen->int(65),
                'defense' => 35 + $gen->int(65), 'chin' => 35 + $gen->int(65),
            ]];
            $r = $sim->simulate($mk(), $mk(), $gen->next());
            foreach ($r['events'] as $e) {
                $this->assertGreaterThanOrEqual(0, $e['ha']);
                $this->assertGreaterThanOrEqual(0, $e['hb']);
            }
            if ($r['how'] === 'Angka') {
                $expected = $r['scoreA'] === $r['scoreB'] ? 'draw' : ($r['scoreA'] > $r['scoreB'] ? 'a' : 'b');
                $this->assertSame($expected, $r['win']);
            } else {
                $this->assertContains($r['win'], ['a', 'b']);
            }
        }
    }
}
