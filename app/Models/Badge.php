<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lencana permanen. Kode: juara:s0, perintis:s0, piala:2026-11-02, pendukung:vip.
 * Label tampilan dibuat di JavaScript (resources/js/ui.js → badgeLabel).
 */
class Badge extends Model
{
    public $timestamps = false;

    protected $fillable = ['fighter_id', 'code', 'meta', 'awarded_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'awarded_at' => 'datetime'];
    }

    public static function award(int $fighterId, string $code, array $meta = []): void
    {
        self::firstOrCreate(['fighter_id' => $fighterId, 'code' => $code], ['meta' => $meta ?: null, 'awarded_at' => now()]);
    }
}
