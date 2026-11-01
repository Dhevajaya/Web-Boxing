<?php

namespace Tests\Unit\Game;

use App\Game\Progression;
use PHPUnit\Framework\TestCase;

class ProgressionTest extends TestCase
{
    private function p(): Progression
    {
        $cfg = require __DIR__.'/../../../config/game.php';

        return new Progression([
            'points' => $cfg['points'], 'level' => $cfg['level'], 'tiers' => $cfg['tiers'], 'xp' => $cfg['xp'],
        ]);
    }

    public function test_level_curve(): void
    {
        $p = $this->p();
        $this->assertSame(['lv' => 1, 'into' => 0, 'need' => 50], $p->level(0));
        $this->assertSame(['lv' => 1, 'into' => 49, 'need' => 50], $p->level(49));
        $this->assertSame(['lv' => 2, 'into' => 0, 'need' => 75], $p->level(50));
        $this->assertSame(['lv' => 3, 'into' => 5, 'need' => 100], $p->level(130));
        $this->assertSame(20, $p->level(10_000_000)['lv']);
        $this->assertSame(0, $p->level(10_000_000)['need']);
    }

    public function test_points_with_milestones(): void
    {
        $p = $this->p();
        $this->assertSame(10, $p->pointsFor(1));
        $this->assertSame(16, $p->pointsFor(4));   // 10 + 2*3, bonus Lv5 belum
        $this->assertSame(21, $p->pointsFor(5));   // 10 + 2*4 + 3
        $this->assertSame(60, $p->pointsFor(20));  // 10 + 2*19 + 3*4
    }

    public function test_tiers(): void
    {
        $p = $this->p();
        $this->assertSame('Pemula Gang', $p->tier(0)['name']);
        $this->assertSame('Petarung Kota', $p->tier(9)['name']);
        $this->assertSame('Legenda', $p->tier(99)['name']);
    }

    public function test_points_validation(): void
    {
        $p = $this->p();
        $keys = ['power', 'speed', 'stamina', 'defense', 'chin'];
        $this->assertTrue($p->validPoints(['power' => 5, 'speed' => 5], 1, $keys));
        $this->assertFalse($p->validPoints(['power' => 11], 1, $keys));
        $this->assertFalse($p->validPoints(['luck' => 1], 1, $keys));
        $this->assertFalse($p->validPoints(['power' => -1], 1, $keys));
    }
}
