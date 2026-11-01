<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fight;
use App\Models\FightNightRun;
use App\Services\Game\Calendar;
use Illuminate\Support\Facades\Auth;

class FightNightController extends Controller
{
    /** Jadwal Fight Night berikutnya + ringkasan yang terakhir (dan laga pemain, bila masuk). */
    public function status(Calendar $calendar)
    {
        $next = $calendar->nextSlot($calendar->now()->addSecond());
        $last = FightNightRun::where('slot_at', '<=', now())->orderByDesc('slot_at')->first();
        $me = Auth::guard('fighter')->user();

        $mine = null;
        if ($me && $last) {
            $f = Fight::visible()->where('kind', 'night')->where('slot_at', $last->slot_at)
                ->where(fn ($q) => $q->where('a_id', $me->id)->orWhere('b_id', $me->id))->first();
            if ($f) {
                $f->load(['fighterA', 'fighterB']);
                $side = $f->a_id === $me->id ? 'a' : 'b';
                $mine = [
                    'id' => $f->id,
                    'result' => $f->winner === 'draw' ? 'draw' : ($f->winner === $side ? 'win' : 'lose'),
                    'how' => $f->how,
                    'opponent' => ($side === 'a' ? $f->fighterB : $f->fighterA)?->summary(),
                ];
            }
        }

        return $this->ok([
            'next' => $next->toIso8601String(),
            'hours' => array_map('intval', (array) app(\App\Support\GameConfig::class)->get('fight_night.hours')),
            'last' => $last ? [
                'slot' => $last->slot_at->toIso8601String(),
                'status' => $last->status,
                'fights' => (int) $last->pairs_total,
            ] : null,
            'mine' => $mine,
        ])->header('Cache-Control', 'private, max-age=20');
    }
}
