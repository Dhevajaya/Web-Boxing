<?php

namespace App\Http\Controllers\Api;

use App\Game\FighterFactory;
use App\Game\Looks;
use App\Game\Progression;
use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\Season;

/**
 * Kartu petarung publik. Username yang belum daftar tetap punya kartu
 * (stat dasar dari hash), hanya saja `registered = false`.
 * `look` selalu terisi (bawaan dari hash bila belum diatur), siap digambar avatar.js.
 * Fase berikutnya menambah rekor tantangan, latihan, lencana, dan laga terakhir.
 */
class FighterController extends Controller
{
    public function show(string $username, FighterFactory $factory, Looks $looks)
    {
        $u = Fighter::normalizeUsername($username);
        if (! $u) {
            return $this->fail('invalid_username', 'Username tidak valid.', 400);
        }

        $f = Fighter::where('instagram_username', $u)->first();
        $base = $factory->base($u);
        $stats = $f ? $f->effectiveStats() : $base['base'];
        $prog = Progression::fromConfig();
        $level = $prog->level((int) ($f?->xp ?? 0));

        $record = null;
        if ($f && ($season = Season::current())) {
            $record = FighterRecord::where('fighter_id', $f->id)->where('season_id', $season->id)
                ->first(['w', 'l', 'd', 'ko', 'stars']);
        }

        return $this->ok([
            'username' => $u,
            'registered' => (bool) $f,
            'active' => $f?->status === 'active',
            'npc' => (bool) $f?->is_npc,
            'verified' => (bool) $f?->verified_at,
            'style' => $base['style'],
            'styleName' => $base['styleName'],
            'kelas' => $base['kelas'],
            'nick' => ($f?->nick ?: $base['nick']).' dari '.($f?->kota ?: $base['kota']),
            'look' => $looks->resolve($f?->look, $u),
            'base' => $base['base'],
            'points' => $f?->points ?? (object) [],
            'stats' => $stats,
            'ovr' => $factory->ovr($stats),
            'level' => $level,
            'pointsFree' => $f ? max(0, $prog->pointsFor($level['lv']) - array_sum($f->points ?? [])) : 0,
            'record' => $record ?? ['w' => 0, 'l' => 0, 'd' => 0, 'ko' => 0, 'stars' => 0],
            'tier' => $prog->tier((int) ($record->stars ?? 0)),
            'sasana' => $f?->sasana?->only(['slug', 'name', 'icon', 'color']),
        ], headers: ['Cache-Control' => 'public, max-age=15, s-maxage=30']);
    }
}
