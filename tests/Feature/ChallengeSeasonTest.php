<?php

namespace Tests\Feature;

use App\Game\FightSimulator;
use App\Models\Announcement;
use App\Models\Badge;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\FighterRecord;
use App\Models\Season;
use App\Services\Game\FightNight;
use App\Services\Game\Seasons;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\MakesFighters;
use Tests\TestCase;

class ChallengeSeasonTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00', 'UTC'));
        $this->seed(GameSeeder::class);
    }

    public function test_challenge_flow_xp_cap_and_limits(): void
    {
        $me = $this->loginAs($this->player('andi'));
        $this->player('budi');
        $this->player('citra');

        $this->postJson('/api/challenges', ['username' => 'andi'])->assertStatus(422)->assertJsonPath('error.code', 'self');
        $this->postJson('/api/challenges', ['username' => 'tidakada'])->assertNotFound();

        $code = $this->postJson('/api/challenges', ['username' => 'budi'])->assertCreated()->json('data.code');
        $f = Fight::where('code', $code)->firstOrFail();
        $this->assertSame('challenge', $f->kind);
        $this->assertNotNull($f->applied_at);
        $this->assertSame(0, (int) $f->xp_b);                    // lawan tidak dapat XP
        $this->assertSame(0, FighterRecord::count());            // tantangan tidak mengubah rekor season

        $data = $this->getJson("/api/challenges/{$code}")->assertOk()->json('data');
        $r = app(FightSimulator::class)->simulate($data['a'], $data['b'], $data['seed'], $data['v']);
        $this->assertSame($f->winner, $r['win']);
        $this->assertSame('andi', $data['fighters']['a']['username']);

        // Lawan yang sama: jeda 10 menit.
        $this->postJson('/api/challenges', ['username' => 'budi'])->assertStatus(429)->assertJsonPath('error.code', 'same_opponent');

        // XP hanya untuk 5 tantangan pertama hari ini.
        for ($i = 0; $i < 6; $i++) {
            $this->travel(11)->minutes();
            $this->postJson('/api/challenges', ['username' => 'citra'])->assertCreated();
        }
        $this->assertSame(5, DB::table('xp_ledger')->where('fighter_id', $me->id)->where('source', 'challenge')->count());
        $this->assertSame((int) DB::table('xp_ledger')->where('fighter_id', $me->id)->sum('amount'), (int) $me->refresh()->xp);

        // Batas harian.
        config(['game.challenge.daily_limit' => 7]);
        $this->travel(11)->minutes();
        $this->postJson('/api/challenges', ['username' => 'citra'])->assertStatus(429)->assertJsonPath('error.code', 'daily_limit');

        $this->getJson('/api/fighters/andi/fights')->assertJsonPath('data.items.0.code', Fight::where('a_id', $me->id)->latest('id')->value('code'));
    }

    public function test_guest_cannot_challenge(): void
    {
        $this->player('budi');
        $this->postJson('/api/challenges', ['username' => 'budi'])->assertUnauthorized();
    }

    public function test_season_close_ranks_badges_and_new_season(): void
    {
        foreach (['andi', 'budi', 'citra', 'dewi'] as $u) {
            $this->player($u, ['xp' => 120]);
        }
        app(FightNight::class)->runNow(CarbonImmutable::parse('2026-11-02 05:00', 'UTC'));
        $s0 = Season::current();
        $leader = $this->getJson('/api/ranking')->json('data.rows.0.username');

        config(['game.season.champion_top' => 2, 'game.season.xp_carry_percent' => 50]);
        $r = app(Seasons::class)->close('Season Satu');
        $this->assertSame(4, $r['ranked']);
        $this->assertSame('closed', $s0->refresh()->status);
        $this->assertSame(1, Season::current()->number);
        $this->assertSame('Season Satu', Season::current()->name);

        $this->assertSame(2, Badge::where('code', 'juara:s0')->count());
        $this->assertSame(4, Badge::where('code', 'perintis:s0')->count());
        $this->assertTrue(Badge::where('code', 'juara:s0')->where('fighter_id', Fighter::findByUsername($leader)->id)->exists());
        $this->assertSame(0, Badge::whereIn('fighter_id', Fighter::where('is_npc', true)->pluck('id'))->count());

        // XP dibawa 50%.
        $andi = Fighter::findByUsername('andi');
        $earned = (int) DB::table('xp_ledger')->where('fighter_id', $andi->id)->sum('amount');
        $this->assertSame(intdiv(120 + $earned, 2), (int) $andi->xp);

        // Season baru: ranking kosong, hall of fame terisi.
        $this->getJson('/api/ranking')->assertJsonCount(0, 'data.rows');
        $this->getJson('/api/seasons/0')->assertOk()->assertJsonPath('data.rows.0.username', $leader)->assertJsonPath('data.rows.0.rank', 1);
        $this->getJson("/api/fighters/{$leader}")->assertJsonFragment(['code' => 'juara:s0']);
        $this->getJson('/api/mading')->assertJsonPath('data.champions.0.fighter.username', $leader);

        $this->artisan('season:close --force')->assertSuccessful();
        $this->assertSame(2, Season::current()->number);
    }

    public function test_mading_shows_news_and_last_night(): void
    {
        foreach (['andi', 'budi', 'citra', 'dewi'] as $u) {
            $this->player($u);
        }
        Announcement::create(['title' => 'Selamat datang', 'body' => 'Season 0 dimulai!', 'pinned' => true, 'published_at' => now()->subHour()]);
        Announcement::create(['title' => 'Draf', 'body' => 'rahasia', 'published_at' => null]);
        Announcement::create(['title' => 'Nanti', 'body' => 'terjadwal', 'published_at' => now()->addDay()]);
        app(FightNight::class)->runNow(CarbonImmutable::parse('2026-11-02 05:00', 'UTC'));

        $d = $this->getJson('/api/mading')->assertOk()->json('data');
        $this->assertCount(1, $d['news']);
        $this->assertSame('Selamat datang', $d['news'][0]['title']);
        $this->assertSame(2, $d['night']['fights']);
        $this->assertCount(4, $d['week']);
        $this->assertSame('2026-11-02T11:00:00+00:00', $d['next']);
    }
}
