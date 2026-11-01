<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi game
|--------------------------------------------------------------------------
| Semua angka yang membentuk "rasa" game ada di sini. Nilai di tabel
| `settings` (diubah lewat panel admin) mengalahkan nilai di file ini,
| dibaca lewat App\Support\GameConfig::get('xp.win').
|
| Aturan MESIN PERTARUNGAN (rumus damage, peluang kena, dll.) TIDAK di sini,
| melainkan di resources/game/rules.v{versi}.json, karena dipakai bersama
| oleh PHP (server) dan JavaScript (animasi). Lihat resources/game/README.md.
*/

return [

    'brand' => [
        'name' => env('GAME_NAME', 'Web Boxing'),
        'tagline' => env('GAME_TAGLINE', 'Naik ring, buktikan namamu.'),
        'instagram' => env('GAME_INSTAGRAM', 'akunresmi'),   // tanpa @
        'keyword' => env('GAME_DM_KEYWORD', 'GELUD'),         // kata kunci DM
        'timezone' => 'Asia/Jakarta',
        'business_email' => env('GAME_BUSINESS_EMAIL'),
    ],

    // Matikan modul yang belum siap dirilis.
    'features' => [
        'piala' => true,
        'sasana' => true,
        'markas' => true,
        'latihan' => true,
        'dukung' => true,
        'push' => false,
        'ref_login' => true,        // login sekali ketuk lewat ig.me?ref=
        'require_follow' => true,   // wajib follow akun resmi untuk aktif
        'dev_login' => env('AUTH_DEV_LOGIN', false), // hanya berlaku saat APP_ENV=local
    ],

    'engine' => [
        // Versi aturan yang dipakai untuk pertarungan BARU.
        // Pertarungan lama tetap diputar dengan versinya sendiri.
        'version' => 1,
    ],

    'stats' => [
        'keys' => ['power', 'speed', 'stamina', 'defense', 'chin'],
        'max' => 99,
    ],

    'points' => [
        'start' => 10,                 // poin saat petarung diaktifkan
        'per_level' => 2,              // tiap naik level
        'milestones' => [5 => 3, 10 => 3, 15 => 3, 20 => 3], // bonus di level tertentu
        'respec_days' => 7,            // memindah poin yang sudah dipasang
    ],

    'xp' => [
        'win' => 10,
        'ko_bonus' => 5,
        'draw' => 5,
        'lose' => 3,
        'challenge_factor' => 0.5,
        'challenge_daily_cap' => 5,
        'cup_win' => 10,
        'cup_ko_bonus' => 5,
    ],

    'level' => [
        'max' => 20,
        'base' => 50,   // XP ke level berikut = base + step * (level - 1)
        'step' => 25,
    ],

    'stars' => [
        'win' => 1,
        'lose' => -1,
        'draw' => 0,
        'spar_range' => 3,
    ],

    // [minimal bintang, nama tier, ikon]
    'tiers' => [
        [0, 'Pemula Gang', '🥉'],
        [3, 'Jagoan Kampung', '🥈'],
        [6, 'Petarung Kota', '🥇'],
        [10, 'Juara Provinsi', '🏅'],
        [15, 'Bintang Nasional', '🌟'],
        [20, 'Raja Ring', '👑'],
        [30, 'Legenda', '🔥'],
    ],

    'season' => [
        'xp_carry_percent' => 100, // XP yang dibawa ke season berikutnya
        'champion_top' => 10,      // Top N dapat lencana Juara
    ],

    'fight_night' => [
        'hours' => [0, 6, 12, 18],  // jam WIB
        'prepare_minutes' => 60,    // mulai dihitung sebelum jam slot
        'chunk' => 2000,            // pasangan per eksekusi cron
        'active_days' => 14,        // petarung ikut bila aktif dalam N hari terakhir
        'retention_days' => 30,
    ],

    'piala' => [
        'reg_open' => 'monday 00:00',
        'draw' => 'thursday 12:00',
        'early_rounds_at' => '19:00',
        'round_gap_minutes' => 15,
        'finals' => ['sunday 19:00', 'sunday 19:30', 'sunday 20:00'],
        'min_entrants' => 8,
        'auto_active_days' => 2,
    ],

    // Ganti nama, ikon, warna, dan moto sasana sesukamu.
    'sasana' => [
        ['slug' => 'banteng', 'name' => 'Banteng', 'icon' => '🐂', 'color' => '#d64545', 'motto' => 'Maju terus, pantang mundur'],
        ['slug' => 'elang', 'name' => 'Elang', 'icon' => '🦅', 'color' => '#2f7de1', 'motto' => 'Cepat dan tepat'],
        ['slug' => 'macan', 'name' => 'Macan', 'icon' => '🐯', 'color' => '#e08a1e', 'motto' => 'Sabar, lalu terkam'],
        ['slug' => 'naga', 'name' => 'Naga', 'icon' => '🐉', 'color' => '#2e9d6b', 'motto' => 'Tahan banting'],
    ],

    'training' => [
        'seconds' => 30,
        'xp_cap_per_game' => 20,
        'bonus_max' => 3,
        'max_taps_per_sec' => 15,
        'games' => [
            'samsak' => 'power',
            'hindari' => 'speed',
            'lompat' => 'stamina',
            'tahan' => 'defense',
            'situp' => 'chin',
        ],
    ],

    'chat' => [
        'max_len' => 80,
        'cooldown_sec' => 3,
        'room_cap' => 100,
        'report_threshold' => 3,
        'mute_hours' => 24,
        'retention_days' => 7,
    ],

    'auth' => [
        'link_ttl_min' => 120,
        'code_ttl_min' => 30,
        'ref_ttl_min' => 15,
        'code_attempts' => 5,
        'session_days' => 90,
    ],

    'dm_queue' => [
        'busy_backlog' => 500,
        'busy_age_sec' => 300,
    ],

    'instagram' => [
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v26.0'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'account_id' => env('INSTAGRAM_ACCOUNT_ID'),
        'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
    ],

];
