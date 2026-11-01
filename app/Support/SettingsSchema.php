<?php

namespace App\Support;

/**
 * Nilai config/game.php yang boleh diubah dari panel admin (disimpan di tabel settings).
 * type: string, int, float, bool, hours (daftar jam 0-23)
 */
final class SettingsSchema
{
    public static function groups(): array
    {
        return [
            'Identitas' => [
                ['brand.name', 'Nama game', 'string'],
                ['brand.tagline', 'Tagline', 'string'],
                ['brand.instagram', 'Akun Instagram resmi (tanpa @)', 'string'],
                ['brand.keyword', 'Kata kunci DM', 'string'],
            ],
            'Fitur' => [
                ['features.ref_login', 'Masuk sekali ketuk (ig.me?ref)', 'bool'],
                ['features.require_follow', 'Wajib follow akun resmi', 'bool'],
                ['features.piala', 'Piala Mingguan', 'bool'],
                ['features.sasana', 'Sasana', 'bool'],
                ['features.markas', 'Markas (chat)', 'bool'],
                ['features.latihan', 'Latihan', 'bool'],
                ['features.dukung', 'Dukung', 'bool'],
            ],
            'XP & level' => [
                ['xp.win', 'XP menang', 'int'],
                ['xp.ko_bonus', 'Bonus XP menang KO', 'int'],
                ['xp.draw', 'XP seri', 'int'],
                ['xp.lose', 'XP kalah', 'int'],
                ['xp.challenge_factor', 'Faktor XP tantangan', 'float'],
                ['xp.challenge_daily_cap', 'Tantangan ber-XP per hari', 'int'],
                ['xp.cup_win', 'XP menang laga piala', 'int'],
                ['level.base', 'XP level 1→2', 'int'],
                ['level.step', 'Tambahan XP tiap level', 'int'],
                ['points.per_level', 'Poin stat per level', 'int'],
                ['points.respec_days', 'Jeda pindah poin (hari)', 'int'],
            ],
            'Bintang & season' => [
                ['stars.win', 'Bintang menang', 'int'],
                ['stars.lose', 'Bintang kalah (negatif)', 'int'],
                ['stars.spar_range', 'Selisih bintang cari lawan', 'int'],
                ['season.xp_carry_percent', 'XP dibawa ke season baru (%)', 'int'],
                ['season.champion_top', 'Lencana Juara untuk N besar', 'int'],
            ],
            'Jadwal' => [
                ['fight_night.hours', 'Jam Fight Night (WIB, pisah koma)', 'hours'],
                ['fight_night.active_days', 'Ikut bila aktif dalam N hari', 'int'],
                ['piala.min_entrants', 'Minimal peserta piala', 'int'],
                ['piala.auto_active_days', 'Daftar otomatis bila aktif N hari (0 = mati)', 'int'],
            ],
            'Komunitas' => [
                ['challenge.daily_limit', 'Tantangan per hari', 'int'],
                ['challenge.same_opponent_minutes', 'Jeda tantang lawan sama (menit)', 'int'],
                ['chat.max_len', 'Panjang pesan chat', 'int'],
                ['chat.cooldown_sec', 'Jeda antar pesan (detik)', 'int'],
                ['chat.report_threshold', 'Laporan sampai pesan disembunyikan', 'int'],
                ['chat.mute_hours', 'Lama bisu otomatis (jam)', 'int'],
                ['profile.identity_cooldown_hours', 'Jeda ganti julukan (jam)', 'int'],
                ['training.xp_cap_per_game', 'XP latihan per game per hari', 'int'],
                ['training.bonus_max', 'Bonus stat latihan maksimal', 'int'],
            ],
        ];
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::groups() as $items) {
            foreach ($items as $i) {
                $out[$i[0]] = $i;
            }
        }

        return $out;
    }

    /** Ubah input form menjadi nilai bertipe. Null = tidak valid. */
    public static function cast(string $type, mixed $raw): mixed
    {
        $raw = is_string($raw) ? trim($raw) : $raw;

        return match ($type) {
            'bool' => (bool) $raw,
            'int' => is_numeric($raw) && (int) $raw == $raw ? (int) $raw : null,
            'float' => is_numeric($raw) ? (float) $raw : null,
            'string' => is_string($raw) && $raw !== '' && mb_strlen($raw) <= 120 ? $raw : null,
            'hours' => (function () use ($raw) {
                $h = array_values(array_unique(array_map('intval', array_filter(array_map('trim', explode(',', (string) $raw)), 'is_numeric'))));
                sort($h);

                return $h && min($h) >= 0 && max($h) <= 23 ? $h : null;
            })(),
            default => null,
        };
    }

    public static function display(string $type, mixed $v): string
    {
        return match ($type) {
            'bool' => $v ? 'ya' : 'tidak',
            'hours' => implode(', ', (array) $v),
            default => (string) $v,
        };
    }
}
