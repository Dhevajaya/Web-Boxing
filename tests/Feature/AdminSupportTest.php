<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\ChatMessage;
use App\Models\Fighter;
use App\Models\FightNightRun;
use App\Models\LoginRequest;
use App\Models\PlayerSession;
use App\Models\Season;
use App\Models\Setting;
use App\Support\GameConfig;
use App\Support\Totp;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesFighters;
use Tests\TestCase;

class AdminSupportTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GameSeeder::class);
    }

    private function admin(string $role = 'owner', array $attrs = []): Admin
    {
        return Admin::create($attrs + ['name' => $role, 'email' => "{$role}@contoh.id", 'password' => 'rahasia-panjang-1', 'role' => $role]);
    }

    public function test_login_password_totp_and_throttle(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Masuk admin');

        $this->admin();
        $this->post('/admin/login', ['email' => 'owner@contoh.id', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->post('/admin/login', ['email' => 'OWNER@contoh.id', 'password' => 'rahasia-panjang-1'])->assertRedirect('/admin');
        $this->assertAuthenticated('admin');
        $this->get('/admin')->assertOk()->assertSee('Dasbor')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post('/admin/logout');
        $this->assertGuest('admin');

        // TOTP
        $secret = Totp::secret();
        Admin::first()->forceFill(['totp_secret' => $secret])->save();
        $this->post('/admin/login', ['email' => 'owner@contoh.id', 'password' => 'rahasia-panjang-1', 'otp' => '000000'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->post('/admin/login', ['email' => 'owner@contoh.id', 'password' => 'rahasia-panjang-1', 'otp' => Totp::code($secret)])->assertRedirect('/admin');
        $this->assertAuthenticated('admin');
        $this->post('/admin/logout');

        // Batas 5 percobaan.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'owner@contoh.id', 'password' => 'x']);
        }
        $this->post('/admin/login', ['email' => 'owner@contoh.id', 'password' => 'rahasia-panjang-1', 'otp' => Totp::code($secret)])
            ->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_roles_limit_pages(): void
    {
        $this->actingAs($this->admin('editor'), 'admin');
        $this->get('/admin/pengumuman')->assertOk();
        $this->get('/admin/petarung')->assertForbidden();
        $this->get('/admin/pengaturan')->assertForbidden();

        $this->actingAs($this->admin('moderator'), 'admin');
        $this->get('/admin/petarung')->assertOk();
        $this->get('/admin/chat')->assertOk();
        $this->get('/admin/login-manual')->assertOk();
        $this->get('/admin/operasi')->assertForbidden();

        $this->actingAs($this->admin('owner'), 'admin');
        foreach (['/admin', '/admin/operasi', '/admin/pengaturan', '/admin/admin', '/admin/audit', '/admin/dukung', '/admin/akun', '/admin/pengumuman/baru'] as $p) {
            $this->get($p)->assertOk();
        }
    }

    public function test_fighter_moderation_actions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $f = $this->player('budi', ['nick' => 'Nama Jelek', 'look' => ['skin' => 'gelap']]);
        PlayerSession::create(['fighter_id' => $f->id, 'session_id_hash' => str_repeat('c', 64)]);

        $this->get('/admin/petarung?q=bud')->assertOk()->assertSee('@budi');
        $this->get("/admin/petarung/{$f->id}")->assertOk()->assertSee('Tindakan');

        $this->post("/admin/petarung/{$f->id}", ['action' => 'suspend'])->assertSessionHas('ok');
        $this->assertSame('suspended', $f->refresh()->status);
        $this->assertSame(0, PlayerSession::whereNull('revoked_at')->count());
        $this->post("/admin/petarung/{$f->id}", ['action' => 'unsuspend']);
        $this->post("/admin/petarung/{$f->id}", ['action' => 'reset_identity']);
        $this->post("/admin/petarung/{$f->id}", ['action' => 'reset_look']);
        $this->post("/admin/petarung/{$f->id}", ['action' => 'mute', 'hours' => 5]);
        $this->post("/admin/petarung/{$f->id}", ['action' => 'xp', 'amount' => 120]);
        $this->post("/admin/petarung/{$f->id}", ['action' => 'xp', 'amount' => -20]);
        $this->post("/admin/petarung/{$f->id}", ['action' => 'supporter', 'tier' => 'vip']);
        $f->refresh();
        $this->assertSame(['active', null, null, null, 100, 'vip'], [$f->status, $f->nick, $f->look, null, (int) $f->xp, $f->supporter_tier]);
        $this->assertSame(2, (int) $f->level); // 100 XP: >= 50 -> Lv2, sisa 50 < 75
        $this->assertTrue($f->muted_until->isFuture());
        $this->assertTrue(Badge::where('fighter_id', $f->id)->where('code', 'pendukung:vip')->exists());
        $this->assertGreaterThanOrEqual(8, AuditLog::where('actor_type', 'admin')->count());
    }

    public function test_manual_login_announcement_chat_settings_and_ops(): void
    {
        $this->actingAs($this->admin(), 'admin');

        // Login manual: pemain minta kode, admin menyetujui dari kode di DM.
        $code = $this->postJson('/api/auth/code/start', ['username' => 'citra'])->json('data.code');
        $this->post('/admin/login-manual', ['code' => 'SALAH-123'])->assertSessionHasErrors('code');
        $this->post('/admin/login-manual', ['code' => strtolower($code)])->assertSessionHas('ok');
        $this->assertSame('ok', LoginRequest::first()->status);
        $this->assertTrue(Fighter::where('instagram_username', 'citra')->exists());

        // Pengumuman: buat, tampil di mading, jadwal, hapus.
        $this->post('/admin/pengumuman/baru', ['title' => 'Halo', 'body' => 'Isi', 'publish' => 'now', 'pinned' => '1'])->assertRedirect('/admin/pengumuman');
        $this->getJson('/api/mading')->assertJsonPath('data.news.0.title', 'Halo');
        $a = Announcement::first();
        $this->post("/admin/pengumuman/{$a->id}", ['title' => 'Nanti', 'body' => 'Isi', 'publish' => 'at', 'publish_at' => now()->addDays(2)->format('Y-m-d H:i')]);
        $this->getJson('/api/mading')->assertJsonCount(0, 'data.news');
        $this->post("/admin/pengumuman/{$a->id}/hapus");
        $this->assertSame(0, Announcement::count());

        // Chat
        $f = $this->player('dodi');
        $m = ChatMessage::create(['room' => 'alun', 'fighter_id' => $f->id, 'body' => 'spam', 'created_at' => now()]);
        $this->get('/admin/chat')->assertOk()->assertSee('spam');
        $this->post("/admin/chat/{$m->id}", ['action' => 'mute']);
        $this->assertNotNull($m->refresh()->hidden_at);
        $this->assertTrue($f->refresh()->muted_until->isFuture());

        // Pengaturan: override, nilai tak valid, reset.
        $this->post('/admin/pengaturan', ['xp__win' => '15', 'fight_night__hours' => '20, 8', 'brand__name' => 'Ring Kita', 'features__piala' => '0'])->assertSessionHas('ok');
        $g = app(GameConfig::class);
        $g->flush();
        $this->assertSame(15, $g->get('xp.win'));
        $this->assertSame([8, 20], $g->get('fight_night.hours'));
        $this->assertSame('Ring Kita', $g->get('brand.name'));
        $this->assertFalse($g->feature('piala'));
        $this->post('/admin/pengaturan', ['xp__win' => 'banyak'])->assertSessionHasErrors();
        $this->post('/admin/pengaturan', ['reset__xp__win' => '1']);
        $g->flush();
        $this->assertSame(10, $g->get('xp.win'));
        $this->assertFalse(Setting::whereKey('xp.win')->exists());
        $this->get('/')->assertSee('Ring Kita');

        // Operasi: ulangi run gagal, tutup season (butuh konfirmasi).
        $run = FightNightRun::create(['slot_at' => now()->subHour(), 'status' => 'failed', 'error' => 'uji']);
        $this->post("/admin/operasi/fight-night/{$run->id}/ulang");
        $this->assertSame('preparing', $run->refresh()->status);
        $this->post('/admin/operasi/season/tutup', ['confirm' => 'ya'])->assertSessionHasErrors('confirm');
        $this->post('/admin/operasi/season/tutup', ['confirm' => 'TUTUP', 'name' => 'Season Baru'])->assertSessionHas('ok');
        $this->assertSame('Season Baru', Season::current()->name);
    }

    public function test_admin_management_and_totp_setup(): void
    {
        $me = $this->admin();
        $this->actingAs($me, 'admin');
        $this->post('/admin/admin', ['name' => 'Mod', 'email' => 'mod@contoh.id', 'password' => 'pendek', 'role' => 'moderator'])->assertSessionHasErrors('password');
        $this->post('/admin/admin', ['name' => 'Mod', 'email' => 'mod@contoh.id', 'password' => 'cukup-panjang-1', 'role' => 'moderator'])->assertSessionHas('ok');
        $mod = Admin::where('email', 'mod@contoh.id')->first();
        $this->post("/admin/admin/{$me->id}/hapus")->assertSessionHasErrors('admin');
        $this->post("/admin/admin/{$mod->id}/hapus")->assertSessionHas('ok');

        $this->get('/admin/akun')->assertOk();
        $secret = session('totp_pending');
        $this->post('/admin/akun', ['action' => 'totp_on', 'otp' => '123456'])->assertSessionHasErrors('otp');
        $this->post('/admin/akun', ['action' => 'totp_on', 'otp' => Totp::code($secret)])->assertSessionHas('ok');
        $this->assertSame($secret, $me->refresh()->totp_secret);
        $this->post('/admin/akun', ['action' => 'password', 'current' => 'rahasia-panjang-1', 'password' => 'password-baru-99', 'password_confirmation' => 'password-baru-99'])->assertSessionHas('ok');
    }

    public function test_saweria_webhook_and_supporter_perks(): void
    {
        config(['game.supporter.webhook_token' => 'tok-rahasia']);
        $f = $this->loginAs($this->player('budi'));

        $payload = ['id' => 'trx-1', 'amount_raw' => 15000, 'donator_name' => 'Budi', 'message' => 'semangat! @Budi'];
        $this->postJson('/api/webhooks/saweria/salah', $payload)->assertForbidden();
        $this->postJson('/api/webhooks/saweria/tok-rahasia', $payload)->assertOk()->assertJsonPath('data.matched', 'budi');
        $this->postJson('/api/webhooks/saweria/tok-rahasia', $payload)->assertOk(); // duplikat diabaikan
        $this->assertSame('tribun', $f->refresh()->supporter_tier);

        $this->postJson('/api/webhooks/saweria/tok-rahasia', ['id' => 'trx-2', 'amount_raw' => 15000, 'donator_name' => '@budi'])->assertOk();
        $this->assertSame('ringside', $f->refresh()->supporter_tier); // total 30rb
        $this->postJson('/api/webhooks/saweria/tok-rahasia', ['id' => 'trx-3', 'amount_raw' => 5000, 'message' => 'tanpa nama'])->assertJsonPath('data.matched', null);

        $d = $this->getJson('/api/dukung')->assertOk()->json('data');
        $this->assertSame(30000, $d['me']['total']);
        $this->assertSame('budi', $d['supporters'][0]['username']);
        $this->getJson('/api/fighters/budi')->assertJsonPath('data.supporter', 'ringside');

        // Item khusus pendukung.
        $this->loginAs($f->refresh());
        $this->putJson('/api/me/look', ['look' => ['gloves' => 'neon', 'hairColor' => 'emas']])->assertOk();
        $other = $this->loginAs($this->player('citra'));
        $this->putJson('/api/me/look', ['look' => ['gloves' => 'neon']])->assertStatus(422)->assertJsonPath('error.fields.gloves', 'supporter');

        // Anonim: hilang dari daftar dan tanda ♥.
        $this->loginAs($f->refresh());
        $this->putJson('/api/me/supporter', ['anonymous' => true])->assertOk();
        $this->assertCount(0, $this->getJson('/api/dukung')->json('data.supporters'));
        $this->getJson('/api/fighters/budi')->assertJsonPath('data.supporter', null);
        unset($other);
    }
}
