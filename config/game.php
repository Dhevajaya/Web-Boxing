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
        'name' => env('GAME_NAME', 'KAMPUNG TENDANG'),
        'tagline' => env('GAME_TAGLINE', 'Dari Gang, Naik Gelanggang.'),
        'instagram' => env('GAME_INSTAGRAM', 'akunresmi'),   // tanpa @
        'keyword' => env('GAME_DM_KEYWORD', 'KAMPUNGTENDANG'), // kata kunci DM
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
        // Versi aturan yang dipakai untuk pertarungan BARU (2 = KAMPUNG TENDANG, kickboxing).
        // Pertarungan lama tetap diputar dengan versinya sendiri (1 = arsip era tinju).
        'version' => 2,
    ],

    'stats' => [
        // Harus sama dengan "stats" di resources/game/rules.v{engine.version}.json.
        'keys' => ['strength', 'speed', 'endurance', 'stamina', 'technique', 'accuracy', 'defense', 'agility', 'balance', 'composure'],
        'max' => 99,
    ],

    'points' => [
        'start' => 20,                 // poin saat petarung diaktifkan (10 atribut)
        'per_level' => 4,              // tiap naik level
        'milestones' => [5 => 5, 10 => 5, 15 => 5, 20 => 5], // bonus di level tertentu
        'respec_days' => 7,            // memindah poin yang sudah dipasang
        'archetype_days' => 7,         // jeda ganti archetype (ganti pertama bebas)
    ],

    // Profil buatan pemain: julukan, kota, tampilan avatar (katalog: resources/game/looks.json).
    'profile' => [
        'nick_min' => 3,
        'nick_max' => 24,
        'kota_min' => 3,
        'kota_max' => 24,
        'identity_cooldown_hours' => 24,   // jeda mengganti julukan/kota (ganti pertama bebas)
        // Kata yang tidak boleh ada di julukan/kota. Kata < 5 huruf dicocokkan utuh,
        // yang lebih panjang dicocokkan di mana saja (juga "4njing", "anjiiing").
        'banned_words' => [
            'admin', 'moderator', 'official', 'resmi', 'staff',
            'anjing', 'bangsat', 'bajingan', 'babi', 'asu', 'jancok', 'jancuk', 'kontol', 'memek',
            'ngentot', 'entot', 'goblok', 'tolol', 'kampret', 'pelacur', 'lonte', 'perek', 'bego',
            'tai', 'taik', 'setan', 'iblis', 'fuck', 'shit', 'bitch', 'nazi',
        ],
    ],

    // Tantangan langsung (/t/{kode}): laga instan, hanya penantang yang dapat XP.
    'challenge' => [
        'daily_limit' => 20,             // tantangan per pemain per hari (zona game)
        'same_opponent_minutes' => 10,   // jeda menantang lawan yang sama
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
        [0, 'Anak Gang', '🥉'],
        [3, 'Jagoan Gang', '🥈'],
        [6, 'Penguasa Blok', '🥇'],
        [10, 'Jawara Kampung', '🏅'],
        [15, 'Raja Gelanggang', '🌟'],
        [20, 'Legenda Kampung', '👑'],
        [30, 'Legenda Abadi', '🔥'],
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
        'early_rounds_day' => 'saturday',   // babak sebelum 3 babak terakhir dimainkan hari ini
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

    // Latihan: mini-game 30 detik. Skor terbaik hari ini jadi XP (total semua game maks xp_daily_cap),
    // ditambah bonus streak sekali sehari. Bintang (1-3) = bonus stat +1..+3 untuk laga hari itu.
    'training' => [
        'seconds' => 30,
        'xp_daily_cap' => 30,
        'streak_xp' => 5,
        'bonus_max' => 3,
        'max_taps_per_sec' => 15,
        // stat: stat yang dilatih · stars: skor untuk 1/2/3 bintang · xp_div: skor per 1 XP
        // max, per_tap, free: batas anti-curang (skor <= max dan skor <= (ketukan + free) x per_tap)
        // soon: tampil "Segera" (belum bisa dimainkan)
        'games' => [
            'samsak' => ['stat' => 'strength', 'stars' => [1000, 2200, 3800], 'xp_div' => 100, 'max' => 24000, 'per_tap' => 300, 'free' => 0],
            'hindari' => ['stat' => 'agility', 'stars' => [500, 1100, 1900], 'xp_div' => 60, 'max' => 8000, 'per_tap' => 220, 'free' => 25],
            'tahan' => ['stat' => 'defense', 'stars' => [500, 1100, 1800], 'xp_div' => 75, 'max' => 9000, 'per_tap' => 260, 'free' => 25],
            'lompat' => ['stat' => 'stamina', 'soon' => true],
            'situp' => ['stat' => 'endurance', 'soon' => true],
        ],
    ],

    // Cari lawan sparing di Markas: pemain aktif N hari terakhir dengan selisih bintang <= stars.spar_range.
    'spar' => [
        'active_days' => 3,
        'limit' => 10,
    ],

    'chat' => [
        'max_len' => 80,
        'cooldown_sec' => 3,
        'room_cap' => 100,
        'report_threshold' => 3,
        'mute_hours' => 24,
        'retention_days' => 7,
    ],

    // Dukung: donasi lewat Saweria (atau platform lain yang mengirim webhook JSON).
    // Pemain menulis @username di pesan donasi; total donasi menentukan tier (tidak pernah turun).
    'supporter' => [
        'saweria_url' => env('SUPPORT_SAWERIA_URL'),        // mis. https://saweria.co/akunmu
        'webhook_token' => env('SUPPORT_WEBHOOK_TOKEN'),    // rahasia di URL webhook: /api/webhooks/saweria/{token}
        'tiers' => [
            ['id' => 'tribun', 'name' => 'Tribun', 'min' => 10000],
            ['id' => 'ringside', 'name' => 'Ringside', 'min' => 25000],
            ['id' => 'vip', 'name' => 'VIP', 'min' => 50000],
        ],
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

    // Admin pertama, dibuat GameSeeder bila keduanya diisi (lebih aman: php artisan admin:create).
    'admin_seed' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'instagram' => [
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v26.0'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'account_id' => env('INSTAGRAM_ACCOUNT_ID'),
        'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
    ],

];
