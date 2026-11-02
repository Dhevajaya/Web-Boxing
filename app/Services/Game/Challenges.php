<?php

namespace App\Services\Game;

use App\Game\FightSimulator;
use App\Game\Rules;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\Season;
use App\Support\GameConfig;
use Illuminate\Support\Str;

/** Tantangan langsung: laga instan antara dua petarung terdaftar. */
class Challenges
{
    public function __construct(
        private readonly GameConfig $game,
        private readonly Calendar $calendar,
        private readonly FightSimulator $sim,
        private readonly Rules $rules,
        private readonly ResultApplier $applier,
        private readonly StatBonus $bonus,
    ) {}

    /**
     * @return Fight|string laga, atau kode galat: self, unavailable, daily_limit, same_opponent
     */
    public function create(Fighter $me, Fighter $opp): Fight|string
    {
        if ($me->is($opp)) {
            return 'self';
        }
        if ($opp->status !== 'active' || ! $opp->activated_at) {
            return 'unavailable';
        }

        $today = Fight::where('kind', 'challenge')->where('a_id', $me->id)->where('created_at', '>=', $this->calendar->dayStart())->count();
        if ($today >= (int) $this->game->get('challenge.daily_limit')) {
            return 'daily_limit';
        }
        $recent = Fight::where('kind', 'challenge')->where('a_id', $me->id)->where('b_id', $opp->id)
            ->where('created_at', '>=', now()->subMinutes((int) $this->game->get('challenge.same_opponent_minutes')))->exists();
        if ($recent) {
            return 'same_opponent';
        }

        $version = $this->rules->current;
        $bonus = $this->bonus->for([$me->id, $opp->id], $this->calendar->gameDate());
        $ia = $me->fightInput($bonus[$me->id] ?? [], $version);
        $ib = $opp->fightInput($bonus[$opp->id] ?? [], $version);
        $seed = random_int(0, 0xFFFFFFFF);
        $r = $this->sim->simulate($ia, $ib, $seed, $version);

        $fight = Fight::create([
            'kind' => 'challenge', 'code' => $this->code(), 'season_id' => Season::current()?->id,
            'a_id' => $me->id, 'b_id' => $opp->id, 'seed' => $seed, 'engine_version' => $version,
        ] + Fight::statColumns($ia, $ib, $r) + [
            'winner' => $r['win'], 'how' => $r['how'], 'round' => $r['rd'],
            'score_a' => $r['scoreA'], 'score_b' => $r['scoreB'], 'visible_at' => now(),
        ]);
        $this->applier->apply($fight);

        return $fight;
    }

    private function code(): string
    {
        do {
            $code = Str::random(8);
        } while (Fight::where('code', $code)->exists());

        return $code;
    }
}
