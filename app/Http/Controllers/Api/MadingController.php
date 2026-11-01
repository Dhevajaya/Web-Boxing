<?php

namespace App\Http\Controllers\Api;

use App\Game\FighterFactory;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FightNightRun;
use App\Models\Season;
use App\Services\Game\Calendar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Papan Mading: pengumuman, sorotan Fight Night terakhir, 5 besar minggu ini, season. */
class MadingController extends Controller
{
    public function index(Calendar $calendar, FighterFactory $factory)
    {
        $data = Cache::remember('mading:v1', 30, function () use ($calendar, $factory) {
            $news = Announcement::published()->orderByDesc('pinned')->orderByDesc('published_at')->limit(10)
                ->get(['id', 'title', 'body', 'pinned', 'published_at'])
                ->map(fn ($a) => [
                    'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'pinned' => $a->pinned,
                    'at' => $a->published_at->toIso8601String(),
                ]);

            $run = FightNightRun::where('status', 'done')->orderByDesc('slot_at')->first();
            $night = null;
            if ($run) {
                $fights = Fight::where('kind', 'night')->where('slot_at', $run->slot_at)
                    ->get(['id', 'a_id', 'b_id', 'winner', 'how', 'round', 'a_power', 'a_speed', 'a_stamina', 'a_defense', 'a_chin', 'b_power', 'b_speed', 'b_stamina', 'b_defense', 'b_chin', 'a_style', 'b_style']);
                $kos = $fights->whereIn('how', ['KO', 'TKO']);
                $fastest = $kos->sortBy([['round', 'asc'], ['id', 'asc']])->first();
                // Kejutan: pemenang dengan OVR paling jauh di bawah lawannya.
                $upset = null;
                $gap = 0;
                foreach ($fights as $f) {
                    if ($f->winner === 'draw') {
                        continue;
                    }
                    $w = $f->winner;
                    $l = $w === 'a' ? 'b' : 'a';
                    $d = $factory->ovr($f->input($l)['stats']) - $factory->ovr($f->input($w)['stats']);
                    if ($d > $gap) {
                        [$gap, $upset] = [$d, $f];
                    }
                }
                $ids = collect([$fastest, $upset])->filter()->flatMap(fn ($f) => [$f->a_id, $f->b_id])->unique();
                $people = Fighter::whereIn('id', $ids)->get()->keyBy('id');
                $hl = function ($f, string $label) use ($people) {
                    if (! $f) {
                        return null;
                    }
                    $w = $f->winner === 'b' ? 'b' : 'a';

                    return [
                        'label' => $label, 'id' => $f->id, 'how' => $f->how, 'round' => (int) $f->round,
                        'winner' => $people->get($f->{"{$w}_id"})?->summary(),
                        'loser' => $people->get($f->{($w === 'a' ? 'b' : 'a').'_id'})?->summary(),
                    ];
                };
                $night = [
                    'slot' => $run->slot_at->toIso8601String(),
                    'fights' => $fights->count(),
                    'ko' => $kos->count(),
                    'draws' => $fights->where('winner', 'draw')->count(),
                    'highlights' => array_values(array_filter([
                        $hl($fastest, 'KO tercepat'),
                        $upset && $upset->id !== $fastest?->id ? $hl($upset, "Kejutan (selisih NILAI {$gap})") : null,
                    ])),
                ];
            }

            $top = DB::table('weekly_records as r')->join('fighters as f', 'f.id', '=', 'r.fighter_id')
                ->where('r.week_start', $calendar->weekStart())->where('f.is_npc', false)->where('f.status', 'active')
                ->orderByDesc('r.w')->orderByDesc('r.ko')->orderBy('r.l')->limit(5)->get(['f.id', 'r.w', 'r.l', 'r.ko']);
            $tf = Fighter::whereIn('id', $top->pluck('id'))->get()->keyBy('id');

            $season = Season::current();
            $champions = DB::table('season_results as r')->join('seasons as s', 's.id', '=', 'r.season_id')
                ->where('r.rank', 1)->orderByDesc('s.number')->limit(5)->get(['s.number', 's.name', 'r.fighter_id', 'r.w', 'r.l', 'r.stars']);
            $cf = Fighter::whereIn('id', $champions->pluck('fighter_id'))->get()->keyBy('id');

            return [
                'news' => $news,
                'night' => $night,
                'week' => $top->map(fn ($r) => ($tf->get($r->id)?->summary() ?? []) + ['w' => (int) $r->w, 'l' => (int) $r->l, 'ko' => (int) $r->ko])->values(),
                'season' => $season ? ['number' => $season->number, 'name' => $season->name, 'since' => $season->starts_at->toIso8601String()] : null,
                'champions' => $champions->map(fn ($c) => ['season' => (int) $c->number, 'name' => $c->name, 'stars' => (int) $c->stars, 'w' => (int) $c->w, 'l' => (int) $c->l, 'fighter' => $cf->get($c->fighter_id)?->summary()])->values(),
            ];
        });
        $data['next'] = $calendar->nextSlot($calendar->now()->addSecond())->toIso8601String();

        return $this->ok($data)->header('Cache-Control', 'public, max-age=30');
    }
}
