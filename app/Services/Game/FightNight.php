<?php

namespace App\Services\Game;

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Game\Pairing;
use App\Game\Rng;
use App\Game\Rules;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\FightNightRun;
use App\Models\Season;
use App\Support\GameConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fight Night: laga otomatis untuk semua petarung aktif, tiap slot di game.fight_night.hours.
 *
 * Dijalankan cron tiap menit (fightnight:tick). Satu run per slot melewati status:
 *   preparing   -> pasangkan petarung (prepare_minutes sebelum slot)
 *   simulating  -> hitung laga per potongan (chunk), simpan dengan visible_at = slot
 *   ready       -> tunggu jam slot
 *   publishing  -> terapkan hasil ke rekor/bintang/XP per potongan
 *   done
 * Laga terlihat pemain tepat di jam slot karena visible_at = slot_at.
 */
class FightNight
{
    public function __construct(
        private readonly Calendar $calendar,
        private readonly GameConfig $game,
        private readonly FightSimulator $sim,
        private readonly FighterFactory $factory,
        private readonly Rules $rules,
        private readonly ResultApplier $applier,
        private readonly StatBonus $bonus,
    ) {}

    /** @return list<string> catatan langkah */
    public function tick(?CarbonImmutable $now = null, float $budgetSeconds = 45): array
    {
        $now ??= $this->calendar->now();
        $started = microtime(true);
        $log = [];

        $runs = [];
        $prev = $this->calendar->prevSlot($now);
        $run = FightNightRun::where('slot_at', $prev)->first();
        // Server sempat mati saat jam slot: tetap jalankan bila belum lewat 30 menit.
        if (! $run && $prev->diffInMinutes($now) <= 30) {
            $run = $this->open($prev);
        }
        if ($run) {
            $runs[$run->id] = $run;
        }

        $next = $this->calendar->nextSlot($now);
        if ($now->greaterThanOrEqualTo($next->subMinutes((int) $this->game->get('fight_night.prepare_minutes')))) {
            $run = $this->open($next);
            $runs[$run->id] = $run;
        }

        foreach ($runs as $run) {
            while (microtime(true) - $started < $budgetSeconds && $this->step($run, $now)) {
                $log[] = $run->slot_at->toIso8601String().' '.$run->status.' '.$run->cursor.'/'.$run->pairs_total;
            }
        }

        return $log;
    }

    /** Jalankan satu slot sampai selesai sekarang juga (perintah fightnight:run, test). */
    public function runNow(CarbonImmutable $slot): FightNightRun
    {
        $run = $this->open($slot);
        $now = $slot->max($this->calendar->now());
        while ($this->step($run, $now)) {
            // lanjut
        }

        return $run->refresh();
    }

    public function open(CarbonImmutable $slot): FightNightRun
    {
        return FightNightRun::firstOrCreate(['slot_at' => $slot], ['status' => 'preparing']);
    }

    /** Satu langkah. true = ada kemajuan dan boleh lanjut langsung. */
    public function step(FightNightRun $run, CarbonImmutable $now): bool
    {
        try {
            switch ($run->status) {
                case 'preparing':
                    $plan = $this->plan(CarbonImmutable::parse($run->slot_at));
                    $run->forceFill([
                        'plan' => $plan, 'pairs_total' => count($plan), 'cursor' => 0,
                        'status' => 'simulating', 'prepared_at' => now(), 'error' => null,
                    ])->save();

                    return true;

                case 'simulating':
                    if ($run->cursor >= $run->pairs_total) {
                        $run->forceFill(['status' => 'ready'])->save();

                        return true;
                    }
                    $this->simulateChunk($run);

                    return true;

                case 'ready':
                    if ($now->lessThan(CarbonImmutable::parse($run->slot_at))) {
                        return false;
                    }
                    $run->forceFill(['status' => 'publishing'])->save();

                    return true;

                case 'publishing':
                    $fights = Fight::where('kind', 'night')->where('slot_at', $run->slot_at)->whereNull('applied_at')
                        ->orderBy('id')->limit($this->chunk())->get();
                    foreach ($fights as $f) {
                        $this->applier->apply($f);
                    }
                    if ($fights->isEmpty()) {
                        $run->forceFill(['status' => 'done', 'published_at' => now(), 'plan' => null])->save();

                        return false;
                    }
                    $run->forceFill(['applied' => $run->applied + $fights->count()])->save();

                    return true;
            }
        } catch (Throwable $e) {
            report($e);
            $run->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }

        return false;
    }

