<?php

namespace App\Http\Controllers\Api;

use App\Game\FighterFactory;
use App\Game\Rng;
use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\Season;
use App\Services\Game\Calendar;
use App\Support\GameConfig;
use Illuminate\Support\Facades\Auth;

/** Cari lawan sparing: pemain aktif dengan bintang setara. Daftar berganti tiap hari. */
class SparController extends Controller
{
    public function index(GameConfig $game, FighterFactory $factory, Calendar $calendar)
    {
        /** @var Fighter $me */
        $me = Auth::guard('fighter')->user();
        $season = Season::current();
        $myStars = $season ? (int) FighterRecord::where('season_id', $season->id)->where('fighter_id', $me->id)->value('stars') : 0;
        $range = (int) $game->get('stars.spar_range');

        $candidates = Fighter::players()->active()->whereNotNull('activated_at')->where('id', '!=', $me->id)
            ->where('last_active_at', '>=', now()->subDays((int) $game->get('spar.active_days')))
            ->limit(500)->get();
        $stars = $season ? FighterRecord::where('season_id', $season->id)->whereIn('fighter_id', $candidates->pluck('id'))->pluck('stars', 'fighter_id') : collect();

        $rng = Rng::fromString('spar|'.$me->id.'|'.$calendar->gameDate());
        $list = $candidates->filter(fn ($f) => abs((int) ($stars[$f->id] ?? 0) - $myStars) <= $range)
            ->map(fn ($f) => ['f' => $f, 'k' => $rng->next()])->sortBy('k')->take((int) $game->get('spar.limit'))
            ->map(fn ($x) => $x['f']->summary() + [
                'stars' => (int) ($stars[$x['f']->id] ?? 0),
                'ovr' => $factory->ovr($x['f']->effectiveStats()),
                'sasana' => $x['f']->sasana?->only(['slug', 'icon', 'name', 'color']),
            ])->values();

        return $this->ok(['stars' => $myStars, 'range' => $range, 'rows' => $list])->header('Cache-Control', 'no-store');
    }
}
