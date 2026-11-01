<?php

namespace App\Services\Game;

use App\Game\Progression;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Fighter;
use App\Models\Season;
use App\Support\GameConfig;
use Illuminate\Support\Facades\DB;

/**
 * Menutup season: simpan peringkat akhir, beri lencana, buka season baru,
 * bawa XP sesuai season.xp_carry_percent. Bintang mulai dari 0 di season baru
 * (rekor season dicatat per season di fighter_records).
 */
class Seasons
{
    public function __construct(private readonly GameConfig $game) {}

    /** @return array{closed: Season, next: Season, ranked: int} */
    public function close(?string $nextName = null, ?int $adminId = null): array
    {
        $result = DB::transaction(function () use ($nextName) {
            $season = Season::where('status', 'active')->lockForUpdate()->orderByDesc('number')->firstOrFail();

            $rows = DB::table('fighter_records as r')->join('fighters as f', 'f.id', '=', 'r.fighter_id')
                ->where('r.season_id', $season->id)->where('f.is_npc', false)
                ->whereRaw('(r.w + r.l + r.d) > 0')
                ->orderByDesc('r.stars')->orderByDesc('r.w')->orderBy('r.l')->orderBy('f.id')
                ->get(['r.fighter_id', 'r.w', 'r.l', 'r.d', 'r.stars']);

            $top = (int) $this->game->get('season.champion_top');
            $now = now();
            $insert = [];
            foreach ($rows as $i => $r) {
                $rank = $i + 1;
                $insert[] = [
                    'season_id' => $season->id, 'fighter_id' => $r->fighter_id, 'rank' => $rank,
                    'w' => $r->w, 'l' => $r->l, 'd' => $r->d, 'stars' => $r->stars,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if ($rank <= $top) {
                    Badge::award((int) $r->fighter_id, "juara:s{$season->number}", ['rank' => $rank, 'season' => $season->number]);
                }
                if ($season->number === 0) {
                    Badge::award((int) $r->fighter_id, 'perintis:s0', ['season' => 0]);
                }
            }
            foreach (array_chunk($insert, 500) as $part) {
                DB::table('season_results')->insertOrIgnore($part);
            }

            $season->forceFill([
                'status' => 'closed', 'ends_at' => $now,
                'config_snapshot' => app(GameConfig::class)->publicConfig(),
            ])->save();

            $number = $season->number + 1;
            $next = Season::create([
                'number' => $number, 'name' => $nextName ?: "Season {$number}",
                'starts_at' => $now, 'status' => 'active',
            ]);

            $carry = (int) $this->game->get('season.xp_carry_percent');
            if ($carry < 100) {
                $prog = Progression::fromConfig();
                Fighter::players()->where('xp', '>', 0)->select(['id', 'xp'])->chunkById(500, function ($fs) use ($carry, $prog) {
                    foreach ($fs as $f) {
                        $xp = intdiv((int) $f->xp * $carry, 100);
                        Fighter::whereKey($f->id)->update(['xp' => $xp, 'level' => $prog->level($xp)['lv']]);
                    }
                });
            }

            return ['closed' => $season, 'next' => $next, 'ranked' => count($insert)];
        });

        $this->game->flush();
        AuditLog::record('season_closed', $result['closed'], ['next' => $result['next']->number, 'ranked' => $result['ranked']], $adminId ? 'admin' : 'system', $adminId);

        return $result;
    }
}
