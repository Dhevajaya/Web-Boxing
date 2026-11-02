<?php

namespace Tests\Feature;

use App\Game\FightSimulator;
use App\Models\Fighter;
use App\Models\Sasana;
use App\Models\Season;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_sasana_season_and_npcs(): void
    {
        $this->seed(GameSeeder::class);
        $this->seed(GameSeeder::class); // aman diulang

        $this->assertSame(count(config('game.sasana')), Sasana::count());
        $this->assertSame(1, Season::where('status', 'active')->count());
        $this->assertGreaterThan(0, Fighter::where('is_npc', true)->count());
    }

    public function test_pages_render(): void
    {
        foreach (['/', '/mading', '/latihan', '/budi_99', '/t/abc12345'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="view"', false);
        }
    }

    public function test_engine_preview_matches_simulator(): void
    {
        $res = $this->getJson('/api/engine/preview/si.biru/si.merah?seed=2026')->assertOk();
        $d = $res->json('data');

        $r = app(FightSimulator::class)->simulate($d['a'], $d['b'], 2026);
        $this->assertSame(FightSimulator::signature($r), $d['sig']);
    }

    public function test_unregistered_fighter_still_has_a_card(): void
    {
        $this->getJson('/api/fighters/@Budi_99')
            ->assertOk()
            ->assertJsonPath('data.username', 'budi_99')
            ->assertJsonPath('data.registered', false);

        $this->getJson('/api/fighters/bukan%20valid!')->assertStatus(400);
    }

    public function test_registered_fighter_uses_points(): void
    {
        $this->seed(GameSeeder::class);
        $f = Fighter::create(['instagram_username' => 'andi', 'status' => 'active', 'points' => ['strength' => 5]]);
        $base = $f->base()['base']['strength'];

        $this->getJson('/api/fighters/andi')
            ->assertOk()
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.stats.strength', min(99, $base + 5));
    }
}