    /** Admin: ulangi run gagal dari status yang aman. */
    public function retry(FightNightRun $run): void
    {
        if ($run->status !== 'failed') {
            return;
        }
        $status = $run->plan === null ? 'preparing' : ($run->cursor < $run->pairs_total ? 'simulating' : 'publishing');
        $run->forceFill(['status' => $status, 'error' => null])->save();
    }

    /** @return list<array{0:int,1:int,2:int}> */
    public function plan(CarbonImmutable $slot): array
    {
        $since = $slot->subDays((int) $this->game->get('fight_night.active_days'));
        $players = Fighter::players()->active()->whereNotNull('activated_at')
            ->where('last_active_at', '>=', $since)
            ->get(['id', 'instagram_username', 'points']);

        $season = Season::current();
        $records = $season
            ? FighterRecord::where('season_id', $season->id)->whereIn('fighter_id', $players->pluck('id'))
                ->get(['fighter_id', 'stars', 'last_fight_id'])->keyBy('fighter_id')
            : collect();
        $lastFights = Fight::whereIn('id', $records->pluck('last_fight_id')->filter())->get(['id', 'a_id', 'b_id'])->keyBy('id');

        $list = [];
        foreach ($players as $p) {
            $rec = $records->get($p->id);
            $last = $rec?->last_fight_id ? $lastFights->get($rec->last_fight_id) : null;
            $list[] = [
                'id' => $p->id,
                'stars' => (int) ($rec->stars ?? 0),
                'ovr' => $this->factory->ovr($p->effectiveStats()),
                'last' => $last ? (int) ($last->a_id === $p->id ? $last->b_id : $last->a_id) : null,
            ];
        }

        $npcs = Fighter::where('is_npc', true)->active()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Pairing::pair($list, $npcs, Rng::fnv1a('night|'.$slot->timestamp));
    }

    private function simulateChunk(FightNightRun $run): void
    {
        $slot = CarbonImmutable::parse($run->slot_at);
        $slice = array_slice($run->plan ?? [], $run->cursor, $this->chunk());
        $ids = array_values(array_unique(array_merge(array_column($slice, 0), array_column($slice, 1))));
        $fighters = Fighter::whereIn('id', $ids)->get(['id', 'instagram_username', 'points'])->keyBy('id');
        $bonus = $this->bonus->for($ids, $this->calendar->gameDate($slot));
        $version = $this->rules->current;
        $seasonId = Season::current()?->id;
        $now = now();

        $rows = [];
        foreach ($slice as [$a, $b, $seed]) {
            $fa = $fighters->get($a);
            $fb = $fighters->get($b);
            if (! $fa || ! $fb) {
                continue; // petarung dihapus setelah dipasangkan
            }
            $ia = $fa->fightInput($bonus[$a] ?? [], $version);
            $ib = $fb->fightInput($bonus[$b] ?? [], $version);
            $r = $this->sim->simulate($ia, $ib, (int) $seed, $version);
            $rows[] = [
                'kind' => 'night', 'slot_at' => $slot, 'season_id' => $seasonId,
                'a_id' => $a, 'b_id' => $b, 'seed' => (int) $seed, 'engine_version' => $version,
            ] + Fight::statColumns($ia, $ib) + [
                'winner' => $r['win'], 'how' => $r['how'], 'round' => $r['rd'],
                'score_a' => $r['scoreA'], 'score_b' => $r['scoreB'],
                'visible_at' => $slot, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows, $run, $slice) {
            foreach (array_chunk($rows, 500) as $part) {
                Fight::insert($part);
            }
            $run->forceFill(['cursor' => $run->cursor + count($slice)])->save();
        });
    }

    private function chunk(): int
    {
        return max(1, (int) $this->game->get('fight_night.chunk'));
    }
}
