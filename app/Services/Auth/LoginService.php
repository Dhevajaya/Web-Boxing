<?php

namespace App\Services\Auth;

use App\Models\Fighter;
use App\Models\LoginRequest;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Semua jalur masuk pemain. Rahasia (ref, token, kode) hanya dikembalikan
 * sekali saat dibuat dan disimpan di database sebagai HMAC.
 *
 *  ref     : browser membuka ig.me/m/<akun>?ref=<ref>; webhook membawa ref + IGSID
 *  code    : browser menampilkan kode, pemain mengirimnya lewat DM
 *  dm_link : pemain mengirim kata kunci, balasan DM berisi link /masuk/<token>
 *  manual  : admin menyetujui kode (tanpa Instagram API)
 *  dev     : hanya APP_ENV=local
 */
class LoginService
{
    /** Huruf/angka yang tidak mudah tertukar (tanpa 0/O, 1/I/L). */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const SESSION_KEY = 'login_pending';

    public function __construct(
        private readonly GameConfig $game,
        private readonly FighterActivator $activator,
        private readonly PlayerAuth $auth,
    ) {}

    // ---------------------------------------------------------------- mulai

    /** @return array{request: LoginRequest, ref: string, url: string} */
    public function startRef(Request $request): array
    {
        $ref = 'L'.Str::random(31); // hanya huruf/angka: aman untuk parameter ref ig.me
        $lr = $this->create($request, 'ref', [
            'ref_hash' => LoginRequest::hash($ref),
        ], $this->game->get('auth.ref_ttl_min'));

        return ['request' => $lr, 'ref' => $ref, 'url' => $this->dmUrl($ref)];
    }

    /** @return array{request: LoginRequest, code: string} */
    public function startCode(Request $request, string $username): array
    {
        $code = $this->randomCode();
        $lr = $this->create($request, 'code', [
            'code_hash' => LoginRequest::hash($code),
            'requested_username' => $username,
        ], $this->game->get('auth.code_ttl_min'));

        return ['request' => $lr, 'code' => self::formatCode($code)];
    }

    /** Token link masuk untuk dikirim lewat balasan DM. Dipanggil oleh pemroses webhook. */
    public function issueLinkToken(Fighter $fighter): string
    {
        $token = bin2hex(random_bytes(32));
        LoginRequest::create([
            'method' => 'dm_link',
            'token_hash' => LoginRequest::hash($token),
            'ig_scoped_id' => $fighter->ig_scoped_id,
            'fighter_id' => $fighter->id,
            'requested_username' => $fighter->instagram_username,
            'status' => 'ok',
            'verified_at' => now(),
            'expires_at' => now()->addMinutes((int) $this->game->get('auth.link_ttl_min')),
        ]);

        return $token;
    }

    // ------------------------------------------------------ dari webhook IG

    /** Event webhook membawa ref milik sebuah permintaan. */
    public function resolveRef(string $ref, string $status, ?Fighter $fighter = null, ?string $igsid = null): ?LoginRequest
    {
        $lr = LoginRequest::where('ref_hash', LoginRequest::hash($ref))->first();

        return $lr ? $this->resolve($lr, $status, $fighter, $igsid) : null;
    }

    /**
     * Pesan DM berisi kode. Username pengirim (dari Profile API) harus sama dengan
     * username yang diketik di browser, kalau tidak siapa pun bisa mengklaim nama orang.
     *
     * @return array{0: ?LoginRequest, 1: string} [permintaan, hasil: ok|mismatch|not_found]
     */
    public function resolveCode(string $code, string $senderUsername, string $status, ?Fighter $fighter, string $igsid): array
    {
        $lr = LoginRequest::where('code_hash', LoginRequest::hash(self::normalizeCode($code)))
            ->where('method', 'code')->where('status', 'pending')->where('expires_at', '>', now())
            ->latest()->first();
        if (! $lr) {
            return [null, 'not_found'];
        }

        if ($lr->requested_username !== Fighter::normalizeUsername($senderUsername)) {
            $lr->increment('attempt_count');
            if ($lr->attempt_count >= (int) $this->game->get('auth.code_attempts')) {
                $lr->forceFill(['status' => 'rejected'])->save();
            }

            return [$lr, 'mismatch'];
        }

        $this->resolve($lr, $status, $fighter, $igsid);

        return [$lr, 'ok'];
    }

    // ---------------------------------------------------------- manual/dev

    /** Admin menyetujui kode tanpa Instagram API. Petarung belum terverifikasi. */
    public function approveManually(string $code): ?LoginRequest
    {
        $lr = LoginRequest::where('code_hash', LoginRequest::hash(self::normalizeCode($code)))
            ->where('method', 'code')->where('status', 'pending')->where('expires_at', '>', now())
            ->latest()->first();
        if (! $lr) {
            return null;
        }

        $fighter = $this->activator->activateUnverified($lr->requested_username, 'manual');

        return $this->resolve($lr, 'ok', $fighter, null, 'manual');
    }

