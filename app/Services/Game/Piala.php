<?php

namespace App\Services\Game;

use App\Game\Bracket;
use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Rng;
use App\Game\Rules;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Cup;
use App\Models\CupEntry;
use App\Models\CupMatch;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\Season;
use App\Support\GameConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Piala Mingguan: turnamen gugur massal.
 *
 *   Senin 00:00   pendaftaran dibuka (piala minggu ini dibuat)
 *   Kamis 12:00   undian: unggulan dari bintang season lalu NILAI, bagan pangkat dua, bye untuk unggulan atas
 *   Sabtu 19:00   babak awal, tiap round_gap_minutes
 *   Minggu        3 babak terakhir di jam game.piala.finals
 * Laga dihitung saat jamnya tiba (piala:tick tiap menit); juara dapat lencana piala:{tanggal Senin}.
 */
class Piala
{
    private const DAYS = ['monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3, 'friday' => 4, 'saturday' => 5, 'sunday' => 6];

    public function __construct(
        private readonly Calendar $calendar,
        private readonly GameConfig $game,
        private readonly FightSimulator $sim,
        private readonly FighterFactory $factory,
        private readonly Rules $rules,
        private readonly ResultApplier $applier,
        private readonly StatBonus $bonus,
    ) {}

    public function current(?CarbonImmutable $now = null): Cup
    {
        return Cup::firstOrCreate(['week_start' => $this->calendar->weekStart($now)], ['status' => 'open']);
    }

    /** Jadwal piala sebuah minggu (UTC). */
    public function times(string $weekStart): array
    {
        $mon = CarbonImmutable::parse($weekStart, $this->calendar->tz())->startOfDay();
        $c = $this->game->get('piala');

        return [
            'reg_open' => $this->at($mon, $c['reg_open']),
            'draw' => $this->at($mon, $c['draw']),
            'early' => $this->at($mon, ($c['early_rounds_day'] ?? 'saturday').' '.$c['early_rounds_at']),
            'gap' => (int) $c['round_gap_minutes'],
            'finals' => array_map(fn ($f) => $this->at($mon, $f), $c['finals']),
        ];
    }

    public function tick(?CarbonImmutable $now = null): void
    {
        $now ??= $this->calendar->now();
        $cup = $this->current($now);
        if ($cup->status === 'open' && $now->greaterThanOrEqualTo($this->times($cup->week_start)['draw'])) {
            $this->draw($cup);
            $cup->refresh();
        }
        if ($cup->status === 'drawn') {
            $this->play($cup, $now);
        }
        // Piala minggu lalu yang belum selesai (server sempat mati): selesaikan.
        Cup::where('status', 'drawn')->where('id', '!=', $cup->id)->get()->each(fn ($c) => $this->play($c, $now));
    }

    /** @return string|null kode galat */
    public function join(Fighter $f, Cup $cup): ?string
    {
        if ($cup->status !== 'open') {
            return 'closed';
        }
        if ($f->is_npc || $f->status !== 'active') {
            return 'unavailable';
        }
        CupEntry::updateOrCreate(['cup_id' => $cup->id, 'fighter_id' => $f->id], ['status' => 'in', 'auto' => false]);

        return null;
    }

    public function leave(Fighter $f, Cup $cup): ?string
    {
        if ($cup->status !== 'open') {
            return 'closed';
        }
        // Baris tetap ada (status out) agar tidak didaftarkan otomatis saat undian.
        CupEntry::updateOrCreate(['cup_id' => $cup->id, 'fighter_id' => $f->id], ['status' => 'out', 'auto' => false]);

        return null;
    }

    public function draw(Cup $cup): void
    {
        DB::transaction(function () use ($cup) {
            $cup = Cup::whereKey($cup->id)->lockForUpdate()->first();
            if ($cup->status !== 'open') {
                return;
            }

            $days = (int) $this->game->get('piala.auto_active_days');
            if ($days > 0) {
                $known = CupEntry::where('cup_id', $cup->id)->pluck('fighter_id');
                $auto = Fighter::players()->active()->whereNotNull('activated_at')
                    ->where('last_active_at', '>=', now()->subDays($days))->whereNotIn('id', $known)->pluck('id');
                $now = now();
                foreach ($auto->chunk(500) as $part) {
                    CupEntry::insert($part->map(fn ($id) => [
                        'cup_id' => $cup->id, 'fighter_id' => $id, 'status' => 'in', 'auto' => true, 'created_at' => $now, 'updated_at' => $now,
                    ])->all());
                }
            }

            $ids = CupEntry::where('cup_id', $cup->id)->where('status', 'in')->pluck('fighter_id');
            $people = Fighter::whereIn('id', $ids)->players()->active()->get(['id', 'instagram_username', 'points', 'archetype']);
            $n = $people->count();
            if ($n < (int) $this->game->get('piala.min_entrants')) {
                $cup->forceFill(['status' => 'cancelled', 'entrants' => $n, 'drawn_at' => now()])->save();

                return;
            }

            $season = Season::current();
            $stars = $season ? FighterRecord::where('season_id', $season->id)->whereIn('fighter_id', $people->pluck('id'))->pluck('stars', 'fighter_id') : collect();
            $ranked = $people->map(fn ($p) => [
                'id' => $p->id, 'stars' => (int) ($stars[$p->id] ?? 0), 'ovr' => $this->factory->ovr($p->effectiveStats()),
            ])->sortBy([['stars', 'desc'], ['ovr', 'desc'], ['id', 'asc']])->values();

            $seedToId = [];
            foreach ($ranked as $i => $r) {
                $seedToId[$i + 1] = $r['id'];
                CupEntry::where('cup_id', $cup->id)->where('fighter_id', $r['id'])->update(['seed' => $i + 1]);
            }

            $size = Bracket::size($n);
            $rounds = (int) log($size, 2);
            $times = $this->roundTimes($cup->week_start, $rounds);
            $order = Bracket::seedOrder($size);
            $now = now();

            $rows = [];
            for ($r = 1; $r <= $rounds; $r++) {
                $count = $size >> $r;
                for ($s = 0; $s < $count; $s++) {
                    $rows[] = [
                        'cup_id' => $cup->id, 'round' => $r, 'slot' => $s,
                        'a_id' => $r === 1 ? ($seedToId[$order[2 * $s]] ?? null) : null,
                        'b_id' => $r === 1 ? ($seedToId[$order[2 * $s + 1]] ?? null) : null,
                        'scheduled_at' => $times[$r], 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $part) {
                CupMatch::insert($part);
            }

            $cup->forceFill(['status' => 'drawn', 'size' => $size, 'rounds' => $rounds, 'entrants' => $n, 'drawn_at' => now()])->save();

            // Bye: lawan kosong di babak 1 = langsung lolos.
            CupMatch::where('cup_id', $cup->id)->where('round', 1)
                ->where(fn ($q) => $q->whereNull('a_id')->orWhereNull('b_id'))->get()
                ->each(function (CupMatch $m) use ($cup) {
                    $m->forceFill(['walkover' => true])->save();
                    $this->advance($cup, $m, (int) ($m->a_id ?? $m->b_id));
                });
        });
    }

    /** Mainkan semua laga yang jamnya sudah tiba dan kedua petarungnya sudah diketahui. */
    public function play(Cup $cup, CarbonImmutable $now): int
    {
        $played = 0;
        for ($guard = 0; $guard < 64; $guard++) {
            $ready = CupMatch::where('cup_id', $cup->id)->whereNull('winner_id')->whereNotNull('a_id')->whereNotNull('b_id')
                ->where('scheduled_at', '<=', $now)->orderBy('round')->orderBy('slot')->limit(500)->get();
            if ($ready->isEmpty()) {
                break;
            }
            foreach ($ready as $m) {
                DB::transaction(fn () => $this->playMatch($cup, $m));
                $played++;
            }
        }

        return $played;
    }

    /** Admin: batalkan piala yang belum selesai. */
    public function cancel(Cup $cup): void
    {
        if (in_array($cup->status, ['open', 'drawn'], true)) {
            $cup->forceFill(['status' => 'cancelled'])->save();
        }
    }

    private function playMatch(Cup $cup, CupMatch $m): void
    {
        $m = CupMatch::whereKey($m->id)->lockForUpdate()->first();
        if ($m->winner_id) {
            return;
        }
        $at = CarbonImmutable::parse($m->scheduled_at);
        $fa = Fighter::find($m->a_id);
        $fb = Fighter::find($m->b_id);
        $version = $this->rules->current;
        $bonus = $this->bonus->for([$fa->id, $fb->id], $this->calendar->gameDate($at));
        $ia = $fa->fightInput($bonus[$fa->id] ?? [], $version);
        $ib = $fb->fightInput($bonus[$fb->id] ?? [], $version);
        $seed = Rng::fnv1a("cup|{$cup->id}|{$m->round}|{$m->slot}");
        $r = $this->sim->simulate($ia, $ib, $seed, $version);

        $fight = Fight::create([
            'kind' => 'cup', 'slot_at' => $at, 'season_id' => Season::current()?->id,
            'a_id' => $fa->id, 'b_id' => $fb->id, 'seed' => $seed, 'engine_version' => $version,
        ] + Fight::statColumns($ia, $ib, $r) + [
            'winner' => $r['win'], 'how' => $r['how'], 'round' => $r['rd'],
            'score_a' => $r['scoreA'], 'score_b' => $r['scoreB'], 'visible_at' => $at,
        ]);
        $this->applier->apply($fight);

        if ($r['win'] === 'draw') {
            // Seri di turnamen: menang yang sisa tenaganya lebih banyak, lalu unggulan lebih tinggi.
            $end = end($r['events']);
            if ($end['ha'] !== $end['hb']) {
                $side = $end['ha'] > $end['hb'] ? 'a' : 'b';
            } else {
                $seeds = CupEntry::where('cup_id', $cup->id)->whereIn('fighter_id', [$fa->id, $fb->id])->pluck('seed', 'fighter_id');
                $side = ($seeds[$fa->id] ?? PHP_INT_MAX) <= ($seeds[$fb->id] ?? PHP_INT_MAX) ? 'a' : 'b';
            }
            $winnerId = $side === 'a' ? $fa->id : $fb->id;
            $xp = $this->applier->grant($winnerId, 'cup', $fight->id, (int) $this->game->get('xp.cup_win'));
            $fight->forceFill(["xp_{$side}" => $xp])->save();
        } else {
            $winnerId = $r['win'] === 'a' ? $fa->id : $fb->id;
        }

        $m->forceFill(['fight_id' => $fight->id])->save();
        $this->advance($cup, $m, $winnerId);
    }

    private function advance(Cup $cup, CupMatch $m, int $winnerId): void
    {
        $m->forceFill(['winner_id' => $winnerId])->save();
        $loser = $m->a_id === $winnerId ? $m->b_id : $m->a_id;
        if ($loser) {
            CupEntry::where('cup_id', $cup->id)->where('fighter_id', $loser)->update(['out_round' => $m->round]);
        }

        if ($m->round >= $cup->rounds) {
            $cup->forceFill(['status' => 'done', 'champion_id' => $winnerId, 'finished_at' => now()])->save();
            Badge::award($winnerId, 'piala:'.$cup->week_start, ['week' => $cup->week_start, 'entrants' => $cup->entrants]);
            AuditLog::record('cup_champion', $cup, ['fighter_id' => $winnerId]);

            return;
        }

        CupMatch::where('cup_id', $cup->id)->where('round', $m->round + 1)->where('slot', intdiv($m->slot, 2))
            ->update([$m->slot % 2 === 0 ? 'a_id' : 'b_id' => $winnerId]);
    }

    /** @return array<int, CarbonImmutable> babak => jam (UTC) */
    public function roundTimes(string $weekStart, int $rounds): array
    {
        $t = $this->times($weekStart);
        $finals = $t['finals'];
        $f = min(count($finals), $rounds);
        $out = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $fromEnd = $rounds - $r; // 0 = final
            $out[$r] = $fromEnd < $f
                ? $finals[count($finals) - 1 - $fromEnd]
                : $t['early']->addMinutes(($r - 1) * $t['gap']);
        }

        return $out;
    }

    private function at(CarbonImmutable $monday, string $spec): CarbonImmutable
    {
        [$day, $time] = explode(' ', trim(strtolower($spec)), 2);

        return $monday->addDays(self::DAYS[$day] ?? 0)->setTimeFromTimeString($time)->utc();
    }
}
