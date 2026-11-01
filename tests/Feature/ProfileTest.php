<?php

namespace Tests\Feature;

use App\Game\Looks;
use App\Models\AuditLog;
use App\Models\Fighter;
use App\Models\PlayerSession;
use App\Services\Auth\PlayerAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function login(array $attrs = []): Fighter
    {
        $f = Fighter::create(['instagram_username' => 'budi', 'status' => 'active'] + $attrs);
        $row = PlayerSession::create(['fighter_id' => $f->id, 'session_id_hash' => str_repeat('b', 64)]);
        $this->actingAs($f, 'fighter')->withSession([PlayerAuth::KEY => $row->id]);

        return $f;
    }

    public function test_guest_cannot_edit(): void
    {
        $this->getJson('/api/me/profile')->assertUnauthorized();
        $this->putJson('/api/me/look', ['look' => ['skin' => 'gelap']])->assertUnauthorized();
        $this->putJson('/api/me/identity', ['nick' => 'Si Kilat'])->assertUnauthorized();
        $this->putJson('/api/me/points', ['points' => ['power' => 1]])->assertUnauthorized();
    }

    public function test_profile_shows_defaults_and_budget(): void
    {
        $f = $this->login();

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('data.username', 'budi')
            ->assertJsonPath('data.level.lv', 1)
            ->assertJsonPath('data.pointsTotal', 10)
            ->assertJsonPath('data.pointsUsed', 0)
            ->assertJsonPath('data.lookCustom', false)
            ->assertJsonPath('data.look', app(Looks::class)->default('budi'))
            ->assertJsonPath('data.defaults.nick', $f->base()['nick']);
    }

    public function test_look_update_lock_and_reset(): void
    {
        $f = $this->login();

        $this->putJson('/api/me/look', ['look' => ['skin' => 'gelap', 'hair' => 'cepak']])
            ->assertOk()
            ->assertJsonPath('data.look.skin', 'gelap')
            ->assertJsonPath('data.lookCustom', true);
        $this->getJson('/api/fighters/budi')->assertJsonPath('data.look.hair', 'cepak');

        $this->putJson('/api/me/look', ['look' => ['gloves' => 'emas']])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_look')
            ->assertJsonPath('error.fields.gloves', 'locked');
        $this->putJson('/api/me/look', ['look' => ['sayap' => 'emas']])->assertStatus(422);
        $this->putJson('/api/me/look', ['look' => 'gelap'])->assertStatus(422);

        // Lewat instance yang sama dengan guard (di test, guard menyimpan objek petarung antar-request).
        $f->forceFill(['xp' => 100_000])->save(); // level maksimal
        $this->putJson('/api/me/look', ['look' => ['gloves' => 'emas']])->assertOk();

        $this->putJson('/api/me/look', ['look' => null])
            ->assertOk()
            ->assertJsonPath('data.lookCustom', false)
            ->assertJsonPath('data.look', app(Looks::class)->default('budi'));
    }

    public function test_unregistered_card_has_default_look(): void
    {
        $this->getJson('/api/fighters/siti')->assertJsonPath('data.look', app(Looks::class)->default('siti'));
    }

    public function test_identity_rules_and_cooldown(): void
    {
        $this->login();

        $this->putJson('/api/me/identity', ['nick' => 'Raja  Hook', 'kota' => 'Cimahi'])
            ->assertOk()
            ->assertJsonPath('data.nick', 'Raja Hook')
            ->assertJsonPath('data.kota', 'Cimahi');
        $this->getJson('/api/fighters/budi')->assertJsonPath('data.nick', 'Raja Hook dari Cimahi');
        $this->assertSame(1, AuditLog::where('action', 'identity_changed')->count());

        // Mengirim nilai yang sama bukan perubahan, jadi tidak kena jeda.
        $this->putJson('/api/me/identity', ['nick' => 'Raja Hook', 'kota' => 'Cimahi'])->assertOk();

        $this->putJson('/api/me/identity', ['nick' => 'Si Kilat', 'kota' => 'Cimahi'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'cooldown');

        $this->putJson('/api/me/identity', ['nick' => 'Si Anjing'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.nick', 'banned');
        $this->putJson('/api/me/identity', ['nick' => 'ab'])->assertJsonPath('error.fields.nick', 'too_short');
        $this->putJson('/api/me/identity', ['kota' => '@kotaku'])->assertJsonPath('error.fields.kota', 'bad_chars');
        $this->putJson('/api/me/identity', ['nick' => ['x']])->assertStatus(422)->assertJsonPath('error.code', 'invalid');

        $this->travel(25)->hours();
        $f = Fighter::where('instagram_username', 'budi')->first();
        $this->putJson('/api/me/identity', ['nick' => '', 'kota' => ''])
            ->assertOk()
            ->assertJsonPath('data.nick', null);
        $this->getJson('/api/fighters/budi')->assertJsonPath('data.nick', $f->base()['nick'].' dari '.$f->base()['kota']);
    }

    public function test_points_budget_and_respec(): void
    {
        $f = $this->login();
        $power = $f->base()['base']['power'];

        $this->putJson('/api/me/points', ['points' => ['power' => 6, 'speed' => 5]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'over_budget');
        $this->putJson('/api/me/points', ['points' => ['power' => '3']])->assertJsonPath('error.code', 'invalid');
        $this->putJson('/api/me/points', ['points' => [3, 2]])->assertJsonPath('error.code', 'invalid');

        // Pembagian pertama: bebas.
        $this->putJson('/api/me/points', ['points' => ['power' => 6, 'speed' => 4]])
            ->assertOk()
            ->assertJsonPath('data.pointsUsed', 10)
            ->assertJsonPath('data.respecNextAt', null);
        $this->getJson('/api/fighters/budi')->assertJsonPath('data.stats.power', min(99, $power + 6));

        // Memindah poin (power turun): boleh sekali, lalu terkunci 7 hari.
        $this->putJson('/api/me/points', ['points' => ['power' => 3, 'speed' => 4, 'chin' => 3]])
            ->assertOk()
            ->assertJsonPath('data.points.chin', 3);
        $this->assertNotNull(Fighter::find($f->id)->points_respec_at);

        $this->putJson('/api/me/points', ['points' => ['power' => 0, 'speed' => 4, 'chin' => 6]])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'respec_locked');

        // Naik level: poin BARU boleh ditambah kapan saja, tanpa menunggu.
        Fighter::whereKey($f->id)->update(['xp' => 50]); // Level 2 = 12 poin
        $this->putJson('/api/me/points', ['points' => ['power' => 3, 'speed' => 4, 'chin' => 5]])
            ->assertOk()
            ->assertJsonPath('data.pointsTotal', 12);

        $this->travel(8)->days();
        $this->putJson('/api/me/points', ['points' => ['power' => 0, 'speed' => 4, 'chin' => 8]])->assertOk();
        $this->assertSame(4, AuditLog::where('action', 'points_changed')->count());
    }

    public function test_profile_edit_pages_render(): void
    {
        foreach (['/profil/tampilan', '/profil/identitas', '/profil/stat'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="view"', false);
        }
        $this->get('/budi')->assertOk()->assertSee('<title>@budi · ', false);
    }
}
