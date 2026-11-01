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

        $fa = $factory->base($ua, $version);
        $fb = $factory->base($ub, $version);
        $inA = ['style' => $fa['style'], 'stats' => $fa['base']];
        $inB = ['style' => $fb['style'], 'stats' => $fb['base']];
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
        ], headers: ['Cache-Control' => 'public, max-age=86400, s-maxage=86400']);
    }
}
