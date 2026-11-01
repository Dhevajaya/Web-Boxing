<?php

namespace App\Services\Game;

use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Fighter;
use App\Support\GameConfig;
use Illuminate\Support\Facades\DB;

/** Donasi -> tier pendukung (Tribun < Ringside < VIP). Tier tidak pernah turun. */
class Supporters
{
    public const ORDER = ['tribun' => 1, 'ringside' => 2, 'vip' => 3];

    public function __construct(private readonly GameConfig $game) {}

    public static function rank(?string $tier): int
    {
        return self::ORDER[$tier] ?? 0;
    }

    /** Ambil @username pertama dari teks (pesan donasi atau nama donatur). */
    public static function extractUsername(?string ...$texts): ?string
    {
        foreach ($texts as $t) {
            if ($t && preg_match('/@([A-Za-z0-9._]{1,30})/', $t, $m)) {
                return Fighter::normalizeUsername($m[1]);
            }
        }

        return null;
    }

    /**
     * Catat donasi (idempoten). Mengembalikan petarung yang cocok, bila ada.
     */
    public function record(string $provider, string $externalId, int $amount, ?string $donor, ?string $message, array $raw = []): ?Fighter
    {
        $username = self::extractUsername($message, $donor);
        $fighter = $username ? Fighter::where('instagram_username', $username)->where('is_npc', false)->first() : null;

        $inserted = DB::table('donations')->insertOrIgnore([
            'provider' => $provider, 'external_id' => mb_substr($externalId, 0, 96), 'fighter_id' => $fighter?->id,
            'username' => $username, 'donor_name' => $donor ? mb_substr($donor, 0, 80) : null, 'amount' => max(0, $amount),
            'message' => $message ? mb_substr($message, 0, 255) : null, 'raw' => $raw ? json_encode($raw) : null, 'created_at' => now(),
        ]);
        if ($inserted && $fighter) {
            $this->refreshTier($fighter);
        }

        return $fighter;
    }

    public function refreshTier(Fighter $fighter): ?string
    {
        $total = (int) DB::table('donations')->where('fighter_id', $fighter->id)->sum('amount');
        $tier = null;
        foreach ((array) $this->game->get('supporter.tiers') as $t) {
            if ($total >= (int) $t['min']) {
                $tier = $t['id'];
            }
        }
        if ($tier && self::rank($tier) > self::rank($fighter->supporter_tier)) {
            $fighter->forceFill(['supporter_tier' => $tier])->save();
            Badge::award($fighter->id, 'pendukung:'.$tier, ['total' => $total]);
            AuditLog::record('supporter_tier', $fighter, ['tier' => $tier, 'total' => $total]);
        }

        return $fighter->supporter_tier;
    }
}
