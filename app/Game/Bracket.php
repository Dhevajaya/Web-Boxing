<?php

namespace App\Game;

/** Bantuan bagan turnamen gugur. Kelas murni. */
final class Bracket
{
    /** Pangkat dua terkecil >= n (minimal 2). */
    public static function size(int $n): int
    {
        $s = 2;
        while ($s < $n) {
            $s *= 2;
        }

        return $s;
    }

    /**
     * Urutan unggulan standar: unggulan 1 dan 2 baru bertemu di final, 1-4 di semifinal, dst.
     * size 8 -> [1, 8, 4, 5, 2, 7, 3, 6]
     *
     * @return list<int>
     */
    public static function seedOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $n = count($order) * 2 + 1;
            $next = [];
            foreach ($order as $s) {
                $next[] = $s;
                $next[] = $n - $s;
            }
            $order = $next;
        }

        return $order;
    }

    public static function roundName(int $round, int $rounds): string
    {
        return match ($rounds - $round) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Perempat final',
            3 => '16 besar',
            4 => '32 besar',
            default => "Babak {$round}",
        };
    }
}
