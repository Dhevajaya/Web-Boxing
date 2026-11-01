<?php

namespace Tests\Feature;

use App\Models\Fighter;
use App\Models\IgEvent;
use App\Models\IgOutbox;
use App\Models\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramLoginTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-aplikasi';

    /** IGSID => [username, follows] yang "dikembalikan" Profile API palsu. */
    private array $profiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'game.brand.instagram' => 'akunresmi',
            'game.brand.keyword' => 'GELUD',
            'game.instagram.app_secret' => self::SECRET,
            'game.instagram.access_token' => 'token-uji',
            'game.instagram.account_id' => '17840000000000000',
            'game.instagram.webhook_verify_token' => 'verif-123',
            'game.features.require_follow' => true,
        ]);

        Http::fake(function (HttpRequest $req) {
            if ($req->method() === 'GET' && preg_match('#graph\.instagram\.com/[^/]+/(\d+)#', $req->url(), $m)) {
                $p = $this->profiles[$m[1]] ?? null;

                return $p
                    ? Http::response(['username' => $p[0], 'is_user_follow_business' => $p[1], 'id' => $m[1]])
                    : Http::response(['error' => ['message' => 'User consent is required']], 400);
            }

            return Http::response(['recipient_id' => '1', 'message_id' => 'm']);
        });
    }

    // ------------------------------------------------------------ bantuan

    private function webhook(array $messaging): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['object' => 'instagram', 'entry' => [['id' => '17840000000000000', 'time' => 1, 'messaging' => [$messaging]]]]);

        $res = $this->call('POST', '/api/webhooks/instagram', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);

        $this->artisan('ig:process')->assertSuccessful();

        return $res;
    }

    private function dm(string $igsid, string $text, ?string $mid = null): array
    {
        return ['sender' => ['id' => $igsid], 'recipient' => ['id' => '17840000000000000'], 'timestamp' => 1,
            'message' => ['mid' => $mid ?? uniqid('m'), 'text' => $text]];
    }

    private function lastLinkToken(): string
    {
        $text = IgOutbox::where('kind', 'login_link')->latest('id')->value('body')['text'];
        preg_match('#/masuk/([a-f0-9]{64})#', $text, $m);

        return $m[1];
    }

    // ------------------------------------------------------------ webhook

    public function test_webhook_verification(): void
    {
        $this->get('/api/webhooks/instagram?hub.mode=subscribe&hub.verify_token=verif-123&hub.challenge=4242')
            ->assertOk()->assertSee('4242');
        $this->get('/api/webhooks/instagram?hub.mode=subscribe&hub.verify_token=salah&hub.challenge=4242')
            ->assertForbidden();
    }

    public function test_webhook_rejects_bad_signature_and_dedupes(): void
    {
        $body = json_encode(['object' => 'instagram', 'entry' => []]);
        $this->call('POST', '/api/webhooks/instagram', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=palsu',
        ], $body)->assertForbidden();

        $this->profiles['111'] = ['budi', true];
        $msg = $this->dm('111', 'halo', 'mid-sama');
        $this->webhook($msg)->assertOk();
        $this->webhook($msg)->assertOk();
        $this->assertSame(1, IgEvent::count());
    }

    // ------------------------------------------------------- jalur link DM

    public function test_keyword_dm_creates_fighter_and_link_logs_in_once(): void
    {
        $this->profiles['111'] = ['Budi_99', true];
        $this->webhook($this->dm('111', 'gelud'))->assertOk();

        $f = Fighter::where('instagram_username', 'budi_99')->firstOrFail();
        $this->assertSame('111', $f->ig_scoped_id);
        $this->assertNotNull($f->verified_at);

        $token = $this->lastLinkToken();
        $this->get('/masuk/'.$token)->assertRedirect('/budi_99?selamat=1');
        $this->assertAuthenticatedAs($f, 'fighter');
        $this->getJson('/api/me')->assertJsonPath('data.username', 'budi_99');

        // Sekali pakai.
        $this->post('/api/me/logout')->assertOk();
        $this->assertGuest('fighter');
        $this->get('/masuk/'.$token)->assertRedirect('/?masuk=kedaluwarsa');
        $this->assertGuest('fighter');
    }

    public function test_expired_link_is_rejected(): void
    {
        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', 'GELUD'));
        $token = $this->lastLinkToken();

        $this->travel(config('game.auth.link_ttl_min') + 1)->minutes();
        $this->get('/masuk/'.$token)->assertRedirect('/?masuk=kedaluwarsa');
        $this->assertGuest('fighter');
    }

    public function test_not_follower_is_not_activated(): void
    {
        $this->profiles['222'] = ['siti', false];
        $this->webhook($this->dm('222', 'GELUD'));

        $this->assertNull(Fighter::where('instagram_username', 'siti')->first());
        $this->assertSame('not_follower', IgOutbox::latest('id')->value('kind'));
    }

    public function test_plain_chat_is_ignored(): void
    {
        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', 'kapan fight night?'));

        $this->assertSame(0, Fighter::count());
        $this->assertSame(0, IgOutbox::count());
    }

    // ---------------------------------------------------------- jalur kode

    public function test_code_login_matches_username(): void
    {
        $start = $this->postJson('/api/auth/code/start', ['username' => '@Budi'])->assertCreated();
        $id = $start->json('data.id');
        $code = $start->json('data.code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);

        $this->getJson("/api/auth/status/{$id}")->assertJsonPath('data.status', 'pending');

        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', "ini kodeku {$code}"));

        $this->getJson("/api/auth/status/{$id}")
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.username', 'budi');
        $this->assertAuthenticated('fighter');

        // Tidak bisa dipakai dua kali.
        $this->getJson("/api/auth/status/{$id}")->assertNotFound();
    }

    public function test_code_from_another_account_is_refused(): void
    {
        $start = $this->postJson('/api/auth/code/start', ['username' => 'budi'])->assertCreated();
        $id = $start->json('data.id');

        // Penyerang mengirim kode itu dari akunnya sendiri.
        $this->profiles['999'] = ['penyusup', true];
        $this->webhook($this->dm('999', $start->json('data.code')));

        $this->getJson("/api/auth/status/{$id}")->assertJsonPath('data.status', 'pending');
        $this->assertGuest('fighter');
        $this->assertNull(Fighter::where('instagram_username', 'budi')->first());
    }

    public function test_status_only_for_the_browser_that_started_it(): void
    {
        $id = $this->postJson('/api/auth/code/start', ['username' => 'budi'])->json('data.id');
        $this->flushSession();
        $this->getJson("/api/auth/status/{$id}")->assertNotFound();
    }

    public function test_code_helpers(): void
    {
        $this->assertSame('ABCDEFGH', LoginService::findCode('kode: abcd-efgh ya'));
        $this->assertSame('ABCDEFGH', LoginService::findCode('ABCD EFGH'));
        $this->assertNull(LoginService::findCode('GELUD'));
        $this->assertSame('ABCD-EFGH', LoginService::formatCode('abcdefgh'));
    }

    // ----------------------------------------------------- jalur ref-link

    public function test_ref_link_one_tap(): void
    {
        $start = $this->postJson('/api/auth/ref/start')->assertCreated();
        $id = $start->json('data.id');
        $this->assertStringStartsWith('https://ig.me/m/akunresmi?ref=', $start->json('data.url'));
        $ref = explode('ref=', $start->json('data.url'))[1];

        $this->profiles['333'] = ['andi', true];
        $this->webhook(['sender' => ['id' => '333'], 'recipient' => ['id' => '17840000000000000'], 'timestamp' => 1,
            'postback' => ['mid' => 'pb1', 'title' => 'GELUD', 'payload' => 'GELUD', 'referral' => ['ref' => $ref, 'source' => 'SHORTLINKS', 'type' => 'OPEN_THREAD']]]);

        $this->getJson("/api/auth/status/{$id}")->assertJsonPath('data.status', 'ok')->assertJsonPath('data.username', 'andi');
        $this->assertAuthenticated('fighter');
    }

    public function test_ref_without_consent_is_unreadable(): void
    {
        $start = $this->postJson('/api/auth/ref/start')->assertCreated();
        $ref = explode('ref=', $start->json('data.url'))[1];

        // Profil 444 tidak ada di API palsu = belum ada persetujuan membaca profil.
        $this->webhook(['sender' => ['id' => '444'], 'recipient' => ['id' => '17840000000000000'], 'timestamp' => 1,
            'referral' => ['ref' => $ref, 'source' => 'SHORTLINKS', 'type' => 'OPEN_THREAD']]);

        $this->getJson('/api/auth/status/'.$start->json('data.id'))->assertJsonPath('data.status', 'unreadable');
        $this->assertSame(0, Fighter::count());
    }

    // --------------------------------------------------- identitas berubah

    public function test_username_change_follows_igsid(): void
    {
        $this->profiles['111'] = ['lama', true];
        $this->webhook($this->dm('111', 'GELUD'));
        $id = Fighter::where('instagram_username', 'lama')->value('id');

        app(\App\Services\Instagram\InstagramClient::class)->forgetProfile('111');
        $this->profiles['111'] = ['baru', true];
        $this->webhook($this->dm('111', 'GELUD'));

        $this->assertSame('baru', Fighter::find($id)->instagram_username);
        $this->assertSame(1, Fighter::count());
        $this->assertDatabaseHas('fighter_username_history', ['fighter_id' => $id, 'old_username' => 'lama']);
    }

    public function test_verified_owner_wins_over_unverified_claim(): void
    {
        // Seseorang masuk manual sebagai "budi" tanpa bukti Instagram.
        $fake = Fighter::create(['instagram_username' => 'budi', 'status' => 'active']);

        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', 'GELUD'));

        // Baris yang sama kini terikat ke IGSID pemilik asli.
        $this->assertSame('111', $fake->fresh()->ig_scoped_id);
    }

    // ------------------------------------------------------- manual & dev

    public function test_manual_approval_command(): void
    {
        $start = $this->postJson('/api/auth/code/start', ['username' => 'rina'])->assertCreated();

        $this->artisan('login:approve', ['code' => $start->json('data.code')])->assertSuccessful();
        $this->getJson('/api/auth/status/'.$start->json('data.id'))->assertJsonPath('data.status', 'ok');

        $f = Fighter::where('instagram_username', 'rina')->firstOrFail();
        $this->assertNull($f->verified_at);
        $this->artisan('login:approve', ['code' => $start->json('data.code')])->assertFailed();
    }

    public function test_dev_login_only_in_local(): void
    {
        config(['game.features.dev_login' => true]);
        $this->postJson('/api/auth/dev', ['username' => 'tes'])->assertNotFound(); // APP_ENV=testing

        $this->app['env'] = 'local';
        $this->postJson('/api/auth/dev', ['username' => 'tes'])->assertOk();
        $this->assertAuthenticated('fighter');
    }

    // --------------------------------------------------------------- sesi

    public function test_suspension_ends_session(): void
    {
        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', 'GELUD'));
        $this->get('/masuk/'.$this->lastLinkToken());
        $this->getJson('/api/me')->assertJsonPath('data.username', 'budi');

        Fighter::where('instagram_username', 'budi')->update(['status' => 'suspended']);
        $this->getJson('/api/me')->assertJsonPath('data', null);
    }

    public function test_logout_everywhere_revokes_all_sessions(): void
    {
        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', 'GELUD'));
        $this->get('/masuk/'.$this->lastLinkToken());
        $f = Fighter::where('instagram_username', 'budi')->first();
        \App\Models\PlayerSession::create(['fighter_id' => $f->id, 'session_id_hash' => str_repeat('a', 64)]); // perangkat lain

        $this->postJson('/api/me/logout', ['everywhere' => true])->assertOk();
        $this->assertSame(0, \App\Models\PlayerSession::whereNull('revoked_at')->count());
        $this->assertGuest('fighter');
    }

    public function test_options_and_rate_limit(): void
    {
        $this->getJson('/api/auth/options')
            ->assertJsonPath('data.instagram_live', true)
            ->assertJsonPath('data.ref_login', true)
            ->assertJsonPath('data.dev_login', false);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/code/start', ['username' => 'spam'])->assertCreated();
        }
        $this->postJson('/api/auth/code/start', ['username' => 'spam'])->assertStatus(429);
    }

    public function test_outbox_sends_through_graph_api(): void
    {
        $this->profiles['111'] = ['budi', true];
        $this->webhook($this->dm('111', 'GELUD'));

        $this->artisan('ig:send')->assertSuccessful();
        $this->assertSame(0, IgOutbox::where('status', 'queued')->count());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/17840000000000000/messages')
            && $r['recipient']['id'] === '111'
            && $r->hasHeader('Authorization', 'Bearer token-uji'));
    }
}
