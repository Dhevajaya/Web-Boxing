<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Services\Auth\LoginService;
use App\Services\Auth\PlayerAuth;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramProcessor;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly LoginService $login) {}

    /** Apa saja jalur masuk yang tersedia sekarang. */
    public function options(GameConfig $game, InstagramClient $ig, InstagramProcessor $processor)
    {
        $live = $ig->configured();

        return $this->ok([
            'instagram' => $game->get('brand.instagram'),
            'keyword' => $game->get('brand.keyword'),
            'dm_url' => $this->login->dmUrl(),
            'instagram_live' => $live,                               // webhook + API sudah disetel
            'ref_login' => $live && $game->feature('ref_login'),
            'require_follow' => $game->feature('require_follow'),
            'dm_busy' => $live && $processor->dmBusy(),
            'dev_login' => $this->login->devLoginAllowed(),
            'link_ttl_min' => (int) $game->get('auth.link_ttl_min'),
            'code_ttl_min' => (int) $game->get('auth.code_ttl_min'),
        ]);
    }

    public function startRef(Request $request, GameConfig $game, InstagramClient $ig)
    {
        if (! $ig->configured() || ! $game->feature('ref_login')) {
            return $this->fail('unavailable', 'Masuk sekali ketuk belum tersedia.', 503);
        }

        $r = $this->login->startRef($request);

        return $this->ok([
            'id' => $r['request']->id,
            'url' => $r['url'],
            'expires_at' => $r['request']->expires_at->toIso8601String(),
        ], 201);
    }

    public function startCode(Request $request)
    {
        $username = Fighter::normalizeUsername($request->input('username'));
        if (! $username) {
            return $this->fail('invalid_username', 'Username Instagram tidak valid.');
        }

        $r = $this->login->startCode($request, $username);

        return $this->ok([
            'id' => $r['request']->id,
            'code' => $r['code'],
            'username' => $username,
            'dm_url' => $this->login->dmUrl(),
            'expires_at' => $r['request']->expires_at->toIso8601String(),
        ], 201);
    }

    public function status(Request $request, string $id)
    {
        $res = $this->login->poll($request, $id);
        if ($res === null) {
            return $this->fail('not_found', 'Permintaan masuk tidak ditemukan di browser ini.', 404);
        }

        return $this->ok($res);
    }

    public function dev(Request $request)
    {
        if (! $this->login->devLoginAllowed()) {
            return $this->fail('not_found', 'Tidak tersedia.', 404);
        }
        $username = Fighter::normalizeUsername($request->input('username'));
        if (! $username) {
            return $this->fail('invalid_username', 'Username Instagram tidak valid.');
        }

        $fighter = $this->login->devLogin($request, $username);

        return $this->ok(['status' => 'ok', 'username' => $fighter->instagram_username]);
    }

    /** GET /masuk/{token}: link dari balasan DM. */
    public function consume(Request $request, string $token)
    {
        $fighter = $this->login->consumeToken($request, $token);

        return $fighter
            ? redirect('/'.$fighter->instagram_username.'?selamat=1')
            : redirect('/?masuk=kedaluwarsa');
    }

    public function me()
    {
        /** @var Fighter|null $f */
        $f = Auth::guard('fighter')->user();

        return $this->ok($f ? [
            'username' => $f->instagram_username,
            'verified' => (bool) $f->verified_at,
            'npc' => $f->is_npc,
        ] : null)->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request, PlayerAuth $auth)
    {
        $auth->logout($request, $request->boolean('everywhere'));

        // Sesi diganti saat keluar: kirim token CSRF baru agar halaman tidak perlu dimuat ulang.
        return $this->ok(['status' => 'ok', 'csrf' => csrf_token()]);
    }
}
