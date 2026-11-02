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
Schedule::command('game:prune')->dailyAt('04:10');

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

// --- Fight Night (Fase 4) ---
Schedule::command('fightnight:tick')->everyMinute()->withoutOverlapping(10);

Artisan::command('fightnight:tick', function (\App\Services\Game\FightNight $fn) {
    foreach ($fn->tick() as $line) {
        $this->line($line);
    }
})->purpose('Siapkan, hitung, dan terbitkan Fight Night sesuai jadwal');

Artisan::command('fightnight:run {--slot= : waktu slot (zona game), mis. "2026-11-02 18:00"} {--now : slot = sekarang, untuk uji di lokal}', function (\App\Services\Game\FightNight $fn, \App\Services\Game\Calendar $cal) {
    if ($this->option('now')) {
        $slot = \Carbon\CarbonImmutable::now()->startOfMinute();
    } elseif ($s = $this->option('slot')) {
        $slot = \Carbon\CarbonImmutable::parse($s, $cal->tz())->utc();
    } else {
        $slot = $cal->nextSlot();
    }
    $run = $fn->runNow($slot);
    $this->info("Slot {$run->slot_at->setTimezone($cal->tz())->format('Y-m-d H:i')} WIB: {$run->status}, {$run->pairs_total} laga".($run->error ? " ({$run->error})" : ''));
    if ($run->status !== 'done') {
        $this->warn('Hasil diterapkan otomatis saat jam slot tiba (fightnight:tick).');
    }
})->purpose('Jalankan satu Fight Night sekarang (manual/uji)');

// --- Piala Mingguan (Fase 7) ---
Schedule::command('piala:tick')->everyMinute()->withoutOverlapping(10)
    ->when(fn () => app(\App\Support\GameConfig::class)->feature('piala'));

Artisan::command('piala:tick', function (\App\Services\Game\Piala $piala) {
    $piala->tick();
    $cup = $piala->current();
    $this->line("Piala {$cup->week_start}: {$cup->status}");
})->purpose('Undian dan laga Piala Mingguan sesuai jadwal');

// --- Season (Fase 6) ---
Artisan::command('season:close {--name= : nama season baru} {--force : tanpa konfirmasi}', function (\App\Services\Game\Seasons $seasons) {
    $cur = \App\Models\Season::current();
    if (! $cur) {
        $this->error('Tidak ada season aktif.');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm("Tutup {$cur->name}? Peringkat dibekukan dan bintang mulai dari 0.")) {
        return 1;
    }
    $r = $seasons->close($this->option('name'));
    $this->info("{$r['closed']->name} ditutup ({$r['ranked']} petarung berperingkat). {$r['next']->name} dimulai.");
})->purpose('Tutup season aktif dan mulai season berikutnya');

// --- Pembersihan data lama ---
Artisan::command('game:prune', function (\App\Support\GameConfig $game) {
    $chat = \App\Models\ChatMessage::where('created_at', '<', now()->subDays((int) $game->get('chat.retention_days')))->delete();
    $train = \Illuminate\Support\Facades\DB::table('training_sessions')->where('created_at', '<', now()->subDays(30))->delete();
    $runs = \App\Models\FightNightRun::where('slot_at', '<', now()->subDays((int) $game->get('fight_night.retention_days')))->delete();
    $this->info("Dihapus: {$chat} pesan chat, {$train} sesi latihan, {$runs} catatan Fight Night");
})->purpose('Hapus chat, sesi latihan, dan catatan run yang sudah lama');

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
    $r = $sim->simulate($factory->defaultInput($fa['username'], $v), $factory->defaultInput($fb['username'], $v), (int) $this->option('seed'), $v);

    $this->table(['', '@'.$fa['username'], '@'.$fb['username']], [
        ['Gaya', $fa['styleName'], $fb['styleName']],
        ...array_map(fn ($k) => [$k, $fa['base'][$k], $fb['base'][$k]], array_keys($fa['base'])),
        ['OVR', $factory->ovr($fa['base'], $v), $factory->ovr($fb['base'], $v)],
    ]);

    foreach ($r['events'] as $e) {
        $this->line(sprintf('R%d %-6s %-2s %-10s %-16s dmg=%-4s HP %4d - %-4d', $e['rd'], $e['t'], $e['who'] ?? '', $e['p'] ?? '', $e['j'] ?? '', $e['d'] ?? '', $e['ha'], $e['hb']));
    }
    $winner = $r['win'] === 'draw' ? 'SERI' : '@'.($r['win'] === 'a' ? $fa['username'] : $fb['username']);
    $this->info("Hasil: {$winner} · {$r['how']} ronde {$r['rd']} · poin {$r['scoreA']}-{$r['scoreB']} · sig ".FightSimulator::signature($r));
})->purpose('Simulasikan laga dua username (tanpa menyimpan apa pun)');

