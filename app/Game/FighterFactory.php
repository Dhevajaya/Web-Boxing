<?php

namespace App\Game;

/**
 * Petarung dasar yang diturunkan dari username: gaya, stat dasar,
 * julukan/kota/kelas bawaan. Sama persis dengan resources/js/game/fighter.js.
 */
final class FighterFactory
{
    public function __construct(private readonly Rules $rules) {}

    public function rules(): Rules
    {
        return $this->rules;
    }

    /**
     * @return array{username:string, style:string, styleName:string, base:array<string,int>, nick:string, kota:string, kelas:string}
     */
    public function base(string $username, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        $c = $this->rules->content;
        $f = $R['fighter'];
        $rng = Rng::fromString($f['salt'].'|'.strtolower($username));

        $styles = $R['styles'] ?? $R['archetypes'];
        $style = $styles[$rng->int(count($styles))];
        $base = [];
        foreach ($R['stats'] as $k) {
            $v = $f['baseMin'] + $rng->int($f['baseRange']) + ($style['bias'][$k] ?? 0);
            $base[$k] = max($f['clampMin'], min($f['clampMax'], $v));
        }

        return [
            'v2' => $R['version'] >= 2,
            'username' => strtolower($username),
            'style' => $style['id'],
            'styleName' => $style['name'],
            'base' => $base,
            'nick' => $c['nicks'][$rng->int(count($c['nicks']))],
            'kota' => $c['kota'][$rng->int(count($c['kota']))],
            'kelas' => $c['kelas'][$rng->int(count($c['kelas']))],
        ];
    }

    /** Input laga untuk username tanpa data pemain (pratinjau mesin, tamu): stat dasar + jurus khas. */
    public function defaultInput(string $username, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        $b = $this->base($username, $version);
        $in = ['style' => $b['style'], 'stats' => $b['base']];
        if ($R['version'] >= 2) {
            $sig = array_values(array_filter($R['archetypes'], fn ($a) => $a['id'] === $b['style']))[0]['signature'];
            $in['jurus'] = [$sig];
        }

        return $in;
    }

    /**
     * Stat dasar dengan archetype tertentu (v2): angka acak dari username sama, bias diganti
     * bias archetype pilihan. v1 atau archetype null = stat dasar biasa.
     *
     * @return array<string,int>
     */
    public function baseWithStyle(string $username, ?string $style, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        $b = $this->base($username, $version);
        if ($R['version'] < 2 || ! $style || $style === $b['style']) {
            return $b['base'];
        }
        $to = null;
        foreach ($R['archetypes'] as $a) {
            $to = $a['id'] === $style ? $a : $to;
        }
        if (! $to) {
            return $b['base'];
        }
        // Ulangi urutan acak base() persis, hanya biasnya diganti archetype pilihan.
        $f = $R['fighter'];
        $rng = Rng::fromString($f['salt'].'|'.strtolower($username));
        $rng->int(count($R['archetypes']));
        $out = [];
        foreach ($R['stats'] as $k) {
            $v = $f['baseMin'] + $rng->int($f['baseRange']) + ($to['bias'][$k] ?? 0);
            $out[$k] = max($f['clampMin'], min($f['clampMax'], $v));
        }

        return $out;
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
