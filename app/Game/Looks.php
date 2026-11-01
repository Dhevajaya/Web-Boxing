<?php

namespace App\Game;

use RuntimeException;

/**
 * Tampilan avatar petarung, dari katalog resources/game/looks.json.
 *
 * - Setiap username punya tampilan bawaan yang diturunkan dari hash (sama persis
 *   dengan resources/js/game/looks.js), jadi petarung yang belum daftar pun punya wajah.
 * - Pemain yang sudah masuk boleh mengganti tiap bagian; item ber-'lv' baru terbuka
 *   di level itu.
 *
 * Kelas murni (tanpa Laravel) agar bisa dites dari tools/ seperti mesin pertarungan.
 */
final class Looks
{
    /** @var array<string, array{key:string, items:array, defaultPool:array}> */
    private array $parts = [];

    /** @var array<string, array<string, array>> part => id => item */
    private array $index = [];

    public function __construct(private readonly array $catalog)
    {
        foreach ($catalog['parts'] as $p) {
            $this->parts[$p['key']] = $p;
            foreach ($p['items'] as $item) {
                $this->index[$p['key']][$item['id']] = $item;
            }
        }
    }

    public static function load(string $dir): self
    {
        $file = $dir.'/looks.json';
        if (! is_file($file)) {
            throw new RuntimeException("Katalog tampilan tidak ditemukan: {$file}");
        }

        return new self(json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR));
    }

    public function catalog(): array
    {
        return $this->catalog;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->parts);
    }

    /**
     * Tampilan bawaan dari username.
     *
     * @return array<string, string>
     */
    public function default(string $username): array
    {
        $rng = Rng::fromString($this->catalog['salt'].'|'.strtolower($username));
        $out = [];
        foreach ($this->parts as $key => $p) {
            $pool = $p['defaultPool'];
            $out[$key] = $pool[$rng->int(count($pool))];
        }

        return $out;
    }

    /**
     * Tampilan untuk ditampilkan: bawaan, ditimpa pilihan tersimpan yang masih ada di katalog.
     *
     * @return array<string, string>
     */
    public function resolve(?array $stored, string $username): array
    {
        $out = $this->default($username);
        foreach ($stored ?? [] as $key => $id) {
            if (is_string($id) && isset($this->index[$key][$id])) {
                $out[$key] = $id;
            }
        }

        return $out;
    }

    public function unlockLevel(string $key, string $id): int
    {
        return (int) ($this->index[$key][$id]['lv'] ?? 1);
    }

    /** Tier pendukung minimal untuk item, atau null. */
    public function supporterTier(string $key, string $id): ?string
    {
        return $this->index[$key][$id]['supporter'] ?? null;
    }

    private const TIERS = ['tribun' => 1, 'ringside' => 2, 'vip' => 3];

    /**
     * Memeriksa pilihan pemain. Bagian yang tidak dikirim diambil dari $current.
     * Item terkunci tetap boleh dipertahankan bila sudah dipakai sebelumnya
     * (misalnya level turun setelah pergantian season).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $current  tampilan saat ini (hasil resolve)
     * @return array{look: array<string,string>, errors: array<string,string>}
     */
    public function validate(array $input, array $current, int $level, ?string $supporter = null): array
    {
        $look = $current;
        $errors = [];

        foreach ($input as $key => $id) {
            if (! isset($this->parts[$key])) {
                $errors[(string) $key] = 'unknown_part';

                continue;
            }
            if (! is_string($id) || ! isset($this->index[$key][$id])) {
                $errors[$key] = 'unknown_item';

                continue;
            }
            $keep = ($current[$key] ?? null) === $id;
            if ($this->unlockLevel($key, $id) > $level && ! $keep) {
                $errors[$key] = 'locked';

                continue;
            }
            $need = $this->supporterTier($key, $id);
            if ($need && (self::TIERS[$supporter] ?? 0) < self::TIERS[$need] && ! $keep) {
                $errors[$key] = 'supporter';

                continue;
            }
            $look[$key] = $id;
        }

        return ['look' => $look, 'errors' => $errors];
    }
}