// --- Data contoh untuk mencoba di lokal ---
Artisan::command('game:demo {count=12 : jumlah pemain contoh} {--nights=4 : Fight Night yang dimainkan}', function (\App\Services\Game\FightNight $fn, \App\Services\Game\Calendar $cal) {
    if (app()->environment('production')) {
        $this->error('Tidak boleh di produksi.');

        return 1;
    }
    $names = ['budi', 'siti', 'raka', 'dewi', 'eko_jp', 'mawar.123', 'jojo', 'tegar', 'ayu', 'bagas', 'intan', 'yoga', 'rina', 'fajar', 'lala', 'dodi'];
    $n = min((int) $this->argument('count'), count($names));
    foreach (array_slice($names, 0, $n) as $u) {
        \App\Models\Fighter::firstOrCreate(['instagram_username' => $u], [
            'status' => 'active', 'activated_at' => now(), 'last_active_at' => now(), 'verified_at' => now(),
        ]);
    }
    $slot = $cal->prevSlot();
    $slots = [];
    for ($i = 0; $i < (int) $this->option('nights'); $i++) {
        $slots[] = $slot;
        $slot = $cal->prevSlot($slot->subMinute());
    }
    foreach (array_reverse($slots) as $s) {
        $run = $fn->runNow($s);
        $this->line("Fight Night {$s->setTimezone($cal->tz())->format('d M H:i')} WIB: {$run->pairs_total} laga");
    }
    $this->info("{$n} pemain contoh siap. Masuk lewat mode pengembang dengan salah satu username di atas.");
})->purpose('Buat pemain contoh dan mainkan beberapa Fight Night (lokal saja)');

// --- Admin ---
Artisan::command('admin:create {email} {--name=Owner} {--role=owner : owner, moderator, atau editor} {--password= : kosongkan agar ditanya}', function () {
    $email = strtolower((string) $this->argument('email'));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Email tidak valid.');

        return 1;
    }
    $role = (string) $this->option('role');
    if (! in_array($role, ['owner', 'moderator', 'editor'], true)) {
        $this->error('Peran harus owner, moderator, atau editor.');

        return 1;
    }
    $password = (string) ($this->option('password') ?: $this->secret('Password (min. 10 karakter)'));
    if (mb_strlen($password) < 10) {
        $this->error('Password minimal 10 karakter.');

        return 1;
    }
    $admin = \App\Models\Admin::updateOrCreate(['email' => $email], ['name' => (string) $this->option('name'), 'password' => $password, 'role' => $role]);
    $this->info("Admin {$admin->email} ({$admin->role}) siap. Masuk di ".url('/admin'));
})->purpose('Buat atau perbarui akun admin (password di-hash)');

// --- Migrasi ke KAMPUNG TENDANG ---
Artisan::command('game:reset-competitive {--dry-run : tampilkan yang akan berubah tanpa mengubah apa pun} {--force : tanpa konfirmasi}', function (\App\Services\Game\CompetitiveReset $reset) {
    $plan = $reset->plan();
    $s = $plan['season'];
    $this->info('Season berjalan: '.($s ? "#{$s['number']} {$s['name']} (era {$s['era']})" : 'tidak ada'));
    $this->table(['Yang terdampak', 'Baris'], collect($plan['rows'])->map(fn ($n, $k) => [$k, $n])->values()->all());
    if ($this->option('dry-run')) {
        $this->comment('Dry-run: tidak ada yang diubah.');

        return 0;
    }
    $this->warn('Pastikan database sudah di-backup. Langkah ini tidak bisa dibatalkan tanpa backup.');
    if (! $this->option('force') && ! $this->confirm('Mulai era KAMPUNG TENDANG sekarang?')) {
        return 1;
    }
    $r = $reset->run();
    $this->info("Selesai. Season baru: #{$r['season']->number} {$r['season']->name}. Lencana veteran: {$r['veterans']} pemain.");

    return 0;
})->purpose('Nolkan statistik kompetitif untuk era KAMPUNG TENDANG (laga lama tetap jadi arsip)');
