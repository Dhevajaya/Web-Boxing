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
        'instagram_username', 'ig_scoped_id', 'status', 'muted_until', 'is_npc', 'look', 'nick', 'kota', 'identity_changed_at',
        'points', 'points_respec_at', 'archetype', 'archetype_changed_at', 'jurus', 'xp', 'level', 'sasana_id', 'sasana_season',
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
            'archetype_changed_at' => 'datetime',
            'jurus' => 'array',
            'identity_changed_at' => 'datetime',
            'muted_until' => 'datetime',
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
        return app(FighterFactory::class)->effective($this->baseFor($version), $this->points ?? [], $bonus, $version);
    }

    /** Stat dasar: hash username + bias archetype pilihan (v2) atau gaya bawaan (v1). */
    public function baseFor(?int $version = null): array
    {
        return app(FighterFactory::class)->baseWithStyle($this->instagram_username, $this->archetypeId($version), $version);
    }

    /** Archetype aktif (v2): pilihan pemain, atau bawaan dari username. */
    public function archetypeId(?int $version = null): string
    {
        $b = $this->base($version);
        if (! ($b['v2'] ?? false)) {
            return $b['style'];
        }
        $ids = array_column(app(\App\Game\Rules::class)->version($version)['archetypes'], 'id');

        return in_array($this->archetype, $ids, true) ? $this->archetype : $b['style'];
    }

    /** Jurus aktif yang sah untuk level dan archetype sekarang (v2). Kosong = jurus khas. */
    public function equippedJurus(?int $version = null): array
    {
        return app(\App\Game\Jurus::class)->equipped($this->archetypeId($version), $this->levelNumber(), $this->jurus ?? [], $version);
    }

    /** Input untuk FightSimulator. */
    public function fightInput(array $bonus = [], ?int $version = null): array
    {
        $in = ['style' => $this->archetypeId($version), 'stats' => $this->effectiveStats($bonus, $version)];
        if ($this->base($version)['v2'] ?? false) {
            $in['jurus'] = $this->equippedJurus($version);
        }

        return $in;
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

    /** Ringkasan untuk daftar (ranking, laga, chat): tanpa data pribadi. */
    public function summary(): array
    {
        $b = $this->base();

        return [
            'username' => $this->instagram_username,
            'nick' => ($this->nick ?: $b['nick']).' dari '.($this->kota ?: $b['kota']),
            'look' => $this->resolvedLook(),
            'level' => Progression::fromConfig()->level((int) $this->xp)['lv'],
            'style' => $this->archetype ?: $b['style'],
            'npc' => (bool) $this->is_npc,
            'supporter' => $this->supporter_anonymous ? null : $this->supporter_tier,
        ];
    }

    public function displayNick(): string
    {
        $b = $this->base();

        return ($this->nick ?: $b['nick']).' dari '.($this->kota ?: $b['kota']);
    }
}
