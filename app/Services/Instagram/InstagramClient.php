<?php

namespace App\Services\Instagram;

use App\Support\GameConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pembungkus Instagram API with Instagram Login (graph.instagram.com).
 * Izin: instagram_business_basic, instagram_business_manage_messages.
 */
class InstagramClient
{
    public function __construct(private readonly GameConfig $game) {}

    public function configured(): bool
    {
        return (bool) ($this->cfg('access_token') && $this->cfg('account_id') && $this->cfg('app_secret'));
    }

    public function accountId(): ?string
    {
        return $this->cfg('account_id') ? (string) $this->cfg('account_id') : null;
    }

    /**
     * Profil pengirim DM. Hanya bisa dibaca setelah orang itu mengirim pesan
     * atau mengetuk Icebreaker (persetujuan dari Meta).
     *
     * @return array{username: string, follows: bool}
     *
     * @throws ProfileUnavailable
     */
    public function profile(string $igsid): array
    {
        $cached = Cache::get("ig.profile.{$igsid}");
        if ($cached) {
            return $cached;
        }

        $res = $this->http()->retry(2, 300, throw: false)->get($this->url($igsid), [
            'fields' => 'username,is_user_follow_business',
        ]);

        if (! $res->successful() || ! $res->json('username')) {
            throw new ProfileUnavailable($res->json('error.message') ?? 'HTTP '.$res->status());
        }

        $profile = [
            'username' => strtolower((string) $res->json('username')),
            'follows' => (bool) $res->json('is_user_follow_business'),
        ];
        // Singkat saja: status follow bisa berubah begitu pemain menekan Follow.
        Cache::put("ig.profile.{$igsid}", $profile, $profile['follows'] ? 600 : 30);

        return $profile;
    }

    public function forgetProfile(string $igsid): void
    {
        Cache::forget("ig.profile.{$igsid}");
    }

    /** Kirim teks DM. Melempar RuntimeException bila Meta menolak. */
    public function sendText(string $igsid, string $text): void
    {
        $res = $this->http()->post($this->url($this->accountId().'/messages'), [
            'recipient' => ['id' => $igsid],
            'message' => ['text' => mb_substr($text, 0, 1000)],
        ]);

        if (! $res->successful()) {
            throw new RuntimeException('Kirim DM gagal: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }
    }

    /** Memeriksa X-Hub-Signature-256 dari Meta terhadap isi request mentah. */
    public function validSignature(string $rawBody, ?string $header): bool
    {
        $secret = (string) $this->cfg('app_secret');
        if ($secret === '' || ! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $header);
    }

    public function verifyToken(): ?string
    {
        return $this->cfg('webhook_verify_token');
    }

    /** Token dikirim lewat header Authorization, bukan query string, agar tidak tercatat di log. */
    private function http(): PendingRequest
    {
        return Http::timeout(8)->acceptJson()->withToken((string) $this->cfg('access_token'));
    }

    private function url(string $path): string
    {
        return 'https://graph.instagram.com/'.$this->cfg('graph_version').'/'.ltrim($path, '/');
    }

    private function cfg(string $key): mixed
    {
        // Rahasia hanya dari config/.env, tidak pernah dari tabel settings.
        return config('game.instagram.'.$key);
    }
}
