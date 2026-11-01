<?php

namespace App\Services\Game;

use App\Models\Fighter;
use App\Support\GameConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Latihan: mini-game 30 detik. Skor dihitung di browser, jadi server membatasi:
 *  - token sekali pakai dari /start, durasi minimal sesuai game,
 *  - jumlah ketukan <= max_taps_per_sec x detik, skor <= batas game,
 *  - XP per game per hari dibatasi xp_cap_per_game,
 *  - bonus stat (+1..bonus_max) dari ambang skor, berlaku untuk laga di tanggal yang sama.
 */
class Training
{
    public function __construct(
        private readonly GameConfig $game,
        private readonly Calendar $calendar,
        private readonly ResultApplier $applier,
    ) {}

    public function games(): array
    {
        return (array) $this->game->get('training.games');
    }

    /** @return array{token:string, seconds:int}|string */
    public function start(Fighter $f, string $game): array|string
    {
        $g = $this->games()[$game] ?? null;
        if (! $g) {
            return 'unknown_game';
        }
        // Batasi sesi terbuka agar token tidak ditimbun.
        $open = DB::table('training_sessions')->where('fighter_id', $f->id)->whereNull('finished_at')
            ->where('started_at', '>=', now()->subMinutes(10))->count();
        if ($open >= 5) {
            return 'too_many';
        }

        $token = Str::random(40);
        DB::table('training_sessions')->insert([
            'fighter_id' => $f->id, 'game' => $game, 'stat' => $g['stat'],
            'game_date' => $this->calendar->gameDate(), 'token_hash' => hash('sha256', $token),
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['token' => $token, 'seconds' => (int) $this->game->get('training.seconds')];
    }

    /** @return array|string hasil, atau kode galat */
    public function finish(Fighter $f, string $token, mixed $score, mixed $taps): array|string
    {
        if (! is_int($score) || ! is_int($taps) || $score < 0 || $taps < 0) {
            return 'invalid';
        }

        return DB::transaction(function () use ($f, $token, $score, $taps) {
            $s = DB::table('training_sessions')->where('token_hash', hash('sha256', $token))
                ->where('fighter_id', $f->id)->lockForUpdate()->first();
            if (! $s || $s->finished_at) {
                return 'invalid_token';
            }

            $g = $this->games()[$s->game];
            $seconds = (int) $this->game->get('training.seconds');
            $elapsed = now()->getTimestamp() - \Carbon\Carbon::parse($s->started_at)->getTimestamp();
            if ($elapsed < $seconds - 2 || $elapsed > $seconds + 300) {
                DB::table('training_sessions')->where('id', $s->id)->update(['finished_at' => now(), 'updated_at' => now()]);

                return 'bad_timing';
            }
            $maxTaps = (int) $this->game->get('training.max_taps_per_sec') * $seconds;
            if ($taps > $maxTaps || $score > (int) $g['max'] || ($s->game !== 'tahan' && $score > $taps)) {
                DB::table('training_sessions')->where('id', $s->id)->update(['finished_at' => now(), 'updated_at' => now()]);

                return 'rejected';
            }

            $bonus = 0;
            foreach ($g['bonus'] as $need) {
                if ($score >= $need) {
                    $bonus++;
                }
            }
            $bonus = min($bonus, (int) $this->game->get('training.bonus_max'));

            $cap = (int) $this->game->get('training.xp_cap_per_game');
            $already = (int) DB::table('training_sessions')->where('fighter_id', $f->id)
                ->where('game', $s->game)->where('game_date', $s->game_date)->sum('xp');
            $xp = max(0, min(intdiv($score, max(1, (int) $g['xp_div'])), $cap - $already));
            $xp = $this->applier->grant($f->id, 'training', $s->id, $xp);

            DB::table('training_sessions')->where('id', $s->id)->update([
                'finished_at' => now(), 'score' => $score, 'taps' => $taps, 'xp' => $xp, 'bonus' => $bonus, 'updated_at' => now(),
            ]);

            return ['game' => $s->game, 'score' => $score, 'xp' => $xp, 'bonus' => $bonus] + $this->today($f)[$s->game];
        });
    }

    /** Ringkasan hari ini per game. */
    public function today(Fighter $f): array
    {
        $date = $this->calendar->gameDate();
        $rows = DB::table('training_sessions')->where('fighter_id', $f->id)->where('game_date', $date)->whereNotNull('finished_at')
            ->groupBy('game')->selectRaw('game, MAX(score) as best, SUM(xp) as xp, MAX(bonus) as bonus, COUNT(*) as plays')->get()->keyBy('game');
        $out = [];
        foreach ($this->games() as $key => $g) {
            $r = $rows->get($key);
            $out[$key] = [
                'best' => (int) ($r->best ?? 0), 'xpToday' => (int) ($r->xp ?? 0),
                'bonusToday' => (int) ($r->bonus ?? 0), 'plays' => (int) ($r->plays ?? 0),
            ];
        }

        return $out;
    }
}
