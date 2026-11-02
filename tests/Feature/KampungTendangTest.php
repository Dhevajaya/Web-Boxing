<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\Season;
use App\Services\Game\Challenges;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\MakesFighters;
use Tests\TestCase;

class KampungTendangTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 03:00:00', 'UTC'));
        $this->seed(GameSeeder::class);
    }

    public function test_new_fights_use_engine_v2_with_snapshot_and_replay(): void
    {
        $a = $this->player('andi', ['archetype' => 'raja_tendang', 'xp' => 10000, 'jurus' => ['tendang_sapu', 'pukulan_pamungkas']]);
        $b = $this->player('budi');
        $f = app(Challenges::class)->create($a, $b);
        $this->assertInstanceOf(Fight::class, $f);
        $this->assertSame(2, (int) $f->engine_version);

        $snap = json_decode($f->a_snap, true);
        $this->assertSame('raja_tendang', $snap['style']);
        $this->assertSame(['tendang_sapu', 'pukulan_pamungkas'], $snap['jurus']);
        $this->assertCount(10, $snap['stats']);
        $this->assertSame($snap['stats']['strength'], (int) $f->a_power); // kolom lama diisi dari atribut baru

        // Replay dari snapshot identik dengan hasil tersimpan.
        $r = $f->replay();
        $this->assertSame([$f->winner, $f->how, (int) $f->round], [$r['win'], $r['how'], $r['rd']]);
        $sum = $f->summaryData();
        $this->assertNotEmpty($sum['rounds']);

        $payload = $this->getJson("/api/challenges/{$f->code}")->assertOk()->json('data');
        $this->assertSame(2, $payload['v']);
        $this->assertSame($snap, $payload['a']);
        $this->assertArrayHasKey('summary', $payload);
    }

    public function test_jurus_ownership_follows_level_and_archetype(): void
    {
        $f = $this->loginAs($this->player('citra', ['archetype' => 'jago_gebuk']));
        $p = $this->getJson('/api/me/profile')->assertOk();
        $this->assertSame('jago_gebuk', $p->json('data.style'));
        $this->assertSame(['gebuk_beruntun'], $p->json('data.jurusEquipped'));
        $this->assertCount(5, $p->json('data.archetypes'));
        $this->assertSame(['gebuk_beruntun', 'patah_ranting', 'sambar_badan', 'pukulan_pamungkas'], array_column($p->json('data.jurus'), 'id'));

        $this->putJson('/api/me/jurus', ['jurus' => ['gebuk_beruntun', 'sambar_badan']])->assertStatus(422)->assertJsonPath('error.code', 'invalid'); // Lv 5
        $this->putJson('/api/me/jurus', ['jurus' => ['tendang_sapu']])->assertStatus(422);    // milik archetype lain
        $this->putJson('/api/me/jurus', ['jurus' => ['gebuk_beruntun', 'gebuk_beruntun']])->assertStatus(422);
        $this->putJson('/api/me/jurus', ['jurus' => []])->assertStatus(422);

        Fighter::whereKey($f->id)->update(['xp' => 2000]); // Lv 10+
        $this->putJson('/api/me/jurus', ['jurus' => ['pukulan_pamungkas', 'sambar_badan']])->assertOk()
            ->assertJsonPath('data.jurusEquipped', ['pukulan_pamungkas', 'sambar_badan']);
        $this->assertSame(['pukulan_pamungkas', 'sambar_badan'], Fighter::find($f->id)->fightInput()['jurus']);

        // Level turun (reset): jurus terkunci tidak ikut laga, kembali ke jurus khas.
        Fighter::whereKey($f->id)->update(['xp' => 0]);
        $this->assertSame(['gebuk_beruntun'], Fighter::find($f->id)->fightInput()['jurus']);
    }

    public function test_archetype_change_has_cooldown_and_changes_base_stats(): void
    {
        $f = $this->loginAs($this->player('dodi'));
        $default = $f->archetypeId();
        $target = $default === 'raja_tendang' ? 'ahli_balas' : 'raja_tendang';
        $before = $f->baseFor();

        $this->putJson('/api/me/archetype', ['archetype' => 'petinju'])->assertStatus(422);
        $res = $this->putJson('/api/me/archetype', ['archetype' => $target])->assertOk()->assertJsonPath('data.style', $target);
        $this->assertNotNull($res->json('data.archetypeNextAt'));
        $this->assertNotEquals($before, Fighter::find($f->id)->baseFor());

        $this->putJson('/api/me/archetype', ['archetype' => $default])->assertStatus(429)->assertJsonPath('error.code', 'cooldown');
        $this->travel(8)->days();
        $this->putJson('/api/me/archetype', ['archetype' => $default])->assertOk();
        $this->assertSame($before, Fighter::find($f->id)->baseFor()); // kembali persis ke stat semula

        $this->getJson('/api/fighters/dodi')->assertOk()->assertJsonPath('data.style', $default)->assertJsonCount(1, 'data.jurus');
    }

    public function test_competitive_reset_dry_run_then_run(): void
    {
        $a = $this->player('eka', ['xp' => 500, 'points' => ['strength' => 3], 'archetype' => 'jaga_jarak', 'sasana_id' => DB::table('sasanas')->value('id'), 'sasana_season' => 0]);
        $b = $this->player('fani');
        app(Challenges::class)->create($a, $b);
        $this->player('gita'); // belum pernah bertarung: tidak dapat lencana veteran
        $fights = Fight::count();

        $this->artisan('game:reset-competitive --dry-run')->expectsOutputToContain('Dry-run')->assertSuccessful();
        $this->assertGreaterThanOrEqual(500, (int) Fighter::find($a->id)->xp);
        $this->assertSame(0, Badge::where('code', 'veteran')->count());

        $this->artisan('game:reset-competitive --force')->assertSuccessful();
        $a->refresh();
        $this->assertSame([0, 1, null, null], [(int) $a->xp, (int) $a->level, $a->points, $a->archetype]);
        $this->assertEqualsCanonicalizing(['eka', 'fani'], Badge::where('code', 'veteran')->join('fighters', 'fighters.id', '=', 'badges.fighter_id')->pluck('instagram_username')->all());
        $s = Season::current();
        $this->assertSame([1, 'tendang'], [$s->number, $s->era]);
        $this->assertSame('tinju', Season::where('number', 0)->value('era'));
        $this->assertSame(1, (int) $a->sasana_season); // tetap anggota geng
        $this->assertSame($fights, Fight::count());       // arsip laga utuh
        $this->getJson('/api/ranking')->assertOk()->assertJsonCount(0, 'data.rows');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'competitive_reset')->count());
    }
}
