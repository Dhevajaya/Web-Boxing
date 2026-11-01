<?php

namespace App\Game;

/**
 * PRNG 32-bit deterministik (mulberry32) + hash FNV-1a.
 *
 * Harus menghasilkan angka yang SAMA PERSIS dengan resources/js/game/rng.js.
 * Semua nilai disimpan sebagai unsigned 32-bit di dalam integer 64-bit PHP,
 * jadi setiap operasi di-mask dengan 0xFFFFFFFF.
 */
final class Rng
{
    private const MASK = 0xFFFFFFFF;

    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & self::MASK;
    }

    public static function fromString(string $s): self
    {
        return new self(self::fnv1a($s));
    }

    /** Bilangan bulat acak 0..2^32-1. */
    public function next(): int
    {
        $this->state = ($this->state + 0x6D2B79F5) & self::MASK;
        $s = $this->state;
        $t = self::imul($s ^ ($s >> 15), $s | 1);
        $t = ((($t + self::imul($t ^ ($t >> 7), $t | 61)) & self::MASK) ^ $t) & self::MASK;

        return ($t ^ ($t >> 14)) & self::MASK;
    }

    /** Bilangan bulat 0..n-1. */
    public function int(int $n): int
    {
        return $n <= 1 ? 0 : $this->next() % $n;
    }

    /** Indeks terpilih menurut bobot. */
    public function weighted(array $weights): int
    {
        $total = array_sum($weights);
        $roll = $this->int($total);
        foreach ($weights as $i => $w) {
            if ($roll < $w) {
                return $i;
            }
            $roll -= $w;
        }

        return count($weights) - 1;
    }

    /** Perkalian 32-bit seperti Math.imul di JavaScript (hasil unsigned). */
    public static function imul(int $a, int $b): int
    {
        $a &= self::MASK;
        $b &= self::MASK;
        $ah = ($a >> 16) & 0xFFFF;
        $al = $a & 0xFFFF;
        $bh = ($b >> 16) & 0xFFFF;
        $bl = $b & 0xFFFF;

        return ($al * $bl + ((($ah * $bl + $al * $bh) & 0xFFFF) << 16)) & self::MASK;
    }

    /** FNV-1a 32-bit atas byte UTF-8. */
    public static function fnv1a(string $s): int
    {
        $h = 0x811C9DC5;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $h ^= ord($s[$i]);
            $h = self::imul($h, 0x01000193);
        }

        return $h & self::MASK;
    }
}
