<?php

namespace App\Game;

/**
 * Petarung dasar yang diturunkan dari username: gaya, stat dasar,
 * julukan/kota/kelas bawaan. Sama persis dengan resources/js/game/fighter.js.
 */
final class FighterFactory
{
    public function __construct(private readonly Rules $rules) {}

    /**
     * @return array{username:string, style:string, styleName:string, base:array<string,int>, nick:string, kota:string, kelas:string}
     */
    public function base(string $username, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        $c = $this->rules->content;
        $f = $R['fighter'];
        $rng = Rng::fromString($f['salt'].'|'.strtolower($username));

        $style = $R['styles'][$rng->int(count($R['styles']))];
        $base = [];
        foreach ($R['stats'] as $k) {
            $v = $f['baseMin'] + $rng->int($f['baseRange']) + ($style['bias'][$k] ?? 0);
            $base[$k] = max($f['clampMin'], min($f['clampMax'], $v));
        }

        return [
            'username' => strtolower($username),
            'style' => $style['id'],
            'styleName' => $style['name'],
            'base' => $base,
            'nick' => $c['nicks'][$rng->int(count($c['nicks']))],
            'kota' => $c['kota'][$rng->int(count($c['kota']))],
            'kelas' => $c['kelas'][$rng->int(count($c['kelas']))],
        ];
    }

    /**
     * Stat efektif = dasar + poin + bonus (latihan hari ini), dibatasi statMax.
     *
     * @param  array<string,int>  $base
     * @param  array<string,int>  $points
     * @param  array<string,int>  $bonus
     * @return array<string,int>
     */
    public function effective(array $base, array $points = [], array $bonus = [], ?int $version = null): array
    {
        $R = $this->rules->version($version);
        $out = [];
        foreach ($R['stats'] as $k) {
            $out[$k] = min($R['statMax'], max(1, (int) $base[$k] + (int) ($points[$k] ?? 0) + (int) ($bonus[$k] ?? 0)));
        }

        return $out;
    }

    /** Nilai keseluruhan (OVR) 1..99. */
    public function ovr(array $stats, ?int $version = null): int
    {
        $w = $this->rules->version($version)['ovr'];
        $sum = 0;
        foreach ($w as $k => $weight) {
            $sum += $weight * $stats[$k];
        }

        return intdiv($sum + 50, 100);
    }
}
