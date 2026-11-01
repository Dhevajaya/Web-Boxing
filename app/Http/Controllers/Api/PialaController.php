<?php

namespace App\Http\Controllers\Api;

use App\Game\Bracket;
use App\Http\Controllers\Controller;
use App\Models\Cup;
use App\Models\CupEntry;
use App\Models\CupMatch;
use App\Models\Fighter;
use App\Services\Game\Piala;
use Illuminate\Support\Facades\Auth;

class PialaController extends Controller
{
    public function show(Piala $piala)
    {
        $cup = $piala->current();
        $times = $piala->times($cup->week_start);
        /** @var Fighter|null $me */
        $me = Auth::guard('fighter')->user();
        $entry = $me ? CupEntry::where('cup_id', $cup->id)->where('fighter_id', $me->id)->first() : null;

        return $this->ok([
            'cup' => $this->cupPayload($cup, $times),
            'me' => $me ? [
                'entered' => $entry?->status === 'in',
                'auto' => (bool) $entry?->auto,
                'seed' => $entry?->seed,
                'out_round' => $entry?->out_round,
            ] : null,
            'bracket' => $cup->status === 'drawn' || $cup->status === 'done' ? $this->bracket($cup, $me) : null,
            'history' => Cup::where('status', 'done')->where('id', '!=', $cup->id)->orderByDesc('week_start')->limit(8)->get()
                ->map(fn ($c) => ['week' => $c->week_start, 'entrants' => $c->entrants, 'champion' => Fighter::find($c->champion_id)?->summary()]),
        ])->header('Cache-Control', 'private, max-age=15');
    }

    public function join(Piala $piala)
    {
        $err = $piala->join(Auth::guard('fighter')->user(), $piala->current());

        return $err ? $this->fail($err, $err === 'closed' ? 'Pendaftaran sudah ditutup.' : 'Akunmu tidak bisa ikut piala.') : $this->ok(['entered' => true]);
    }

    public function leave(Piala $piala)
    {
        $err = $piala->leave(Auth::guard('fighter')->user(), $piala->current());

        return $err ? $this->fail($err, 'Undian sudah dilakukan, tidak bisa mundur.') : $this->ok(['entered' => false]);
    }

    private function cupPayload(Cup $cup, array $t): array
    {
        $champ = $cup->champion_id ? Fighter::find($cup->champion_id) : null;

        return [
            'week' => $cup->week_start,
            'status' => $cup->status,
            'entrants' => $cup->status === 'open' ? CupEntry::where('cup_id', $cup->id)->where('status', 'in')->count() : $cup->entrants,
            'min_entrants' => (int) app(\App\Support\GameConfig::class)->get('piala.min_entrants'),
            'auto_active_days' => (int) app(\App\Support\GameConfig::class)->get('piala.auto_active_days'),
            'size' => $cup->size,
            'rounds' => $cup->rounds,
            'draw_at' => $t['draw']->toIso8601String(),
            'early_at' => $t['early']->toIso8601String(),
            'final_at' => end($t['finals'])->toIso8601String(),
            'champion' => $champ?->summary(),
        ];
    }

    /**
     * Bagan: lengkap untuk babak yang berisi <= 8 laga (16 besar ke atas), plus semua laga
     * milik pemain yang sedang masuk. Hasil hanya tampil setelah jam laganya tiba.
     */
    private function bracket(Cup $cup, ?Fighter $me): array
    {
        $fullFrom = max(1, $cup->rounds - 3);
        $q = CupMatch::where('cup_id', $cup->id)->where(function ($q) use ($fullFrom, $me) {
            $q->where('round', '>=', $fullFrom);
            if ($me) {
                $q->orWhere('a_id', $me->id)->orWhere('b_id', $me->id);
            }
        })->orderBy('round')->orderBy('slot');
        $matches = $q->get();
        $people = Fighter::whereIn('id', $matches->pluck('a_id')->merge($matches->pluck('b_id'))->filter()->unique())->get()->keyBy('id');

        $rounds = [];
        $times = CupMatch::where('cup_id', $cup->id)->groupBy('round')->selectRaw('round, MIN(scheduled_at) as at, COUNT(*) as n')->get()->keyBy('round');
        for ($r = 1; $r <= $cup->rounds; $r++) {
            $rounds[] = [
                'round' => $r,
                'name' => Bracket::roundName($r, $cup->rounds),
                'at' => \Carbon\CarbonImmutable::parse($times[$r]->at)->toIso8601String(),
                'matches_total' => (int) $times[$r]->n,
                'full' => $r >= $fullFrom,
                'matches' => $matches->where('round', $r)->values()->map(function (CupMatch $m) use ($people) {
                    $shown = $m->walkover || $m->scheduled_at->lte(now());

                    return [
                        'slot' => $m->slot,
                        'a' => $m->a_id ? $people->get($m->a_id)?->summary() : null,
                        'b' => $m->b_id ? $people->get($m->b_id)?->summary() : null,
                        'winner' => $shown && $m->winner_id ? ($m->winner_id === $m->a_id ? 'a' : 'b') : null,
                        'walkover' => $m->walkover,
                        'fight_id' => $shown ? $m->fight_id : null,
                        'at' => $m->scheduled_at->toIso8601String(),
                    ];
                })->all(),
            ];
        }

        return $rounds;
    }
}
