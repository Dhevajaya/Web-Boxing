<?php

namespace App\Services\Game;

use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Fighter;
use App\Models\Season;
use App\Support\GameConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mulai era KAMPUNG TENDANG: statistik kompetitif dinolkan, data lama tetap tersimpan sebagai arsip.
 *
 * Dinolkan : XP & level, poin stat, archetype & jurus, rekor minggu ini (Adu Reputasi).
 * Ditutup  : season berjalan (peringkat akhir + lencana juara tetap tersimpan, era "tinju").
 * Dibuka   : season baru era "tendang" (ranking, poin geng, rekor latihan otomatis mulai dari nol).
 * Tetap    : akun, username, tampilan, julukan, kota, keanggotaan geng, donasi, pengumuman, laga lama.
 * Lencana  : "veteran" untuk pemain yang sudah pernah bertarung atau punya XP (tanpa bonus stat).
 */
class CompetitiveReset
{
    public function __construct(
        private readonly Seasons $seasons,
        private readonly Calendar $calendar,
        private readonly GameConfig $game,
    ) {}

    /** Hitungan yang akan terdampak (tanpa mengubah apa pun). */
    public function plan(): array
    {
        $week = $this->calendar->weekStart();
        $season = Season::current();

        return [
            'season' => $season ? ['number' => $season->number, 'name' => $season->name, 'era' => $season->era] : null,
            'rows' => [
                'fighters: xp, level, poin stat, archetype, jurus dinolkan' => Fighter::players()
                    ->where(fn ($q) => $q->where('xp', '>', 0)->orWhereNotNull('points')->orWhereNotNull('archetype')->orWhereNotNull('jurus'))->count(),
                'badges: lencana veteran baru' => $this->veteranQuery()->count(),
                'weekly_records: rekor minggu ini dihapus' => DB::table('weekly_records')->where('week_start', $week)->count(),
                'fighter_records season berjalan: dibekukan sebagai arsip' => $season ? DB::table('fighter_records')->where('season_id', $season->id)->count() : 0,
                'fighters: keanggotaan geng dipindah ke season baru' => $season ? Fighter::players()->where('sasana_season', $season->number)->whereNotNull('sasana_id')->count() : 0,
                'fights: laga lama tetap ada (arsip, tidak masuk ranking baru)' => DB::table('fights')->count(),
            ],
        ];
    }

    public function run(?int $adminId = null): array
    {
        $plan = $this->plan();
        $old = Season::current();
        $veterans = $this->veteranQuery()->pluck('id');

        // 1. Tutup season lama (peringkat & lencana juara tersimpan), buka season era baru.
        $closed = $this->seasons->close('Season '.(($old?->number ?? -1) + 1).' · Kampung Tendang', $adminId);
        $new = $closed['next'];
        $closed['closed']->forceFill(['era' => 'tinju'])->save();
        $new->forceFill(['era' => 'tendang'])->save();

        DB::transaction(function () use ($veterans, $old, $new) {
            foreach ($veterans as $id) {
                Badge::award((int) $id, 'veteran', ['era' => 'tinju']);
            }
            Fighter::players()->update([
                'xp' => 0, 'level' => 1, 'points' => null, 'points_respec_at' => null,
                'archetype' => null, 'archetype_changed_at' => null, 'jurus' => null,
            ]);
            if ($old) {
                Fighter::players()->where('sasana_season', $old->number)->whereNotNull('sasana_id')->update(['sasana_season' => $new->number]);
            }
            DB::table('weekly_records')->where('week_start', $this->calendar->weekStart())->delete();
        });

        foreach (['mading:v1', 'game.season.current'] as $k) {
            Cache::forget($k);
        }
        $this->game->flush();
        AuditLog::record('competitive_reset', $new, $plan + ['veterans' => $veterans->count()], $adminId ? 'admin' : 'system', $adminId);

        return ['season' => $new, 'veterans' => $veterans->count(), 'plan' => $plan];
    }

    private function veteranQuery()
    {
        return Fighter::players()->where(fn ($q) => $q->where('xp', '>', 0)
            ->orWhereExists(fn ($s) => $s->from('fights')->whereColumn('fights.a_id', 'fighters.id'))
            ->orWhereExists(fn ($s) => $s->from('fights')->whereColumn('fights.b_id', 'fighters.id')))
            ->whereNotExists(fn ($s) => $s->from('badges')->whereColumn('badges.fighter_id', 'fighters.id')->where('badges.code', 'veteran'));
    }
}
