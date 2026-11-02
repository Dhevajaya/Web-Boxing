<?php

namespace App\Http\Controllers\Api;

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rules;
use App\Http\Controllers\Controller;
use App\Models\Fighter;
use Illuminate\Http\Request;

/**
 * Pratinjau mesin: hitungan murni tanpa menyentuh rekor.
 * Dipakai halaman demo untuk membuktikan hasil server = hasil browser.
 */
class EngineController extends Controller
{
    public function preview(Request $request, string $a, string $b, FighterFactory $factory, FightSimulator $sim, Rules $rules)
    {
        $ua = Fighter::normalizeUsername($a);
        $ub = Fighter::normalizeUsername($b);
        if (! $ua || ! $ub) {
            return $this->fail('invalid_username', 'Username tidak valid.');
        }

        $version = (int) $request->integer('v', $rules->current);
        $seed = (int) $request->integer('seed', 1) & 0xFFFFFFFF;

        if (! in_array($version, [1, $rules->current], true)) {
            return $this->fail('invalid', 'Versi mesin tidak dikenal.');
        }
        // Petarung terdaftar memakai input aslinya (atribut + poin, gaya, jurus); lainnya stat dasar.
        $people = $version === $rules->current
            ? Fighter::whereIn('instagram_username', [$ua, $ub])->active()->get()->keyBy('instagram_username')
            : collect();
        $input = fn (string $u) => $people->has($u) ? $people[$u]->fightInput([], $version) : $factory->defaultInput($u, $version);
        $inA = $input($ua);
        $inB = $input($ub);
        $r = $sim->simulate($inA, $inB, $seed, $version);

        return $this->ok([
            'v' => $version,
            'seed' => $seed,
            'a' => $inA,
            'b' => $inB,
            'win' => $r['win'],
            'how' => $r['how'],
            'rd' => $r['rd'],
            'scoreA' => $r['scoreA'],
            'scoreB' => $r['scoreB'],
            'events' => count($r['events']),
            'sig' => FightSimulator::signature($r),
            'registered' => ['a' => $people->has($ua), 'b' => $people->has($ub)],
        ], headers: ['Cache-Control' => $people->isEmpty() ? 'public, max-age=3600' : 'no-store']);
    }
}
