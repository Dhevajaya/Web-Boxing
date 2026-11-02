<?php

namespace App\Http\Controllers\Api;

use App\Game\Progression;
use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Services\Game\Calendar;
use App\Services\Game\Training;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrainingController extends Controller
{
    public function index(Training $training, GameConfig $game, Calendar $calendar)
    {
        /** @var Fighter|null $me */
        $me = Auth::guard('fighter')->user();

        return $this->ok([
            'season' => $game->currentSeasonNumber(),
            'seconds' => (int) $game->get('training.seconds'),
            'xp_cap' => (int) $game->get('training.xp_daily_cap'),
            'games' => $training->summary($me),
            'xp_today' => $me ? $training->xpToday($me) : null,
            'me' => $me ? ['look' => $me->resolvedLook(), 'level' => Progression::fromConfig()->level((int) $me->xp)] : null,
            'bonus_today' => $me ? DB::table('training_sessions')->where('fighter_id', $me->id)->where('game_date', $calendar->gameDate())
                ->where('bonus', '>', 0)->groupBy('stat')->selectRaw('stat, MAX(bonus) as b')->pluck('b', 'stat')->map(fn ($v) => (int) $v) : null,
            'next_night' => $calendar->nextSlot($calendar->now()->addSecond())->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    /** GET /api/training/{game}/top: 100 besar season ini. */
    public function top(string $game, Training $training, GameConfig $game_)
    {
        $g = $training->games()[$game] ?? null;
        if (! $g || ! empty($g['soon'])) {
            return $this->fail('not_found', 'Latihan tidak dikenal.', 404);
        }
        $me = Auth::guard('fighter')->id();

        return $this->ok($training->board($game, $game_->currentSeasonNumber(), $me, 100))->header('Cache-Control', 'private, max-age=30');
    }

    public function start(Request $request, Training $training)
    {
        $r = $training->start(Auth::guard('fighter')->user(), (string) $request->input('game'));
        if (is_string($r)) {
            return $this->fail($r, $r === 'too_many' ? 'Terlalu banyak sesi terbuka. Selesaikan dulu yang ada.' : 'Latihan ini belum bisa dimainkan.', $r === 'too_many' ? 429 : 422);
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
