<?php

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use App\Models\LoginRequest;
use App\Services\Auth\LoginService;
use App\Services\Instagram\InstagramProcessor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Di shared hosting cukup SATU cron tiap menit:
|   * * * * * cd ~/app && php artisan schedule:run >> /dev/null 2>&1
*/

// --- Instagram: cadangan bila pemrosesan setelah-respons tidak berjalan ---
Schedule::command('ig:process')->everyMinute()->withoutOverlapping();
Schedule::command('ig:send')->everyMinute()->withoutOverlapping();
Schedule::command('auth:prune')->daily();

Artisan::command('ig:process {--limit=200}', function (InstagramProcessor $p) {
    $n = $p->processPending((int) $this->option('limit'));
    $this->info("Event diproses: {$n}");
})->purpose('Proses event webhook Instagram yang belum diproses');

Artisan::command('ig:send {--limit=50}', function (InstagramProcessor $p) {
    $r = $p->sendOutbox((int) $this->option('limit'));
    $this->info("DM terkirim: {$r['sent']}, gagal: {$r['failed']}");
})->purpose('Kirim balasan DM yang mengantre');

Artisan::command('auth:prune', function () {
    $n = LoginRequest::where('created_at', '<', now()->subDays(7))->delete();
    $e = \App\Models\IgEvent::where('created_at', '<', now()->subDays(14))->delete();
    $o = \App\Models\IgOutbox::where('created_at', '<', now()->subDays(14))->whereIn('status', ['sent', 'failed'])->delete();
    $this->info("Dihapus: {$n} permintaan masuk, {$e} event IG, {$o} DM lama");
})->purpose('Hapus data login dan Instagram yang sudah lama');

// --- Login manual (sebelum Instagram API siap, atau saat DM bermasalah) ---
Artisan::command('login:pending', function () {
    $rows = LoginRequest::where('method', 'code')->where('status', 'pending')->where('expires_at', '>', now())
        ->latest()->limit(30)->get(['id', 'requested_username', 'created_at', 'expires_at']);
    if ($rows->isEmpty()) {
        $this->info('Tidak ada permintaan kode yang menunggu.');

        return;
    }
    $this->table(['ID', 'Username', 'Dibuat', 'Kedaluwarsa'], $rows->map(fn ($r) => [
        $r->id, '@'.$r->requested_username, $r->created_at->diffForHumans(), $r->expires_at->diffForHumans(),
    ]));
    $this->line('Setujui setelah pemain mengirim kodenya ke DM akun resmi: php artisan login:approve KODE');
})->purpose('Daftar permintaan masuk pakai kode yang menunggu');

Artisan::command('login:approve {code}', function (LoginService $login) {
    $lr = $login->approveManually($this->argument('code'));
    if (! $lr) {
        $this->error('Kode tidak ditemukan, sudah dipakai, atau kedaluwarsa.');

        return 1;
    }
    $this->info("Disetujui: @{$lr->requested_username}. Browser pemain akan masuk otomatis.");
    $this->warn('Petarung ini belum terverifikasi Instagram. Pastikan kodenya benar-benar dikirim dari akun @'.$lr->requested_username.'.');
})->purpose('Setujui kode masuk secara manual (tanpa Instagram API)');

// --- Mesin ---
Artisan::command('game:simulate {a} {b} {--seed=1} {--v=}', function (FighterFactory $factory, FightSimulator $sim) {
    $v = $this->option('v') ? (int) $this->option('v') : null;
    $fa = $factory->base($this->argument('a'), $v);
    $fb = $factory->base($this->argument('b'), $v);
    $r = $sim->simulate(
        ['style' => $fa['style'], 'stats' => $fa['base']],
        ['style' => $fb['style'], 'stats' => $fb['base']],
        (int) $this->option('seed'),
        $v,
    );

    $this->table(['', '@'.$fa['username'], '@'.$fb['username']], [
        ['Gaya', $fa['styleName'], $fb['styleName']],
        ...array_map(fn ($k) => [$k, $fa['base'][$k], $fb['base'][$k]], array_keys($fa['base'])),
        ['OVR', $factory->ovr($fa['base'], $v), $factory->ovr($fb['base'], $v)],
    ]);

    foreach ($r['events'] as $e) {
        $this->line(sprintf('R%d %-5s %-2s %-9s dmg=%-4s HP %4d - %-4d', $e['rd'], $e['t'], $e['who'] ?? '', $e['p'] ?? '', $e['d'] ?? '', $e['ha'], $e['hb']));
    }
    $winner = $r['win'] === 'draw' ? 'SERI' : '@'.($r['win'] === 'a' ? $fa['username'] : $fb['username']);
    $this->info("Hasil: {$winner} · {$r['how']} ronde {$r['rd']} · poin {$r['scoreA']}-{$r['scoreB']} · sig ".FightSimulator::signature($r));
})->purpose('Simulasikan laga dua username (tanpa menyimpan apa pun)');
