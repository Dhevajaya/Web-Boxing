<?php

namespace Tests\Feature;

use App\Game\FightSimulator;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\FightNightRun;
use App\Models\PlayerSession;
use App\Models\WeeklyRecord;
use App\Services\Auth\PlayerAuth;
use App\Services\Game\Calendar;
use App\Services\Game\FightNight;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FightNightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Senin 2 Nov 2026 17:30 WIB = 10:30 UTC, setengah jam sebelum slot 18:00 WIB.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00', 'UTC'));
        $this->seed(GameSeeder::class);
    }

    private function player(string $u, array $attrs = []): Fighter
    {
        return Fighter::create($attrs + ['instagram_username' => $u, 'status' => 'active', 'activated_at' => now(), 'last_active_at' => now()]);
    }

    private function actingAsFighter(Fighter $f): void
    {
        $row = PlayerSession::create(['fighter_id' => $f->id, 'session_id_hash' => hash('sha256', $f->id.'s')]);
        $this->actingAs($f, 'fighter')->withSession([PlayerAuth::KEY => $row->id]);
    }

    public function test_calendar_slots_in_game_timezone(): void
    {
        $cal = app(Calendar::class);
        $this->assertSame('2026-11-02T11:00:00+00:00', $cal->nextSlot()->toIso8601String()); // 18:00 WIB
        $this->assertSame('2026-11-02T05:00:00+00:00', $cal->prevSlot()->toIso8601String()); // 12:00 WIB
        $this->assertSame('2026-11-02', $cal->weekStart());
        // 23:30 WIB -> slot berikutnya 00:00 WIB besok = 17:00 UTC hari ini.
        $this->assertSame('2026-11-02T17:00:00+00:00', $cal->nextSlot(CarbonImmutable::parse('2026-11-02 16:30', 'UTC'))->toIso8601String());
    }

    public function test_full_cycle_prepare_hide_publish_apply(): void
    {
        $players = collect(['andi', 'budi', 'citra', 'dewi', 'eko'])->map(fn ($u) => $this->player($u));
        $this->player('lama', ['last_active_at' => now()->subDays(30)]);   // tidak aktif
        $this->player('susp', ['status' => 'suspended']);                    // disuspend

        app(FightNight::class)->tick();
        $run = FightNightRun::sole();
        $this->assertSame('ready', $run->status);
        $this->assertSame(3, $run->pairs_total); // 5 pemain = 2 pasang + 1 lawan NPC
        $this->assertSame(3, Fight::count());

        // Belum jam slot: laga tersembunyi.
        $fight = Fight::first();
        $this->getJson("/api/fights/{$fight->id}")->assertNotFound();
        $this->assertSame(0, FighterRecord::count());

        $ids = Fight::pluck('a_id')->merge(Fight::pluck('b_id'));
        foreach ($players as $p) {
            $this->assertSame(1, $ids->filter(fn ($x) => $x === $p->id)->count(), $p->instagram_username);
        }
        $this->assertFalse($ids->contains(Fighter::where('instagram_username', 'lama')->value('id')));
        $this->assertFalse($ids->contains(Fighter::where('instagram_username', 'susp')->value('id')));

        // Jam 18:00 WIB.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00:30', 'UTC'));
        app(FightNight::class)->tick();
        $this->assertSame('done', $run->refresh()->status);
        $this->assertSame(0, Fight::whereNull('applied_at')->count());

        // Rekor dan XP sesuai hasil.
        foreach (Fight::all() as $f) {
            foreach (['a', 'b'] as $side) {
                $fighter = Fighter::find($f->{"{$side}_id"});
                $rec = FighterRecord::where('fighter_id', $fighter->id)->first();
                $outcome = $f->winner === 'draw' ? 'd' : ($f->winner === $side ? 'w' : 'l');
                $this->assertSame(1, $rec->{$outcome});
                $this->assertSame($outcome === 'w' ? 1 : 0, $rec->stars);
                $this->assertSame(1, WeeklyRecord::where('fighter_id', $fighter->id)->where('week_start', '2026-11-02')->value($outcome));
                if (! $fighter->is_npc) {
                    $this->assertSame((int) $f->{"xp_{$side}"}, (int) $fighter->xp);
                    $this->assertGreaterThan(0, $fighter->xp);
                } else {
                    $this->assertSame(0, (int) $fighter->xp);
                }
            }
        }

        // Idempoten: tick ulang tidak menggandakan apa pun.
        $xpBefore = Fighter::sum('xp');
        app(FightNight::class)->tick();
        $this->assertSame($xpBefore, Fighter::sum('xp'));
        $this->assertSame(DB::table('xp_ledger')->count(), Fighter::where('xp', '>', 0)->count());

        // Replay di server sama dengan hasil tersimpan; API mengirim input lengkap.
        $res = $this->getJson("/api/fights/{$fight->id}")->assertOk()->json('data');
        $r = app(FightSimulator::class)->simulate($res['a'], $res['b'], $res['seed'], $res['v']);
        $this->assertSame([$fight->winner, $fight->how, (int) $fight->round], [$r['win'], $r['how'], $r['rd']]);
        $this->assertArrayHasKey('look', $res['fighters']['a']);
    }

    public function test_ranking_history_and_status(): void
    {
        foreach (['andi', 'budi', 'citra', 'dewi'] as $u) {
            $this->player($u);
        }
        app(FightNight::class)->runNow(CarbonImmutable::parse('2026-11-02 05:00', 'UTC')); // slot 12:00 WIB yang sudah lewat

        $rows = $this->getJson('/api/ranking')->assertOk()->json('data.rows');
        $this->assertCount(4, $rows);   // NPC tidak masuk ranking
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertGreaterThanOrEqual($rows[3]['stars'], $rows[0]['stars']);
        $week = $this->getJson('/api/ranking?board=week')->assertOk()->json('data.rows');
        $this->assertCount(4, $week);

        $last = $rows[3]['username'];
        $me = Fighter::findByUsername($last);
        $this->actingAsFighter($me);
        $this->getJson('/api/ranking')->assertJsonPath('data.me.username', $last);
        $myRank = $this->getJson('/api/ranking')->json('data.me.rank');
        $this->assertSame(collect($rows)->firstWhere('username', $last)['rank'], $myRank);

        $hist = $this->getJson("/api/fighters/{$last}/fights")->assertOk()->json('data.items');
        $this->assertCount(1, $hist);
        $this->assertContains($hist[0]['result'], ['win', 'lose', 'draw']);
        $this->assertNotNull($hist[0]['opponent']['username']);

        $st = $this->getJson('/api/fight-night')->assertOk()->json('data');
        $this->assertSame('2026-11-02T11:00:00+00:00', $st['next']);
        $this->assertSame('done', $st['last']['status']);
        $this->assertSame($hist[0]['id'], $st['mine']['id']);
    }

    public function test_failed_run_can_retry_and_missed_slot_is_skipped(): void
    {
        $this->player('andi');
        $this->player('budi');
        // Lebih dari 30 menit setelah slot 12:00 WIB tanpa run: tidak dikejar.
        app(FightNight::class)->tick(CarbonImmutable::parse('2026-11-02 06:00', 'UTC'));
        $this->assertSame(0, FightNightRun::count());

        $fn = app(FightNight::class);
        $run = $fn->open(CarbonImmutable::parse('2026-11-02 11:00', 'UTC'));
        $run->forceFill(['status' => 'failed', 'error' => 'uji'])->save();
        $fn->retry($run);
        $this->assertSame('preparing', $run->refresh()->status);
        $fn->runNow(CarbonImmutable::parse('2026-11-02 11:00', 'UTC'));
        $this->assertSame('done', $run->refresh()->status);
    }

    public function test_artisan_run_now(): void
    {
        $this->player('andi');
        $this->player('budi');
        $this->artisan('fightnight:run --now')->assertSuccessful();
        $this->assertSame(1, Fight::where('kind', 'night')->whereNotNull('applied_at')->count());
    }
}
