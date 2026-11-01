<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fight;
use App\Models\Fighter;
use App\Services\Game\Challenges;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChallengeController extends Controller
{
    /** POST /api/challenges { username } -> laga instan, kembalikan kode link /t/{code}. */
    public function store(Request $request, Challenges $challenges, GameConfig $game)
    {
        $opp = Fighter::findByUsername((string) $request->input('username'));
        if (! $opp) {
            return $this->fail('not_registered', 'Petarung itu belum terdaftar. Ajak dia main dulu!', 404);
        }

        /** @var Fighter $me */
        $me = Auth::guard('fighter')->user();
        $r = $challenges->create($me, $opp);
        if (is_string($r)) {
            return match ($r) {
                'self' => $this->fail('self', 'Tidak bisa menantang diri sendiri.'),
                'unavailable' => $this->fail('unavailable', 'Petarung itu sedang tidak aktif.'),
                'daily_limit' => $this->fail('daily_limit', 'Jatah tantangan hari ini habis ('.$game->get('challenge.daily_limit').'). Coba lagi besok.', 429),
                'same_opponent' => $this->fail('same_opponent', 'Baru saja menantang dia. Tunggu '.$game->get('challenge.same_opponent_minutes').' menit.', 429),
            };
        }

        return $this->ok(['code' => $r->code, 'id' => $r->id], 201);
    }

    /** GET /api/challenges/{code} */
    public function show(string $code)
    {
        $f = Fight::where('kind', 'challenge')->where('code', $code)->visible()->first();
        if (! $f) {
            return $this->fail('not_found', 'Tantangan tidak ditemukan.', 404);
        }

        return $this->ok(FightController::payload($f), headers: ['Cache-Control' => 'public, max-age=300']);
    }
}
