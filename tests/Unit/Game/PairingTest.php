<?php

namespace Tests\Unit\Game;

use App\Game\Pairing;
use PHPUnit\Framework\TestCase;

class PairingTest extends TestCase
{
    private function players(int $n): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = ['id' => $i, 'stars' => intdiv($i, 3), 'ovr' => 40 + $i % 17, 'last' => null];
        }

        return $out;
    }

    public function test_everyone_fights_exactly_once_and_is_deterministic(): void
    {
        $p = Pairing::pair($this->players(10), [], 42);
        $this->assertCount(5, $p);
        $ids = array_merge(array_column($p, 0), array_column($p, 1));
        sort($ids);
        $this->assertSame(range(1, 10), $ids);
        $this->assertSame($p, Pairing::pair($this->players(10), [], 42));
        $this->assertNotSame($p, Pairing::pair($this->players(10), [], 43));
        foreach ($p as [$a, $b, $seed]) {
            $this->assertNotSame($a, $b);
            $this->assertGreaterThanOrEqual(0, $seed);
        }
    }

    public function test_odd_player_meets_npc_or_sits_out(): void
    {
        $p = Pairing::pair($this->players(5), [900, 901], 7);
        $this->assertCount(3, $p);
        $ids = array_merge(array_column($p, 0), array_column($p, 1));
        $this->assertCount(1, array_intersect($ids, [900, 901]));

        $this->assertCount(2, Pairing::pair($this->players(5), [], 7));
        $this->assertSame([], Pairing::pair([], [900], 7));
    }

    public function test_avoids_immediate_rematch(): void
    {
        // Tanpa acak (jitter 1) urutannya pasti: 4-3, 2-1. Laga terakhir 4 lawan 3 -> ditukar.
        $players = [
            ['id' => 1, 'stars' => 0, 'ovr' => 40, 'last' => null],
            ['id' => 2, 'stars' => 0, 'ovr' => 41, 'last' => null],
            ['id' => 3, 'stars' => 0, 'ovr' => 42, 'last' => 4],
            ['id' => 4, 'stars' => 0, 'ovr' => 43, 'last' => 3],
        ];
        foreach (Pairing::pair($players, [], 1, 1) as [$a, $b]) {
            $this->assertNotEquals([3, 4], [min($a, $b), max($a, $b)]);
        }
    }
}
