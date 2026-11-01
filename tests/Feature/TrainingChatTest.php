<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Fight;
use App\Models\Fighter;
use App\Services\Game\Challenges;
use App\Services\Game\StatBonus;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\MakesFighters;
use Tests\TestCase;

class TrainingChatTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 03:00:00', 'UTC')); // Senin 10:00 WIB
        $this->seed(GameSeeder::class);
    }

    private function play(string $game, int $score, int $taps, int $wait = 31): \Illuminate\Testing\TestResponse
    {
        $token = $this->postJson('/api/training/start', ['game' => $game])->assertCreated()->json('data.token');
        $this->travel($wait)->seconds();

        return $this->postJson('/api/training/finish', ['token' => $token, 'score' => $score, 'taps' => $taps]);
    }

    public function test_training_rewards_caps_and_anticheat(): void
    {
        $me = $this->loginAs($this->player('andi'));
        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.today.samsak.best', 0);

        // Samsak: 200 ketukan = 20 XP (cap), bonus +2 (>=120, >=180).
        $this->play('samsak', 200, 200)->assertOk()->assertJsonPath('data.xp', 20)->assertJsonPath('data.bonus', 2);
        // Cap XP per game per hari sudah habis; bonus terbaik tetap tercatat.
        $this->play('samsak', 250, 250)->assertOk()->assertJsonPath('data.xp', 0)->assertJsonPath('data.bonus', 3)->assertJsonPath('data.bonusToday', 3);
        $this->assertSame(20, (int) $me->refresh()->xp);

        // Anti-curang.
        $this->play('samsak', 300, 200)->assertStatus(422)->assertJsonPath('error.code', 'rejected');   // skor > ketukan
        $this->play('samsak', 460, 460)->assertStatus(422)->assertJsonPath('error.code', 'rejected');   // > max & > 15/dtk
        $this->play('hindari', 10, 10, 5)->assertStatus(422)->assertJsonPath('error.code', 'bad_timing'); // terlalu cepat
        $t = $this->postJson('/api/training/start', ['game' => 'tahan'])->json('data.token');
        $this->travel(31)->seconds();
        $this->postJson('/api/training/finish', ['token' => $t, 'score' => 2.5, 'taps' => 3])->assertStatus(422)->assertJsonPath('error.code', 'invalid');
        $this->postJson('/api/training/finish', ['token' => $t, 'score' => '20', 'taps' => 30])->assertStatus(422)->assertJsonPath('error.code', 'invalid');
        $this->postJson('/api/training/start', ['game' => 'catur'])->assertStatus(422);

        // Token sekali pakai.
        $token = $this->postJson('/api/training/start', ['game' => 'tahan'])->json('data.token');
        $this->travel(31)->seconds();
        $this->postJson('/api/training/finish', ['token' => $token, 'score' => 45, 'taps' => 60])->assertOk()->assertJsonPath('data.bonus', 2);
        $this->postJson('/api/training/finish', ['token' => $token, 'score' => 45, 'taps' => 60])->assertJsonPath('error.code', 'invalid_token');

        // Bonus berlaku untuk laga HARI INI saja (zona game).
        $bonus = app(StatBonus::class)->for([$me->id], '2026-11-02');
        $this->assertEquals(['power' => 3, 'defense' => 2], $bonus[$me->id]);
        $this->assertSame([], app(StatBonus::class)->for([$me->id], '2026-11-03'));

        // ...dan benar-benar masuk ke stat laga.
        $opp = $this->player('budi');
        $f = app(Challenges::class)->create($me, $opp);
        $this->assertInstanceOf(Fight::class, $f);
        $this->assertSame(min(99, $me->effectiveStats()['power'] + 3), (int) $f->a_power);

        $this->assertSame(3, $this->getJson('/api/training')->json('data.today.samsak.bonusToday'));
    }

    public function test_guest_cannot_start_training(): void
    {
        $this->postJson('/api/training/start', ['game' => 'samsak'])->assertUnauthorized();
        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.today', null);
    }

    public function test_chat_rules_reports_and_rooms(): void
    {
        $andi = $this->loginAs($this->player('andi'));
        $this->getJson('/api/chat/alun')->assertOk()->assertJsonCount(0, 'data.messages');

        $id = $this->postJson('/api/chat/alun', ['body' => '  Halo   ring! '])->assertCreated()->assertJsonPath('data.body', 'Halo ring!')->json('data.id');
        $this->postJson('/api/chat/alun', ['body' => 'lagi'])->assertStatus(429);      // cooldown 3 detik
        $this->travel(4)->seconds();
        $this->postJson('/api/chat/alun', ['body' => str_repeat('a', 81)])->assertStatus(422);
        $this->postJson('/api/chat/alun', ['body' => 'dasar anjing'])->assertStatus(422)->assertJsonPath('error.code', 'banned');
        $this->postJson('/api/chat/alun', ['body' => ''])->assertStatus(422);

        // Tamu boleh membaca alun-alun, tidak boleh menulis.
        $msgs = $this->getJson('/api/chat/alun')->json('data.messages');
        $this->assertSame('andi', $msgs[0]['from']['username']);
        $this->assertSame(1, count($this->getJson("/api/chat/alun?after=0")->json('data.messages')));
        $this->assertSame(0, count($this->getJson("/api/chat/alun?after={$id}")->json('data.messages')));

        // Ruang sasana hanya untuk anggota.
        $this->getJson('/api/chat/sasana:naga')->assertForbidden();
        $this->putJson('/api/me/sasana', ['slug' => 'naga'])->assertOk();
        $andi->refresh();
        $this->loginAs($andi);
        $this->postJson('/api/chat/sasana:naga', ['body' => 'Naga!'])->assertCreated();
        $this->getJson('/api/chat/sasana:kuda')->assertNotFound();

        // 3 laporan berbeda: pesan disembunyikan, penulis dibisukan 24 jam.
        $this->postJson("/api/chat/messages/{$id}/report")->assertStatus(422); // tidak bisa lapor diri sendiri
        foreach (['r1', 'r2', 'r3'] as $i => $u) {
            $this->loginAs($this->player($u));
            $this->postJson("/api/chat/messages/{$id}/report")->assertOk()->assertJsonPath('data.hidden', $i === 2);
        }
        $this->assertNotNull(ChatMessage::find($id)->hidden_at);
        $this->assertContains($id, $this->getJson("/api/chat/alun?after={$id}")->json('data.hidden'));
        $andi = Fighter::find($andi->id);
        $this->assertTrue($andi->muted_until->isFuture());
        $this->loginAs($andi);
        $this->travel(10)->seconds();
        $this->postJson('/api/chat/alun', ['body' => 'aku kembali'])->assertForbidden()->assertJsonPath('error.code', 'muted');
        $this->travel(25)->hours();
        $this->postJson('/api/chat/alun', ['body' => 'aku kembali'])->assertCreated();

        // Pembersihan.
        $this->travel(8)->days();
        $this->artisan('game:prune')->assertSuccessful();
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_spar_finder(): void
    {
        $me = $this->loginAs($this->player('andi'));
        $this->player('budi');
        $this->player('citra');
        $this->player('lama', ['last_active_at' => now()->subDays(10)]);
        $rows = $this->getJson('/api/spar')->assertOk()->json('data.rows');
        $names = collect($rows)->pluck('username')->sort()->values()->all();
        $this->assertSame(['budi', 'citra'], $names);
        // Urutan tetap sepanjang hari.
        $this->assertSame($rows, $this->getJson('/api/spar')->json('data.rows'));
        $this->assertArrayHasKey('ovr', $rows[0]);
        $this->getJson('/api/spar')->assertJsonPath('data.stars', 0);
        unset($me);
        DB::table('fighters')->count();
    }
}
