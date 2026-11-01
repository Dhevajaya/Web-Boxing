<?php

namespace App\Game;

/**
 * Aturan progresi: XP -> level, level -> poin stat, bintang -> tier.
 * Angkanya diambil dari config/game.php (bisa ditimpa tabel settings),
 * dikirim lewat konstruktor agar kelas ini tetap murni dan mudah dites.
 */
final class Progression
{
    /**
     * @param  array{points:array, level:array, tiers:array, xp:array}  $cfg
     */
    public function __construct(private readonly array $cfg) {}

    public static function fromConfig(): self
    {
        $g = app(\App\Support\GameConfig::class);

        return new self([
            'points' => $g->get('points'),
            'level' => $g->get('level'),
            'tiers' => $g->get('tiers'),
            'xp' => $g->get('xp'),
        ]);
    }

    /** XP yang dibutuhkan dari level $lv ke $lv+1. */
    public function xpToNext(int $lv): int
    {
        $L = $this->cfg['level'];

        return $L['base'] + $L['step'] * ($lv - 1);
    }

    /** @return array{lv:int, into:int, need:int} need = 0 bila sudah level maksimal */
    public function level(int $xp): array
    {
        $max = $this->cfg['level']['max'];
        $lv = 1;
        while ($lv < $max && $xp >= $this->xpToNext($lv)) {
            $xp -= $this->xpToNext($lv);
            $lv++;
        }

        return ['lv' => $lv, 'into' => $lv >= $max ? 0 : $xp, 'need' => $lv >= $max ? 0 : $this->xpToNext($lv)];
    }

    /** Total poin stat yang boleh dibagikan pada level tertentu. */
    public function pointsFor(int $lv): int
    {
        $P = $this->cfg['points'];
        $total = $P['start'] + $P['per_level'] * max(0, $lv - 1);
        foreach ($P['milestones'] as $at => $bonus) {
            if ($lv >= (int) $at) {
                $total += $bonus;
            }
        }

        return $total;
    }

    /** @return array{name:string, icon:string, min:int} */
    public function tier(int $stars): array
    {
        $found = $this->cfg['tiers'][0];
        foreach ($this->cfg['tiers'] as $t) {
            if ($stars >= $t[0]) {
                $found = $t;
            }
        }

        return ['min' => $found[0], 'name' => $found[1], 'icon' => $found[2] ?? ''];
    }

    /** XP untuk hasil Fight Night dari sudut pandang satu petarung. */
    public function fightXp(string $outcome, bool $byKo = false): int
    {
        $X = $this->cfg['xp'];

        return match ($outcome) {
            'win' => $X['win'] + ($byKo ? $X['ko_bonus'] : 0),
            'draw' => $X['draw'],
            default => $X['lose'],
        };
    }

    /** Validasi pembagian poin: total tidak melebihi jatah, tiap nilai >= 0. */
    public function validPoints(array $points, int $lv, array $statKeys): bool
    {
        $sum = 0;
        foreach ($points as $k => $v) {
            if (! in_array($k, $statKeys, true) || ! is_int($v) || $v < 0) {
                return false;
            }
            $sum += $v;
        }

        return $sum <= $this->pointsFor($lv);
    }

    /**
     * Pemeriksaan lengkap pembagian poin dari editor stat.
     *
     * @param  array<string,mixed>  $points  stat => poin
     * @param  array<string,int>  $base  stat dasar petarung (menentukan kunci yang sah)
     * @return string|null null bila sah; selain itu kode galat: invalid, over_budget, over_max
     */
    public function checkPoints(array $points, int $lv, array $base, int $statMax): ?string
    {
        if (! $this->validPoints($points, $lv, array_keys($base))) {
            $sum = 0;
            foreach ($points as $k => $v) {
                if (! array_key_exists($k, $base) || ! is_int($v) || $v < 0) {
                    return 'invalid';
                }
                $sum += $v;
            }

            return 'over_budget';
        }
        foreach ($points as $k => $v) {
            if ($base[$k] + $v > $statMax) {
                return 'over_max';
            }
        }

        return null;
    }

    /** Ada poin yang DIPINDAH (bukan sekadar ditambah)? Itu dibatasi waktu (points.respec_days). */
    public static function isRespec(array $old, array $new): bool
    {
        foreach ($old as $k => $v) {
            if ((int) $v > (int) ($new[$k] ?? 0)) {
                return true;
            }
        }

        return false;
    }
}
