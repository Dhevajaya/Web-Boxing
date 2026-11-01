<?php

namespace App\Services\Game;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bonus stat sementara dari Latihan (Fase 9): berlaku untuk laga pada tanggal (zona game)
 * yang sama dengan latihannya. Nilai terbaik per stat per hari, maksimal training.bonus_max.
 */
class StatBonus
{
    /**
     * @param  list<int>  $fighterIds
     * @return array<int, array<string,int>> fighter_id => [stat => bonus]
     */
    public function for(array $fighterIds, string $date): array
    {
        if (! $fighterIds || ! Schema::hasTable('training_sessions')) {
            return [];
        }

        $out = [];
        foreach (array_chunk($fighterIds, 1000) as $ids) {
            $rows = DB::table('training_sessions')
                ->whereIn('fighter_id', $ids)->where('game_date', $date)->where('bonus', '>', 0)
                ->groupBy('fighter_id', 'stat')
                ->selectRaw('fighter_id, stat, MAX(bonus) as bonus')
                ->get();
            foreach ($rows as $r) {
                $out[(int) $r->fighter_id][$r->stat] = (int) $r->bonus;
            }
        }

        return $out;
    }
}
