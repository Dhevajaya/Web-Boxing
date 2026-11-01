<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Membaca konfigurasi game: nilai di tabel `settings` (diubah admin)
 * mengalahkan nilai bawaan di config/game.php.
 *
 *   app(GameConfig::class)->get('xp.win')
 */
class GameConfig
{
    private ?array $overrides = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->overrides();
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        $value = config('game.'.$key, $default);

        // Minta satu kelompok (misalnya 'xp')? Gabungkan override di dalamnya ('xp.win').
        if (is_array($value)) {
            foreach ($overrides as $k => $v) {
                if (str_starts_with($k, $key.'.')) {
                    Arr::set($value, substr($k, strlen($key) + 1), $v);
                }
            }
        }

        return $value;
    }

    public function feature(string $name): bool
    {
        return (bool) $this->get('features.'.$name, false);
    }

    /** Nilai efektif untuk dikirim ke JavaScript (tanpa rahasia). */
    public function publicConfig(): array
    {
        $all = config('game');
        unset($all['instagram']);
        foreach ($this->overrides() as $key => $value) {
            if (! str_starts_with($key, 'instagram.')) {
                Arr::set($all, $key, $value);
            }
        }

        return $all;
    }

    public function currentSeasonNumber(): int
    {
        return (int) Cache::remember('game.season.current', 60, function () {
            try {
                return (int) (DB::table('seasons')->where('status', 'active')->value('number') ?? 0);
            } catch (Throwable) {
                return 0;
            }
        });
    }

    public function flush(): void
    {
        $this->overrides = null;
        Cache::forget('game.settings');
        Cache::forget('game.season.current');
    }

    /** @return array<string, mixed> */
    private function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        try {
            return $this->overrides = Cache::remember('game.settings', 60, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return DB::table('settings')->pluck('value', 'key')
                    ->map(fn ($v) => json_decode($v, true))
                    ->all();
            });
        } catch (Throwable) {
            // Database belum siap (misalnya saat install awal): pakai config saja.
            return $this->overrides = [];
        }
    }
}
