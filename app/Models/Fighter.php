<?php

namespace App\Models;

use App\Game\FighterFactory;
use App\Game\Looks;
use App\Game\Progression;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Petarung = pemain. Masuk tanpa password (lewat DM Instagram), jadi kolom
 * password dan remember token tidak ada; sesi dikelola App\Services\Auth\PlayerAuth.
 */
class Fighter extends Model implements AuthenticatableContract
{
    use Authenticatable;

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    protected $fillable = [
        'instagram_username', 'ig_scoped_id', 'status', 'is_npc', 'look', 'nick', 'kota', 'identity_changed_at',
        'points', 'points_respec_at', 'xp', 'level', 'sasana_id', 'sasana_season',
        'supporter_tier', 'supporter_anonymous', 'verified_at', 'activated_at', 'last_active_at',
    ];

    protected $hidden = ['ig_scoped_id'];

    protected function casts(): array
    {
        return [
            'is_npc' => 'boolean',
            'supporter_anonymous' => 'boolean',
            'look' => 'array',
            'points' => 'array',
            'points_respec_at' => 'datetime',
            'identity_changed_at' => 'datetime',
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /** Normalisasi username Instagram: huruf kecil, tanpa @, pola IG. Null bila tidak sah. */
    public static function normalizeUsername(?string $u): ?string
    {
        $u = strtolower(ltrim(trim((string) $u), '@'));

        return preg_match('/^[a-z0-9._]{1,30}$/', $u) ? $u : null;
    }

    public static function findByUsername(string $username): ?self
    {
        $u = self::normalizeUsername($username);

        return $u ? self::where('instagram_username', $u)->first() : null;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopePlayers(Builder $q): Builder
    {
        return $q->where('is_npc', false);
    }

    public function sasana(): BelongsTo
    {
        return $this->belongsTo(Sasana::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(FighterRecord::class);
    }

    /** Petarung dasar dari hash username (gaya, stat dasar, julukan bawaan). */
    public function base(?int $version = null): array
    {
        return app(FighterFactory::class)->base($this->instagram_username, $version);
    }

    /** Stat efektif untuk laga: dasar + poin + bonus latihan hari ini. */
    public function effectiveStats(array $bonus = [], ?int $version = null): array
    {
        return app(FighterFactory::class)->effective($this->base($version)['base'], $this->points ?? [], $bonus, $version);
    }

    /** Input untuk FightSimulator. */
    public function fightInput(array $bonus = [], ?int $version = null): array
    {
        return ['style' => $this->base($version)['style'], 'stats' => $this->effectiveStats($bonus, $version)];
    }

    /** Tampilan avatar: pilihan tersimpan di atas bawaan dari username. */
    public function resolvedLook(): array
    {
        return app(Looks::class)->resolve($this->look, $this->instagram_username);
    }

    public function levelNumber(): int
    {
        return Progression::fromConfig()->level((int) $this->xp)['lv'];
    }

    public function displayNick(): string
    {
        $b = $this->base();

        return ($this->nick ?: $b['nick']).' dari '.($this->kota ?: $b['kota']);
    }
}
