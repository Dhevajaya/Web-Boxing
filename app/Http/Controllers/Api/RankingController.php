<?php

namespace App\Http\Controllers\Api;

use App\Game\Progression;
use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Models\Season;
use App\Services\Game\Calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Papan peringkat. Hanya pemain (bukan NPC) yang aktif.
 *   ?board=season  bintang, lalu menang, lalu kalah paling sedikit
 *   ?board=week    menang minggu ini (Senin WIB), lalu KO, lalu kalah paling sedikit
 */
class RankingController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, Calendar $calendar)
    {
        $board = $request->query('board') === 'week' ? 'week' : 'season';
        $page = max(1, min(200, $request->integer('page', 1)));
        $season = Season::current();

        if ($board === 'season') {
            if (! $season) {
                return $this->ok(['board' => $board, 'rows' => [], 'me' => null, 'page' => $page, 'more' => false]);
            }
            $base = DB::table('fighter_records as r')->join('fighters as f', 'f.id', '=', 'r.fighter_id')
                ->where('r.season_id', $season->id)->where('f.is_npc', false)->where('f.status', 'active')
                ->whereRaw('(r.w + r.l + r.d) > 0');
            $order = [['r.stars', 'desc'], ['r.w', 'desc'], ['r.l', 'asc'], ['f.id', 'asc']];
            $cols = ['r.w', 'r.l', 'r.d', 'r.ko', 'r.stars'];
        } else {
            $base = DB::table('weekly_records as r')->join('fighters as f', 'f.id', '=', 'r.fighter_id')
                ->where('r.week_start', $calendar->weekStart())->where('f.is_npc', false)->where('f.status', 'active');
            $order = [['r.w', 'desc'], ['r.ko', 'desc'], ['r.l', 'asc'], ['f.id', 'asc']];
            $cols = ['r.w', 'r.l', 'r.d', 'r.ko'];
        }

        $q = (clone $base)->select(array_merge(['f.id'], $cols));
        foreach ($order as [$c, $dir]) {
            $q->orderBy($c, $dir);
        }
        $rows = $q->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE + 1)->get();
        $more = $rows->count() > self::PER_PAGE;
        $rows = $rows->take(self::PER_PAGE);

        $fighters = Fighter::with('sasana:id,slug,name,icon,color')->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
        $prog = Progression::fromConfig();
        $out = [];
        foreach ($rows->values() as $i => $r) {
            $f = $fighters->get($r->id);
            if (! $f) {
                continue;
            }
            $out[] = ['rank' => ($page - 1) * self::PER_PAGE + $i + 1] + $f->summary() + [
                'w' => (int) $r->w, 'l' => (int) $r->l, 'd' => (int) $r->d, 'ko' => (int) $r->ko,
                'stars' => isset($r->stars) ? (int) $r->stars : null,
                'tier' => isset($r->stars) ? $prog->tier((int) $r->stars) : null,
                'sasana' => $f->sasana?->only(['slug', 'name', 'icon', 'color']),
            ];
        }

        return $this->ok([
            'board' => $board,
            'season' => $season?->only(['number', 'name']),
            'week' => $calendar->weekStart(),
            'rows' => $out,
            'me' => $this->myRank($base, $order),
            'page' => $page,
            'more' => $more,
        ])->header('Cache-Control', 'private, max-age=20');
    }

    /** Peringkat pemain yang sedang masuk: banyaknya baris yang urutannya di atasnya + 1. */
    private function myRank($base, array $order): ?array
    {
        $me = Auth::guard('fighter')->user();
        if (! $me) {
            return null;
        }
        $mine = (clone $base)->where('f.id', $me->id)->select(array_map(fn ($o) => $o[0], $order))->first();
        if (! $mine) {
            return null;
        }

        $ahead = (clone $base)->where(function ($q) use ($order, $mine) {
            // Perbandingan leksikografis: (k1 lebih baik) atau (k1 sama dan k2 lebih baik) ...
            foreach ($order as $i => [$col, $dir]) {
                $q->orWhere(function ($q) use ($order, $i, $mine) {
                    for ($j = 0; $j < $i; $j++) {
                        $q->where($order[$j][0], '=', $this->val($mine, $order[$j][0]));
                    }
                    [$c, $d] = $order[$i];
                    $q->where($c, $d === 'desc' ? '>' : '<', $this->val($mine, $c));
                });
            }
        })->count();

        return ['rank' => $ahead + 1, 'username' => $me->instagram_username];
    }

    private function val(object $row, string $col): mixed
    {
        return $row->{substr($col, strpos($col, '.') + 1)};
    }
}
