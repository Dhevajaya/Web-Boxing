<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Cup;
use App\Models\CupEntry;
use App\Models\CupMatch;
use App\Models\Fight;
use App\Models\Fighter;
use App\Services\Game\FightNight;
use App\Services\Game\Piala;
use App\Services\Game\Seasons;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesFighters;
use Tests\TestCase;

class PialaSasanaTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00', 'UTC')); // Senin 17:30 WIB
        $this->seed(GameSeeder::class);
    }

    public function test_full_cup_week(): void
    {
        $names = ['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9', 'a10'];
        foreach ($names as $u) {
            $this->player($u);
        }
        $this->player('pasif', ['last_active_at' => now()->subDays(10)]);
        $piala = app(Piala::class);

        // Pendaftaran.
        $this->loginAs(Fighter::findByUsername('a1'));
        $this->postJson('/api/piala/join')->assertOk();
        $d = $this->getJson('/api/piala')->assertOk()->json('data');
        $this->assertSame('open', $d['cup']['status']);
        $this->assertTrue($d['me']['entered']);
        $this->assertSame('2026-11-05T05:00:00+00:00', $d['cup']['draw_at']);   // Kamis 12:00 WIB
        $this->assertSame('2026-11-08T13:00:00+00:00', $d['cup']['final_at']);  // Minggu 20:00 WIB

        $this->loginAs(Fighter::findByUsername('a10'));
        $this->postJson('/api/piala/leave')->assertOk(); // mundur: tidak didaftarkan otomatis

        // Undian Kamis 12:00 WIB. Pemain aktif 2 hari terakhir didaftarkan otomatis.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 05:00:10', 'UTC'));
        Fighter::whereIn('instagram_username', $names)->update(['last_active_at' => now()->subDay()]);
        $piala->tick();
        $cup = Cup::sole();
        $this->assertSame('drawn', $cup->status);
        $this->assertSame(9, $cup->entrants);       // a1..a9 (a10 mundur, pasif tidak aktif, NPC tidak ikut)
        $this->assertSame(16, $cup->size);
        $this->assertSame(4, $cup->rounds);
        $this->assertSame(7, CupMatch::where('cup_id', $cup->id)->where('walkover', true)->count());
        $this->assertSame(1, CupEntry::where('cup_id', $cup->id)->whereNotNull('seed')->min('seed'));
        $this->assertSame(0, Fight::where('kind', 'cup')->count());
        $this->postJson('/api/piala/leave')->assertStatus(422);

        $times = CupMatch::where('cup_id', $cup->id)->groupBy('round')->selectRaw('round, MIN(scheduled_at) as at')->pluck('at', 'round');
        $this->assertStringStartsWith('2026-11-07 12:00', (string) $times[1]);  // Sabtu 19:00 WIB
        $this->assertStringStartsWith('2026-11-08 12:00', (string) $times[2]);  // Minggu 19:00 WIB
        $this->assertStringStartsWith('2026-11-08 13:00', (string) $times[4]);  // Minggu 20:00 WIB

        // Bagan sebelum babak 1: belum ada pemenang kecuali bye.
        $b = $this->getJson('/api/piala')->json('data.bracket');
        $this->assertCount(4, $b);
        $this->assertSame('Final', $b[3]['name']);

        // Sabtu 19:00 WIB: babak 1 (1 laga: unggulan 8 lawan 9, sisanya bye).
        $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:30', 'UTC'));
        $piala->tick();
        $this->assertSame(1, Fight::where('kind', 'cup')->count());
        $this->assertSame(0, CupMatch::where('cup_id', $cup->id)->where('round', 1)->whereNull('winner_id')->count());

        // Minggu 20:00 WIB lewat: semua babak selesai.
        $this->travelTo(CarbonImmutable::parse('2026-11-08 13:01', 'UTC'));
        $piala->tick();
        $cup->refresh();
        $this->assertSame('done', $cup->status);
        $this->assertSame(8, Fight::where('kind', 'cup')->count()); // 1 + 4 + 2 + 1
        $this->assertNotNull($cup->champion_id);
        $this->assertTrue(Badge::where('fighter_id', $cup->champion_id)->where('code', 'piala:2026-11-02')->exists());
        $this->assertNull(CupEntry::where('cup_id', $cup->id)->where('fighter_id', $cup->champion_id)->value('out_round'));
        $this->assertSame(8, CupEntry::where('cup_id', $cup->id)->whereNotNull('out_round')->count());
        $this->assertGreaterThan(0, (int) Fighter::find($cup->champion_id)->xp);

        $d = $this->getJson('/api/piala')->json('data');
        $this->assertSame($cup->champion_id, Fighter::findByUsername($d['cup']['champion']['username'])->id);
        $this->assertNotNull($d['bracket'][3]['matches'][0]['fight_id']);

        // Minggu berikutnya: piala baru, riwayat juara tampil.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 01:00', 'UTC'));
        $d = $this->getJson('/api/piala')->json('data');
        $this->assertSame('2026-11-09', $d['cup']['week']);
        $this->assertSame('open', $d['cup']['status']);
        $this->assertCount(1, $d['history']);
    }

    public function test_cup_cancelled_with_too_few_entrants(): void
    {
        $this->player('a1');
        $this->player('a2');
        $this->travelTo(CarbonImmutable::parse('2026-11-05 05:01', 'UTC'));
        app(Piala::class)->tick();
        $this->assertSame('cancelled', Cup::sole()->status);
    }

    public function test_sasana_choice_lock_and_war(): void
    {
        $me = $this->loginAs($this->player('andi'));
        $this->player('budi');
        $this->assertCount(4, $this->getJson('/api/sasana')->json('data'));

        $this->putJson('/api/me/sasana', ['slug' => 'naga'])->assertOk()->assertJsonPath('data.sasana.slug', 'naga');
        $this->putJson('/api/me/sasana', ['slug' => 'elang'])->assertStatus(409);
        $this->putJson('/api/me/sasana', ['slug' => 'kuda'])->assertStatus(422);
        $this->getJson('/api/me/profile')->assertJsonPath('data.sasana.slug', 'naga')->assertJsonPath('data.sasanaLocked', true);
        $this->getJson('/api/fighters/andi')->assertJsonPath('data.sasana.slug', 'naga');

        app(FightNight::class)->runNow(CarbonImmutable::parse('2026-11-02 05:00', 'UTC'));
        $naga = collect($this->getJson('/api/sasana/war')->assertOk()->json('data.rows'))->firstWhere('slug', 'naga');
        $this->assertSame(1, $naga['members']);
        $rec = \App\Models\FighterRecord::where('fighter_id', $me->id)->first();
        $this->assertSame($rec->w * 3 + $rec->d + $rec->ko, $naga['points']);

        // Season baru: boleh pindah.
        app(Seasons::class)->close();
        $this->putJson('/api/me/sasana', ['slug' => 'elang'])->assertOk();
    }
}
