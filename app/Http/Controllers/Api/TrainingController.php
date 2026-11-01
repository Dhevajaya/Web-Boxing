<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\Calendar;
use App\Services\Game\Training;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrainingController extends Controller
{
    public function index(Training $training, GameConfig $game, Calendar $calendar)
    {
        $me = Auth::guard('fighter')->user();

        return $this->ok([
            'games' => $training->games(),
            'seconds' => (int) $game->get('training.seconds'),
            'xp_cap' => (int) $game->get('training.xp_cap_per_game'),
            'bonus_max' => (int) $game->get('training.bonus_max'),
            'today' => $me ? $training->today($me) : null,
            'next_night' => $calendar->nextSlot($calendar->now()->addSecond())->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function start(Request $request, Training $training)
    {
        $r = $training->start(Auth::guard('fighter')->user(), (string) $request->input('game'));
        if (is_string($r)) {
            return $this->fail($r, $r === 'too_many' ? 'Terlalu banyak sesi terbuka. Selesaikan dulu yang ada.' : 'Mini-game tidak dikenal.', $r === 'too_many' ? 429 : 422);
        }

        return $this->ok($r, 201);
    }

    public function finish(Request $request, Training $training)
    {
        $r = $training->finish(Auth::guard('fighter')->user(), (string) $request->input('token'), $request->input('score'), $request->input('taps'));
        if (is_string($r)) {
            return $this->fail($r, match ($r) {
                'invalid_token' => 'Sesi latihan tidak ditemukan atau sudah selesai.',
                'bad_timing' => 'Waktu latihan tidak wajar. Coba lagi.',
                'rejected' => 'Skor ditolak karena tidak wajar.',
                default => 'Data latihan tidak valid.',
            });
        }

        return $this->ok($r);
    }
}
