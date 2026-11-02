<?php

namespace App\Game;

/**
 * Pemeriksa struktur aturan v2 (KAMPUNG TENDANG). Aturan yang salah ketik harus gagal keras
 * saat dimuat, bukan diam-diam menghasilkan laga aneh.
 */
final class RulesSchema
{
    /** @return list<string> daftar galat; kosong = valid */
    public static function errors(array $r): array
    {
        $e = [];
        $need = fn (string $k) => array_key_exists($k, $r) || ! ($e[] = "kunci '{$k}' tidak ada");
        foreach (['version', 'stats', 'statNames', 'statMax', 'fighter', 'archetypes', 'ranges', 'techniques', 'defenses', 'jurus', 'meter', 'fight', 'ovr'] as $k) {
            $need($k);
        }
        if ($e) {
            return $e;
        }

        $stats = $r['stats'];
        if (count($stats) !== count(array_unique($stats))) {
            $e[] = 'stats berisi nama ganda';
        }
        $sameKeys = function (array $map, string $where) use ($stats, &$e) {
            $missing = array_diff($stats, array_keys($map));
            $extra = array_diff(array_keys($map), $stats);
            if ($missing || $extra) {
                $e[] = "{$where}: kunci stat tidak cocok (kurang: ".implode(',', $missing).'; lebih: '.implode(',', $extra).')';
            }
        };
        $sameKeys($r['statNames'], 'statNames');
        $sameKeys($r['ovr'], 'ovr');
        if (array_sum($r['ovr']) !== 100) {
            $e[] = 'ovr harus berjumlah 100, sekarang '.array_sum($r['ovr']);
        }

        $ranges = $r['ranges'];
        $tech = [];
        foreach ($r['techniques'] as $i => $t) {
            $id = $t['id'] ?? "#{$i}";
            if (isset($tech[$id])) {
                $e[] = "teknik '{$id}' ganda";
            }
            $tech[$id] = $t;
            if (! in_array($t['cat'] ?? null, ['hand', 'kick'], true)) {
                $e[] = "teknik '{$id}': cat harus hand atau kick";
            }
            foreach (['stamina', 'acc', 'dmg', 'score'] as $k) {
                if (! is_int($t[$k] ?? null)) {
                    $e[] = "teknik '{$id}': {$k} harus bilangan bulat";
                }
            }
            if (($t['dmg'] ?? 0) <= 0 || ($t['stamina'] ?? 0) <= 0) {
                $e[] = "teknik '{$id}': dmg dan stamina harus > 0";
            }
            if (! $t['ranges'] || array_diff($t['ranges'], $ranges)) {
                $e[] = "teknik '{$id}': ranges tidak valid";
            }
        }
        foreach (['hand', 'kick'] as $cat) {
            if (! array_filter($tech, fn ($t) => ($t['cat'] ?? '') === $cat)) {
                $e[] = "harus ada minimal satu teknik {$cat}";
            }
        }

        $jurus = [];
        foreach ($r['jurus'] as $j) {
            $id = $j['id'] ?? '?';
            $jurus[$id] = $j;
            foreach ($j['steps'] ?? [] as $s) {
                if (! isset($tech[$s])) {
                    $e[] = "jurus '{$id}': langkah '{$s}' bukan teknik";
                }
            }
            if (count($j['steps'] ?? []) < 2) {
                $e[] = "jurus '{$id}': minimal 2 langkah";
            }
            if (($j['dmgPm'] ?? 0) > $r['jurusDmgCapPm']) {
                $e[] = "jurus '{$id}': dmgPm melewati batas {$r['jurusDmgCapPm']}";
            }
            if (($j['meter'] ?? 0) <= 0 || $j['meter'] > $r['meter']['max']) {
                $e[] = "jurus '{$id}': meter harus 1..{$r['meter']['max']}";
            }
            if (array_diff($j['ranges'] ?? [], $ranges)) {
                $e[] = "jurus '{$id}': ranges tidak valid";
            }
        }

        $arch = [];
        foreach ($r['archetypes'] as $a) {
            $id = $a['id'] ?? '?';
            $arch[$id] = true;
            $sameKeys($a['bias'] ?? [], "archetype '{$id}'.bias");
            if (array_sum($a['prefs'] ?? []) !== 100) {
                $e[] = "archetype '{$id}': prefs harus berjumlah 100";
            }
            if (! in_array($a['rangePref'] ?? null, $ranges, true)) {
                $e[] = "archetype '{$id}': rangePref tidak valid";
            }
            $sig = $jurus[$a['signature'] ?? ''] ?? null;
            if (! $sig || ($sig['archetype'] ?? null) !== $id || $sig['unlock'] !== 1) {
                $e[] = "archetype '{$id}': jurus khas harus jurus Lv 1 milik archetype ini";
            }
        }
        foreach ($jurus as $id => $j) {
            if ($j['archetype'] !== null && ! isset($arch[$j['archetype']])) {
                $e[] = "jurus '{$id}': archetype '{$j['archetype']}' tidak ada";
            }
        }

        $f = $r['fight'];
        if ($f['rounds'] < 1 || $f['ticks'] < 1 || $f['hp'] < 1) {
            $e[] = 'fight: rounds, ticks, dan hp harus positif';
        }
        if (! in_array($f['startRange'], $ranges, true)) {
            $e[] = 'fight.startRange tidak valid';
        }
        if ($f['hit']['min'] >= $f['hit']['max']) {
            $e[] = 'fight.hit: min harus < max';
        }

        // Semua angka harus bulat agar PHP dan JS identik.
        array_walk_recursive($r, function ($v, $k) use (&$e) {
            if (is_float($v)) {
                $e[] = "angka pecahan tidak boleh ({$k} = {$v}); pakai permil";
            }
        });

        return $e;
    }
}
