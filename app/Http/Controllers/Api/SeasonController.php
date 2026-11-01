<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

class SeasonController extends Controller
{
    public function index()
    {
        return $this->ok(Season::orderByDesc('number')->get(['number', 'name', 'status', 'starts_at', 'ends_at'])
            ->map(fn ($s) => [
                'number' => $s->number, 'name' => $s->name, 'status' => $s->status,
                'starts_at' => $s->starts_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(),
            ]), headers: ['Cache-Control' => 'public, max-age=300']);
    }

    /** Peringkat akhir season yang sudah ditutup (100 besar). */
    public function show(int $number)
    {
        $s = Season::where('number', $number)->first();
        if (! $s) {
            return $this->fail('not_found', 'Season tidak ditemukan.', 404);
        }
        $rows = DB::table('season_results')->where('season_id', $s->id)->orderBy('rank')->limit(100)->get();
        $people = Fighter::whereIn('id', $rows->pluck('fighter_id'))->get()->keyBy('id');

        return $this->ok([
            'season' => ['number' => $s->number, 'name' => $s->name, 'status' => $s->status],
            'rows' => $rows->map(fn ($r) => ['rank' => (int) $r->rank, 'w' => (int) $r->w, 'l' => (int) $r->l, 'd' => (int) $r->d, 'stars' => (int) $r->stars]
                + ($people->get($r->fighter_id)?->summary() ?? []))->values(),
        ], headers: ['Cache-Control' => 'public, max-age=300']);
    }
}
