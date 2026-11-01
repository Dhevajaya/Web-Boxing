<?php

namespace Tests\Unit\Game;

use App\Game\Bracket;
use PHPUnit\Framework\TestCase;

class BracketTest extends TestCase
{
    public function test_size_and_seed_order(): void
    {
        $this->assertSame(2, Bracket::size(1));
        $this->assertSame(8, Bracket::size(5));
        $this->assertSame(16, Bracket::size(16));
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], Bracket::seedOrder(8));
        $o = Bracket::seedOrder(32);
        $this->assertCount(32, $o);
        $this->assertSame(range(1, 32), (function ($a) { sort($a); return $a; })($o));
        // Setiap pasangan babak 1 berjumlah size + 1 (unggulan atas lawan unggulan bawah).
        for ($i = 0; $i < 32; $i += 2) {
            $this->assertSame(33, $o[$i] + $o[$i + 1]);
        }
        // Unggulan 1 dan 2 di paruh berbeda.
        $this->assertLessThan(16, array_search(1, $o));
        $this->assertGreaterThanOrEqual(16, array_search(2, $o));
    }

    public function test_round_names(): void
    {
        $this->assertSame('Final', Bracket::roundName(5, 5));
        $this->assertSame('Semifinal', Bracket::roundName(4, 5));
        $this->assertSame('Babak 1', Bracket::roundName(1, 7));
    }
}
