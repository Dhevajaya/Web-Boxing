<?php

namespace App\Game;

/**
 * Aturan julukan dan kota buatan pemain. Kelas murni, angkanya dari config/game.php → profile.
 *
 * Teks kosong berarti "pakai bawaan dari username" (disimpan sebagai null).
 */
final class NameRules
{
    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', '@' => 'a', '$' => 's', '!' => 'i'];

    /**
     * @param  array{nick_min:int, nick_max:int, kota_min:int, kota_max:int, banned_words:list<string>}  $cfg
     */
    public function __construct(private readonly array $cfg) {}

    /** Rapikan spasi; kembalikan null bila kosong. */
    public static function clean(?string $s): ?string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', (string) $s));

        return $s === '' ? null : $s;
    }

    /** @return array{0:?string, 1:?string} [nilai bersih atau null, kode galat atau null] */
    public function nick(?string $s): array
    {
        return $this->check($s, $this->cfg['nick_min'], $this->cfg['nick_max']);
    }

    /** @return array{0:?string, 1:?string} */
    public function kota(?string $s): array
    {
        return $this->check($s, $this->cfg['kota_min'], $this->cfg['kota_max']);
    }

    /** @return array{0:?string, 1:?string} */
    private function check(?string $s, int $min, int $max): array
    {
        $s = self::clean($s);
        if ($s === null) {
            return [null, null];
        }

        $len = mb_strlen($s);
        if ($len < $min) {
            return [null, 'too_short'];
        }
        if ($len > $max) {
            return [null, 'too_long'];
        }
        // Huruf (termasuk beraksen), angka, spasi, titik, tanda petik, tanda hubung.
        // Tanpa @, tautan, atau emoji agar tidak dipakai untuk spam/menyamar.
        if (! preg_match("/^[\\p{L}\\p{N} .'\\-]+$/u", $s)) {
            return [null, 'bad_chars'];
        }
        if (! preg_match('/\p{L}/u', $s)) {
            return [null, 'no_letter'];
        }
        if ($this->isBanned($s)) {
            return [null, 'banned'];
        }

        return [$s, null];
    }

    public function isBanned(string $s): bool
    {
        $t = strtr(mb_strtolower($s), self::LEET);
        $t = (string) preg_replace('/[^\p{L}]+/u', ' ', $t);
        $tokens = array_filter(explode(' ', $t));
        $joined = implode('', $tokens);
        // "aaanjiiing" -> "anjing": huruf berulang dipadatkan.
        $squeezed = (string) preg_replace('/(\p{L})\1+/u', '$1', $joined);

        foreach ($this->cfg['banned_words'] as $w) {
            $w = mb_strtolower(trim($w));
            if ($w === '') {
                continue;
            }
            // Kata pendek dicocokkan utuh (agar "asu" tidak memblokir "asuransi"),
            // kata panjang dicocokkan di mana saja.
            if (mb_strlen($w) < 5) {
                if (in_array($w, $tokens, true)) {
                    return true;
                }
            } elseif (str_contains($joined, $w) || str_contains($squeezed, $w)) {
                return true;
            }
        }

        return false;
    }
}
