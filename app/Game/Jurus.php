<?php

namespace App\Game;

/**
 * Aturan kepemilikan jurus (v2): jurus khas archetype terbuka di Lv 1, jurus umum terbuka
 * sesuai level. Pemain memasang maksimal jurusSlots jurus; tanpa pilihan = jurus khas.
 */
final class Jurus
{
    public function __construct(private readonly Rules $rules) {}

    /** @return list<array> jurus yang boleh dipakai archetype + level ini, dengan status terbuka/terkunci */
    public function catalog(string $archetype, int $level, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        $out = [];
        foreach ($R['jurus'] ?? [] as $j) {
            if ($j['archetype'] !== null && $j['archetype'] !== $archetype) {
                continue;
            }
            $out[] = $j + ['open' => $level >= $j['unlock']];
        }

        return $out;
    }

    /** @return list<string> */
    public function equipped(string $archetype, int $level, array $chosen, ?int $version = null): array
    {
        $R = $this->rules->version($version);
        if (empty($R['jurus'])) {
            return [];
        }
        $open = array_column(array_filter($this->catalog($archetype, $level, $version), fn ($j) => $j['open']), null, 'id');
        $out = [];
        foreach ($chosen as $id) {
            if (is_string($id) && isset($open[$id]) && ! in_array($id, $out, true) && count($out) < $R['jurusSlots']) {
                $out[] = $id;
            }
        }
        if (! $out) {
            foreach ($R['archetypes'] as $a) {
                if ($a['id'] === $archetype) {
                    $out[] = $a['signature'];
                }
            }
        }

        return $out;
    }

    /** Validasi pilihan pemain: null = sah. */
    public function invalid(string $archetype, int $level, mixed $chosen, ?int $version = null): ?string
    {
        $R = $this->rules->version($version);
        if (! is_array($chosen) || ! array_is_list($chosen) || count($chosen) < 1 || count($chosen) > $R['jurusSlots']) {
            return "Pilih 1 sampai {$R['jurusSlots']} jurus.";
        }
        if (count(array_unique($chosen)) !== count($chosen)) {
            return 'Jurus tidak boleh dobel.';
        }
        $cat = array_column($this->catalog($archetype, $level, $version), null, 'id');
        foreach ($chosen as $id) {
            if (! is_string($id) || ! isset($cat[$id])) {
                return 'Jurus tidak dikenal untuk gaya bertarungmu.';
            }
            if (! $cat[$id]['open']) {
                return "{$cat[$id]['name']} terbuka di Level {$cat[$id]['unlock']}.";
            }
        }

        return null;
    }
}
