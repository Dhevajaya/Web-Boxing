<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Fighter;
use App\Models\Sasana;
use App\Models\Season;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Sasana: empat kubu. Pemain memilih satu per season (tidak bisa pindah sampai season berikutnya).
 * Perang Sasana: anggota menyumbang 3 poin per menang, 1 per seri, +1 tiap KO di Fight Night season ini.
 */
class SasanaController extends Controller
{
    public function index(GameConfig $game)
    {
        $season = $game->currentSeasonNumber();
        $counts = Fighter::players()->active()->where('sasana_season', $season)->groupBy('sasana_id')
            ->selectRaw('sasana_id, COUNT(*) as n')->pluck('n', 'sasana_id');

        return $this->ok(Sasana::orderBy('id')->get()->map(fn ($s) => $s->only(['slug', 'name', 'icon', 'color', 'motto']) + ['members' => (int) ($counts[$s->id] ?? 0)]));
    }

    public function war()
    {
        $season = Season::current();
        $rows = collect();
        if ($season) {
            $rows = DB::table('fighters as f')->join('fighter_records as r', function ($j) use ($season) {
                $j->on('r.fighter_id', '=', 'f.id')->where('r.season_id', '=', $season->id);
            })->where('f.is_npc', false)->where('f.status', 'active')->where('f.sasana_season', $season->number)->whereNotNull('f.sasana_id')
                ->groupBy('f.sasana_id')->selectRaw('f.sasana_id, SUM(r.w) as w, SUM(r.d) as d, SUM(r.ko) as ko')->get()->keyBy('sasana_id');
        }
        $members = Fighter::players()->active()->where('sasana_season', $season?->number)->groupBy('sasana_id')
            ->selectRaw('sasana_id, COUNT(*) as n')->pluck('n', 'sasana_id');

        $out = Sasana::orderBy('id')->get()->map(function ($s) use ($rows, $members) {
            $r = $rows->get($s->id);
            $w = (int) ($r->w ?? 0);
            $d = (int) ($r->d ?? 0);
            $ko = (int) ($r->ko ?? 0);

            return $s->only(['slug', 'name', 'icon', 'color', 'motto']) + [
                'members' => (int) ($members[$s->id] ?? 0), 'w' => $w, 'd' => $d, 'ko' => $ko, 'points' => $w * 3 + $d + $ko,
            ];
        })->sortByDesc('points')->values();

        return $this->ok(['season' => $season?->only(['number', 'name']), 'rows' => $out])->header('Cache-Control', 'public, max-age=60');
    }

    /** PUT /api/me/sasana { slug } */
    public function choose(Request $request, GameConfig $game)
    {
        /** @var Fighter $me */
        $me = Auth::guard('fighter')->user();
        $sasana = Sasana::where('slug', (string) $request->input('slug'))->first();
        if (! $sasana) {
            return $this->fail('invalid', 'Sasana tidak dikenal.');
        }
        $season = $game->currentSeasonNumber();
        if ($me->sasana_id && (int) $me->sasana_season === $season) {
            return $this->fail('locked', 'Kamu sudah memilih sasana untuk season ini. Bisa pindah di season berikutnya.', 409);
        }

        $me->forceFill(['sasana_id' => $sasana->id, 'sasana_season' => $season])->save();
        AuditLog::record('sasana_joined', $me, ['sasana' => $sasana->slug, 'season' => $season], 'fighter', $me->id);

        return $this->ok(['sasana' => $sasana->only(['slug', 'name', 'icon', 'color']), 'season' => $season]);
    }
}
