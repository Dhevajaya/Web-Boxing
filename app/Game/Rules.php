<?php

namespace App\Game;

use RuntimeException;

/**
 * Aturan mesin pertarungan per versi, dibaca dari resources/game/rules.v{n}.json.
 * Semua versi lama tetap dimuat agar pertarungan lama bisa diputar ulang.
 */
final class Rules
{
    /** @var array<int, array> */
    private array $versions = [];

    private function __construct(
        private readonly string $dir,
        public readonly int $current,
        public readonly array $content,
    ) {}

    public static function load(string $dir, int $current): self
    {
        $contentFile = $dir.'/content.json';
        if (! is_file($contentFile)) {
            throw new RuntimeException("File konten game tidak ditemukan: {$contentFile}");
        }

        return new self($dir, $current, json_decode(file_get_contents($contentFile), true, flags: JSON_THROW_ON_ERROR));
    }

    public function version(?int $v = null): array
    {
        $v ??= $this->current;
        if (! isset($this->versions[$v])) {
            $file = "{$this->dir}/rules.v{$v}.json";
            if (! is_file($file)) {
                throw new RuntimeException("Aturan mesin versi {$v} tidak ditemukan: {$file}");
            }
            $rules = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            if ($v >= 2 && ($errors = RulesSchema::errors($rules))) {
                throw new RuntimeException("Aturan mesin versi {$v} tidak valid:\n- ".implode("\n- ", $errors));
            }
            $this->versions[$v] = $rules;
        }

        return $this->versions[$v];
    }
}
