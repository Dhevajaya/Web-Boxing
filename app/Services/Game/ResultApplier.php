<?php

namespace App\Services\Game;

use App\Game\Progression;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\WeeklyRecord;
use App\Support\GameConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menerapkan hasil satu laga ke rekor, bintang, dan XP. Idempoten: laga yang sudah
 * punya applied_at dilewati, dan XP lewat xp_ledger berkunci unik.
 *
 *  night     : rekor season + mingguan, bintang, XP penuh (kedua petarung)
 *  challenge : XP saja untuk penantang (dikali challenge_factor, dibatasi per hari)
 *  cup       : XP untuk pemenang
 */
class ResultApplier
{
    public function __construct(
        private readonly GameConfig $game,
        private readonly Calendar $calendar,
    ) {}

    public function apply(Fight $fight): void
    {
        DB::transaction(function () use ($fight) {
            $f = Fight::whereKey($fight->id)->lockForUpdate()->first();
            if (! $f || $f->applied_at) {
                return;
            }

            $prog = Progression::fromConfig();
            $byKo = in_array($f->how, ['KO', 'TKO'], true);
            $xp = ['a' => 0, 'b' => 0];

            foreach (['a', 'b'] as $side) {
                $outcome = $f->winner === 'draw' ? 'draw' : ($f->winner === $side ? 'win' : 'lose');
                $fighterId = (int) $f->{"{$side}_id"};

                if ($f->kind === 'night') {
                    $this->record($f, $fighterId, $outcome, $byKo);
                    $xp[$side] = $this->grant($fighterId, 'night', $f->id, $prog->fightXp($outcome, $byKo));
                } elseif ($f->kind === 'challenge' && $side === 'a') {
                    $xp[$side] = $this->challengeXp($f, $fighterId, $prog->fightXp($outcome, $byKo));
                } elseif ($f->kind === 'cup' && $outcome === 'win') {
                    $amount = (int) $this->game->get('xp.cup_win') + ($byKo ? (int) $this->game->get('xp.cup_ko_bonus') : 0);
                    $xp[$side] = $this->grant($fighterId, 'cup', $f->id, $amount);
                }
            }

            $f->forceFill(['xp_a' => $xp['a'], 'xp_b' => $xp['b'], 'applied_at' => now()])->save();
            $fight->setRawAttributes($f->getAttributes(), true);
        });
    }

    private function record(Fight $f, int $fighterId, string $outcome, bool $byKo): void
    {
        $col = ['win' => 'w', 'lose' => 'l', 'draw' => 'd'][$outcome];
        $starDelta = (int) $this->game->get('stars.'.$outcome, 0);

        if ($f->season_id) {
            $rec = FighterRecord::firstOrCreate(['fighter_id' => $fighterId, 'season_id' => $f->season_id]);
            $rec->{$col}++;
            if ($outcome === 'win' && $byKo) {
                $rec->ko++;
            }
            $rec->stars = max(0, $rec->stars + $starDelta);
            $rec->last_fight_id = $f->id;
            $rec->save();
        }

        $week = $this->calendar->weekStart(CarbonImmutable::parse($f->visible_at));
        $wr = WeeklyRecord::firstOrCreate(['fighter_id' => $fighterId, 'week_start' => $week]);
        $wr->{$col}++;
        if ($outcome === 'win' && $byKo) {
            $wr->ko++;
        }
        $wr->save();
    }

    /** XP tantangan: hanya N tantangan pertama per hari (zona game) yang memberi XP. */
    private function challengeXp(Fight $f, int $fighterId, int $base): int
    {
        $cap = (int) $this->game->get('xp.challenge_daily_cap');
        $today = DB::table('xp_ledger')->where('fighter_id', $fighterId)->where('source', 'challenge')
            ->where('created_at', '>=', $this->calendar->dayStart(CarbonImmutable::parse($f->visible_at)))->count();
        if ($today >= $cap) {
            return 0;
        }

        return $this->grant($fighterId, 'challenge', $f->id, (int) floor($base * (float) $this->game->get('xp.challenge_factor')));
    }

    /**
     * Tambah XP lewat buku besar. Mengembalikan jumlah yang benar-benar ditambahkan
     * (0 bila sudah pernah diberikan atau petarung NPC).
     */
    public function grant(int $fighterId, string $source, string|int $sourceId, int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }
        $fighter = Fighter::whereKey($fighterId)->first(['id', 'is_npc', 'xp']);
        if (! $fighter || $fighter->is_npc) {
            return 0;
        }

        $inserted = DB::table('xp_ledger')->insertOrIgnore([
            'fighter_id' => $fighterId, 'source' => $source, 'source_id' => (string) $sourceId,
            'amount' => $amount, 'created_at' => now(),
        ]);
        if (! $inserted) {
            return 0;
        }

        // Increment atomik: dua hadiah bersamaan (latihan + Fight Night) tidak saling menimpa.
        Fighter::whereKey($fighterId)->increment('xp', $amount);
        $xp = (int) Fighter::whereKey($fighterId)->value('xp');
        Fighter::whereKey($fighterId)->update(['level' => Progression::fromConfig()->level($xp)['lv']]);

        return $amount;
    }
}