    public function devLoginAllowed(): bool
    {
        return app()->environment('local') && $this->game->feature('dev_login');
    }

    public function devLogin(Request $request, string $username): Fighter
    {
        abort_unless($this->devLoginAllowed(), 404);
        $fighter = $this->activator->activateUnverified($username, 'dev');
        $this->auth->login($request, $fighter, 'dev');

        return $fighter;
    }

    // -------------------------------------------------- browser menyelesaikan

    /**
     * Browser menanyakan status permintaannya. Hanya browser yang membuat
     * permintaan (tercatat di sesinya) yang boleh menanyakan dan masuk.
     *
     * @return array{status: string, username?: string}|null null = bukan milik sesi ini
     */
    public function poll(Request $request, string $id): ?array
    {
        if (! in_array($id, (array) $request->session()->get(self::SESSION_KEY, []), true)) {
            return null;
        }

        $lr = LoginRequest::find($id);
        if (! $lr) {
            return null;
        }

        if ($lr->status === 'pending' && $lr->isExpired()) {
            $lr->forceFill(['status' => 'expired'])->save();
        }

        if ($lr->status !== 'ok') {
            return ['status' => $lr->status];
        }

        // Tandai terpakai secara atomik agar dua tab tidak masuk dengan permintaan yang sama.
        $claimed = LoginRequest::whereKey($lr->id)->where('status', 'ok')->whereNull('used_at')
            ->update(['status' => 'used', 'used_at' => now()]);
        if (! $claimed) {
            return ['status' => 'used'];
        }

        $fighter = Fighter::find($lr->fighter_id);
        if (! $fighter || $fighter->status !== 'active') {
            return ['status' => 'rejected'];
        }

        $this->forget($request, $id);
        $this->auth->login($request, $fighter, $lr->method);

        return ['status' => 'ok', 'username' => $fighter->instagram_username];
    }

    /** Link dari balasan DM dibuka. */
    public function consumeToken(Request $request, string $token): ?Fighter
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return DB::transaction(function () use ($request, $token) {
            $lr = LoginRequest::where('token_hash', LoginRequest::hash($token))->lockForUpdate()->first();
            if (! $lr || $lr->status !== 'ok' || $lr->used_at || $lr->isExpired()) {
                return null;
            }
            $fighter = Fighter::find($lr->fighter_id);
            if (! $fighter || $fighter->status !== 'active') {
                return null;
            }

            $lr->forceFill(['status' => 'used', 'used_at' => now()])->save();
            $this->auth->login($request, $fighter, 'dm_link');

            return $fighter;
        });
    }

    // ---------------------------------------------------------------- bantu

    public function dmUrl(?string $ref = null): string
    {
        $account = $this->game->get('brand.instagram');

        return 'https://ig.me/m/'.rawurlencode($account).($ref ? '?ref='.$ref : '');
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function formatCode(string $code): string
    {
        $c = self::normalizeCode($code);

        return substr($c, 0, 4).'-'.substr($c, 4, 4);
    }

    /** Cari kode dalam teks DM, misalnya "ini kodeku: abcd-efgh". */
    public static function findCode(string $text): ?string
    {
        $alpha = preg_quote(self::CODE_ALPHABET, '/');
        if (preg_match('/(?<![A-Z0-9])(['.$alpha.']{4})[\s-]?(['.$alpha.']{4})(?![A-Z0-9])/', strtoupper($text), $m)) {
            return $m[1].$m[2];
        }

        return null;
    }

    private function randomCode(): string
    {
        $out = '';
        $n = strlen(self::CODE_ALPHABET);
        for ($i = 0; $i < 8; $i++) {
            $out .= self::CODE_ALPHABET[random_int(0, $n - 1)];
        }

        return $out;
    }

    private function create(Request $request, string $method, array $attrs, int $ttlMinutes): LoginRequest
    {
        $lr = LoginRequest::create($attrs + [
            'method' => $method,
            'status' => 'pending',
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        // Simpan maksimal 5 permintaan terakhir di sesi browser ini.
        $ids = array_slice([...(array) $request->session()->get(self::SESSION_KEY, []), $lr->id], -5);
        $request->session()->put(self::SESSION_KEY, $ids);

        return $lr;
    }

    private function forget(Request $request, string $id): void
    {
        $ids = array_values(array_diff((array) $request->session()->get(self::SESSION_KEY, []), [$id]));
        $request->session()->put(self::SESSION_KEY, $ids);
    }

    private function resolve(LoginRequest $lr, string $status, ?Fighter $fighter, ?string $igsid, ?string $method = null): LoginRequest
    {
        if ($lr->status !== 'pending' || $lr->isExpired()) {
            return $lr;
        }

        $lr->forceFill(array_filter([
            'status' => $status,
            'fighter_id' => $fighter?->id,
            'ig_scoped_id' => $igsid,
            'verified_at' => $status === 'ok' ? now() : null,
            'method' => $method,
        ], fn ($v) => $v !== null))->save();

        return $lr;
    }
}
