<?php

namespace App\Services\Instagram;

use App\Models\Fighter;
use App\Models\IgEvent;
use App\Models\IgOutbox;
use App\Services\Auth\FighterActivator;
use App\Services\Auth\LoginService;
use App\Support\GameConfig;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengubah event webhook Instagram menjadi aksi login.
 *
 *   ingest()         dipanggil webhook: simpan event mentah (cepat, tanpa API call)
 *   processPending() dipanggil setelah respons dikirim + cron tiap menit
 *   sendOutbox()     kirim balasan DM yang mengantre, dengan batas laju
 */
class InstagramProcessor
{
    public function __construct(
        private readonly InstagramClient $ig,
        private readonly LoginService $login,
        private readonly FighterActivator $activator,
        private readonly GameConfig $game,
    ) {}

    /** @return int jumlah event baru yang disimpan */
    public function ingest(array $payload): int
    {
        if (($payload['object'] ?? null) !== 'instagram') {
            return 0;
        }

        $own = $this->ig->accountId();
        $saved = 0;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $item) {
                $sender = (string) ($item['sender']['id'] ?? '');
                if ($sender === '' || $sender === $own || ! empty($item['message']['is_echo'])) {
                    continue; // pesan yang dikirim akun kita sendiri
                }

                $type = isset($item['message']) ? 'message' : (isset($item['postback']) ? 'postback' : (isset($item['referral']) ? 'referral' : 'other'));
                $mid = $item['message']['mid'] ?? $item['postback']['mid'] ?? null;
                $key = hash('sha256', $mid ? "mid:{$mid}" : json_encode($item));

                try {
                    IgEvent::create([
                        'dedupe_key' => $key,
                        'type' => $type,
                        'ig_scoped_id' => $sender,
                        'payload' => $item,
                    ]);
                    $saved++;
                } catch (UniqueConstraintViolationException) {
                    // Meta mengirim ulang event yang sama: abaikan.
                }
            }
        }

        return $saved;
    }

    /** @return int jumlah event yang diproses */
    public function processPending(int $limit = 100): int
    {
        $lock = Cache::lock('ig:process', 55);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $n = 0;
            IgEvent::whereNull('processed_at')->orderBy('id')->limit($limit)->get()
                ->each(function (IgEvent $e) use (&$n) {
                    try {
                        $this->handle($e);
                        $e->forceFill(['processed_at' => now(), 'error' => null])->save();
                    } catch (Throwable $ex) {
                        Log::warning('Gagal memproses event Instagram', ['event' => $e->id, 'error' => $ex->getMessage()]);
                        $e->forceFill(['processed_at' => now(), 'error' => mb_substr($ex->getMessage(), 0, 250)])->save();
                    }
                    $n++;
                });

            return $n;
        } finally {
            $lock->release();
        }
    }

    public function handle(IgEvent $event): void
    {
        $item = $event->payload;
        $igsid = (string) $event->ig_scoped_id;
        $text = trim((string) ($item['message']['text'] ?? ''));
        $postback = (string) ($item['postback']['payload'] ?? '');
        $ref = $item['message']['referral']['ref']
            ?? $item['postback']['referral']['ref']
            ?? $item['referral']['ref']
            ?? null;

        $keyword = strtoupper((string) $this->game->get('brand.keyword'));
        $saysKeyword = $this->mentions($text, $keyword) || strtoupper($postback) === $keyword;
        $code = $text !== '' ? LoginService::findCode($text) : null;

        if (! $ref && ! $saysKeyword && ! $code) {
            return; // obrolan biasa: biarkan admin membalas dari aplikasi Instagram
        }

        // 1. Siapa pengirimnya? (Profile API)
        try {
            if ($saysKeyword) {
                $this->ig->forgetProfile($igsid); // mungkin baru saja menekan Follow
            }
            $profile = $this->ig->profile($igsid);
        } catch (ProfileUnavailable) {
            if ($ref) {
                $this->login->resolveRef($ref, 'unreadable');
            }
            // Ref tanpa pesan belum memberi izin membaca profil: minta pemain mengetik kata kunci.
            IgOutbox::queue($igsid, 'info', "Halo! Ketik {$keyword} di sini supaya kami bisa mengenali akunmu, lalu kembali ke browser.");

            return;
        }

        // 2. Wajib follow?
        if ($this->game->feature('require_follow') && ! $profile['follows']) {
            if ($ref) {
                $this->login->resolveRef($ref, 'not_follower');
            }
            if ($code) {
                $this->login->resolveCode($code, $profile['username'], 'not_follower', null, $igsid);
            }
            $account = $this->game->get('brand.instagram');
            IgOutbox::queue($igsid, 'not_follower', "Follow @{$account} dulu ya, lalu kirim {$keyword} lagi di sini. 🥊");

            return;
        }

        // 3. Aktifkan / temukan petarungnya.
        $fighter = $this->activator->activateVerified($igsid, $profile['username']);
        if ($fighter->status !== 'active') {
            IgOutbox::queue($igsid, 'info', 'Akunmu sedang dinonaktifkan. Hubungi admin lewat DM ini.');

            return;
        }

        $name = $this->game->get('brand.name');

        // 4a. Login sekali ketuk (link ig.me berisi ref).
        if ($ref && ($lr = $this->login->resolveRef($ref, 'ok', $fighter, $igsid)) && $lr->status === 'ok') {
            IgOutbox::queue($igsid, 'info', "✅ Kamu masuk ke {$name} sebagai @{$fighter->instagram_username}. Kembali ke browser ya!");

            return;
        }

        // 4b. Kode dari browser.
        if ($code) {
            [, $result] = $this->login->resolveCode($code, $profile['username'], 'ok', $fighter, $igsid);
            if ($result === 'ok') {
                IgOutbox::queue($igsid, 'info', "✅ Kode diterima. Kamu masuk ke {$name} sebagai @{$fighter->instagram_username}.");

                return;
            }
            if ($result === 'mismatch') {
                IgOutbox::queue($igsid, 'info', "Kode itu dibuat untuk username lain. Di browser, ketik @{$fighter->instagram_username} lalu minta kode baru.");

                return;
            }
        }

        // 4c. Kata kunci (atau kode yang tidak dikenali): kirim link masuk.
        if ($saysKeyword || $code) {
            $this->queueLoginLink($fighter);
        }
    }

    public function queueLoginLink(Fighter $fighter): void
    {
        $token = $this->login->issueLinkToken($fighter);
        $url = url('/masuk/'.$token);
        $hours = intdiv((int) $this->game->get('auth.link_ttl_min'), 60);
        $name = $this->game->get('brand.name');

        IgOutbox::queue($fighter->ig_scoped_id, 'login_link', implode("\n", [
            "🥊 Selamat datang di {$name}, @{$fighter->instagram_username}!",
            '',
            "Ketuk link ini untuk masuk (berlaku {$hours} jam, sekali pakai):",
            $url,
            '',
            'Link hanya membuat sesi di browser tempat link dibuka. Mau masuk di Chrome/Safari? Salin link-nya lalu tempel di sana.',
        ]));
    }

    /**
     * Kirim balasan yang mengantre. Batas laju sederhana per eksekusi.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendOutbox(int $limit = 50): array
    {
        $sent = $failed = 0;
        if (! $this->ig->configured()) {
            return compact('sent', 'failed');
        }

        $lock = Cache::lock('ig:send', 55);
        if (! $lock->get()) {
            return compact('sent', 'failed');
        }

        try {
            IgOutbox::where('status', 'queued')->where('send_after', '<=', now())
                ->orderBy('id')->limit($limit)->get()
                ->each(function (IgOutbox $m) use (&$sent, &$failed) {
                    try {
                        $this->ig->sendText($m->ig_scoped_id, $m->body['text']);
                        $m->forceFill(['status' => 'sent', 'sent_at' => now(), 'attempts' => $m->attempts + 1])->save();
                        $sent++;
                    } catch (Throwable $e) {
                        $attempts = $m->attempts + 1;
                        $m->forceFill([
                            'attempts' => $attempts,
                            'status' => $attempts >= 5 ? 'failed' : 'queued',
                            'send_after' => now()->addSeconds(30 * 2 ** $attempts),
                            'error' => mb_substr($e->getMessage(), 0, 250),
                        ])->save();
                        $failed++;
                    }
                });
        } finally {
            $lock->release();
        }

        Cache::forget('ig.dm_busy');

        return compact('sent', 'failed');
    }

    /** Antrean balasan sedang menumpuk? Web menampilkan banner dan mendorong login pakai kode. */
    public function dmBusy(): bool
    {
        return Cache::remember('ig.dm_busy', 30, function () {
            $q = IgOutbox::where('status', 'queued');
            $count = (clone $q)->count();
            $oldest = (clone $q)->min('created_at');

            return $count > (int) $this->game->get('dm_queue.busy_backlog')
                || ($oldest && now()->diffInSeconds($oldest, true) > (int) $this->game->get('dm_queue.busy_age_sec'));
        });
    }

    private function mentions(string $text, string $keyword): bool
    {
        if ($text === '' || $keyword === '') {
            return false;
        }

        return (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/iu', $text);
    }
}
