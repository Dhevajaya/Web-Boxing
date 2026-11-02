<?php

namespace App\Services\Game;

use App\Game\Progression;
use App\Models\Fighter;
use App\Support\GameConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Latihan: mini-game 30 detik (Samsak, Hindari Pelatih, Tahan Serangan).
 *
 * Skor dihitung di browser, jadi server membatasi: token sekali pakai dari /start,
 * durasi wajar, ketukan <= max_taps_per_sec x detik, skor <= batas game dan <= (ketukan + free) x per_tap.
 *
 * Hadiah:
 *  - XP: skor TERBAIK hari ini per game diubah jadi XP (skor / xp_div). Hanya kenaikan yang dibayar,
 *    total semua game per hari maksimal xp_daily_cap. Sesi pertama tiap hari: + streak_xp.
 *  - Bintang 1-3 dari ambang skor = bonus stat +1..+3 untuk laga pada tanggal (WIB) yang sama.
 *  - Rekor & peringkat per game per season (skor terbaik tiap pemain).
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

    private function playable(string $game): ?array
    {
        $g = $this->games()[$game] ?? null;

        return $g && empty($g['soon']) ? $g : null;
    }

    public static function stars(array $g, int $score): int
    {
        $n = 0;
        foreach ($g['stars'] ?? [] as $need) {
            if ($score >= $need) {
                $n++;
            }
        }

        return $n;
    }

    /** @return array{token:string, seconds:int}|string */
    public function start(Fighter $f, string $game): array|string
    {
        if (! $this->playable($game)) {
            return 'unknown_game';
        }
        $open = DB::table('training_sessions')->where('fighter_id', $f->id)->whereNull('finished_at')
            ->where('started_at', '>=', now()->subMinutes(10))->count();
        if ($open >= 5) {
            return 'too_many';
        }

        $token = Str::random(40);
        DB::table('training_sessions')->insert([
            'fighter_id' => $f->id, 'game' => $game, 'stat' => $this->games()[$game]['stat'],
            'game_date' => $this->calendar->gameDate(), 'season' => $this->game->currentSeasonNumber(),
            'token_hash' => hash('sha256', $token), 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
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
            $close = fn () => DB::table('training_sessions')->where('id', $s->id)->update(['finished_at' => now(), 'updated_at' => now()]);

            $g = $this->playable($s->game);
            $seconds = (int) $this->game->get('training.seconds');
            $elapsed = now()->getTimestamp() - \Carbon\Carbon::parse($s->started_at)->getTimestamp();
            if (! $g || $elapsed < $seconds - 2 || $elapsed > $seconds + 300) {
                $close();

                return 'bad_timing';
            }
            $maxTaps = (int) $this->game->get('training.max_taps_per_sec') * $seconds;
            if ($taps > $maxTaps || $score > (int) $g['max'] || $score > ($taps + (int) $g['free']) * (int) $g['per_tap']) {
                $close();

                return 'rejected';
            }

            $prevSeasonBest = (int) DB::table('training_sessions')->where('fighter_id', $f->id)->where('game', $s->game)
                ->where('season', $s->season)->whereNotNull('finished_at')->max('score');
            $prevTodayBest = (int) DB::table('training_sessions')->where('fighter_id', $f->id)->where('game', $s->game)
                ->where('game_date', $s->game_date)->whereNotNull('finished_at')->max('score');
            $xpToday = (int) DB::table('training_sessions')->where('fighter_id', $f->id)->where('game_date', $s->game_date)->sum('xp');
            $firstToday = ! DB::table('training_sessions')->where('fighter_id', $f->id)->where('game_date', $s->game_date)->whereNotNull('finished_at')->exists();

            $stars = self::stars($g, $score);
            $bonus = min($stars, (int) $this->game->get('training.bonus_max'));
            $div = max(1, (int) $g['xp_div']);
            $cap = (int) $this->game->get('training.xp_daily_cap');
            $gain = max(0, intdiv($score, $div) - intdiv($prevTodayBest, $div));
            $gain = max(0, min($gain, $cap - $xpToday));

            $lvBefore = Progression::fromConfig()->level((int) Fighter::whereKey($f->id)->value('xp'))['lv'];
            $xp = $this->applier->grant($f->id, 'training', $s->id, $gain);

            $streakDays = 0;
            $streakXp = 0;
            if ($firstToday) {
                $streakDays = $this->streak($f->id, $s->game_date);
                $streakXp = $this->applier->grant($f->id, 'streak', $s->game_date, (int) $this->game->get('training.streak_xp'));
            }

            DB::table('training_sessions')->where('id', $s->id)->update([
                'finished_at' => now(), 'score' => $score, 'taps' => $taps, 'xp' => $xp,
                'bonus' => $bonus, 'stars' => $stars, 'updated_at' => now(),
            ]);

            $fresh = Fighter::whereKey($f->id)->first(['id', 'xp']);
            $level = Progression::fromConfig()->level((int) $fresh->xp);

            return [
                'game' => $s->game,
                'stat' => $g['stat'],
                'score' => $score,
                'stars' => $stars,
                'bonus' => $bonus,
                'record' => $score > $prevSeasonBest,
                'best' => max($score, $prevSeasonBest),
                'xp' => $xp,
                'xpToday' => $xpToday + $xp,
                'xpCap' => $cap,
                'streakDays' => $streakDays,
                'streakXp' => $streakXp,
                'level' => $level,
                'levelUp' => $level['lv'] > $lvBefore,
                'bonusToday' => $this->bonusToday($f->id, $s->game_date),
            ] + $this->board($s->game, (int) $s->season, $f->id);
        });
    }

    /** Hari berturut-turut berlatih sampai tanggal ini (termasuk hari ini). */
    private function streak(int $fighterId, string $date): int
    {
        $days = DB::table('training_sessions')->where('fighter_id', $fighterId)->whereNotNull('finished_at')
            ->where('game_date', '<', $date)->where('game_date', '>=', \Carbon\CarbonImmutable::parse($date)->subDays(60)->toDateString())
            ->distinct()->orderByDesc('game_date')->pluck('game_date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $n = 1;
        $cur = \Carbon\CarbonImmutable::parse($date);
        foreach ($days as $d) {
            if ($d !== $cur->subDay()->toDateString()) {
                break;
            }
            $n++;
            $cur = $cur->subDay();
        }

        return $n;
    }

    /** @return array<string,int> stat => bonus terbaik hari itu */
    private function bonusToday(int $fighterId, string $date): array
    {
        return DB::table('training_sessions')->where('fighter_id', $fighterId)->where('game_date', $date)->where('bonus', '>', 0)
            ->groupBy('stat')->selectRaw('stat, MAX(bonus) as b')->pluck('b', 'stat')->map(fn ($v) => (int) $v)->all();
    }

    /** Peringkat season: skor terbaik tiap pemain. */
    public function board(string $game, int $season, ?int $meId = null, int $top = 3): array
    {
        $best = DB::table('training_sessions as t')->join('fighters as f', 'f.id', '=', 't.fighter_id')
            ->where('t.game', $game)->where('t.season', $season)->whereNotNull('t.finished_at')->where('t.score', '>', 0)
            ->where('f.status', 'active')->groupBy('t.fighter_id')->selectRaw('t.fighter_id, MAX(t.score) as best');
        $total = DB::query()->fromSub($best, 'b')->count();
        $rows = (clone $best)->orderByDesc('best')->orderBy('t.fighter_id')->limit($top)->get();

        $mine = null;
        $around = collect();
        if ($meId) {
            $my = (int) DB::table('training_sessions')->where('fighter_id', $meId)->where('game', $game)->where('season', $season)->whereNotNull('finished_at')->max('score');
            if ($my > 0) {
                $rank = DB::query()->fromSub($best, 'b')->where('best', '>', $my)->count() + 1;
                $mine = ['rank' => $rank, 'best' => $my];
                if ($rank > $top) {
                    $ahead = DB::query()->fromSub($best, 'b')->where('best', '>', $my)->orderBy('best')->limit(2)->get()->reverse();
                    $behind = DB::query()->fromSub($best, 'b')->where('best', '<=', $my)->where('fighter_id', '!=', $meId)->orderByDesc('best')->limit(2)->get();
                    $around = $ahead->values()->map(fn ($r, $i) => ['fighter_id' => $r->fighter_id, 'best' => (int) $r->best, 'rank' => $rank - $ahead->count() + $i])
                        ->push(['fighter_id' => $meId, 'best' => $my, 'rank' => $rank])
                        ->merge($behind->values()->map(fn ($r, $i) => ['fighter_id' => $r->fighter_id, 'best' => (int) $r->best, 'rank' => $rank + 1 + $i]));
                }
            }
        }

        $ids = $rows->pluck('fighter_id')->merge($around->pluck('fighter_id'))->unique();
        $names = Fighter::whereIn('id', $ids)->pluck('instagram_username', 'id');
        $row = fn ($id, $best, $rank) => ['rank' => $rank, 'username' => $names[$id] ?? '?', 'best' => (int) $best, 'me' => $id === $meId];

        return [
            'rank' => $mine['rank'] ?? null,
            'total' => $total,
            'top' => $rows->values()->map(fn ($r, $i) => $row($r->fighter_id, $r->best, $i + 1))->all(),
            'around' => $around->map(fn ($r) => $row($r['fighter_id'], $r['best'], $r['rank']))->values()->all(),
        ];
    }

    /** Ringkasan untuk halaman pilih latihan. */
    public function summary(?Fighter $f): array
    {
        $season = $this->game->currentSeasonNumber();
        $date = $this->calendar->gameDate();
        $out = [];
        foreach ($this->games() as $key => $g) {
            $row = ['stat' => $g['stat'], 'soon' => ! empty($g['soon']), 'stars' => $g['stars'] ?? []];
            if ($f && empty($g['soon'])) {
                $best = (int) DB::table('training_sessions')->where('fighter_id', $f->id)->where('game', $key)->where('season', $season)->whereNotNull('finished_at')->max('score');
                $row['best'] = $best;
                $row['rank'] = $best > 0 ? $this->board($key, $season, $f->id, 0)['rank'] : null;
                $row['bestToday'] = (int) DB::table('training_sessions')->where('fighter_id', $f->id)->where('game', $key)->where('game_date', $date)->whereNotNull('finished_at')->max('score');
            }
            $out[$key] = $row;
        }

        return $out;
    }

    public function xpToday(Fighter $f): int
    {
        return (int) DB::table('training_sessions')->where('fighter_id', $f->id)->where('game_date', $this->calendar->gameDate())->sum('xp');
    }
}
