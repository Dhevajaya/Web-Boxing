# Web Boxing

Game tinju komunitas berbasis web. Pemain memakai username Instagram sebagai identitas petarung, masuk lewat DM ke akun resmi, lalu bertanding otomatis di **Fight Night** tiap 6 jam, menantang teman, latihan, ikut **Piala Mingguan**, membela **sasana**, dan naik ranking.

Stack: **Laravel 12 · PHP 8.3 · MySQL/SQLite · JavaScript (Vite)**. Berjalan di shared hosting: cukup satu cron tiap menit, tanpa worker atau websocket.

## Status

| Fase | Isi | Status |
| --- | --- | --- |
| 1 | Fondasi Laravel, `config/game.php`, mesin pertarungan PHP + JS + test vektor, migration inti, seeder | ✅ |
| 2 | Login Instagram (sekali ketuk, link DM, kode, manual, dev), sesi pemain | ✅ |
| 3 | Profil: avatar, editor tampilan, julukan & kota, bagi poin stat | ✅ |
| 4 | Fight Night otomatis, arena animasi, ranking season & mingguan | ✅ |
| 5–6 | Tantangan langsung (`/t/{kode}`), Mading, penutupan season, lencana, Hall of Fame | ✅ |
| 7–8 | Piala Mingguan (undian, bagan, jadwal), Sasana & Perang Sasana | ✅ |
| 9 | Latihan (5 mini-game, XP & bonus stat harian), Markas (chat, laporan, bisu, cari lawan) | ✅ |
| 10 | Panel admin (peran, 2FA, moderasi, pengaturan, audit), Dukung (Saweria, tier pendukung) | ✅ |
| 11 | Tombol fitur, halaman privasi, header keamanan, halaman error, paket rilis, panduan deploy | ✅ |

Aturan lengkap: [docs/game-design.md](docs/game-design.md). Deploy: [docs/deploy-hostinger.md](docs/deploy-hostinger.md).

## Menjalankan di komputer sendiri

Butuh PHP 8.3+ (ekstensi `pdo_sqlite`, `mbstring`), Composer, dan Node 20+.

```bash
git clone https://github.com/Dhevajaya/Web-Boxing.git
cd Web-Boxing
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan game:demo                 # 12 pemain contoh + 4 Fight Night yang sudah dimainkan
php artisan admin:create admin@contoh.id
npm install
npm run dev                           # biarkan jalan di terminal ini
php artisan serve                     # di terminal lain, buka http://localhost:8000
```

- Masuk sebagai pemain: klik **Masuk** → *Mode pengembang* → ketik username apa saja (misalnya `budi` dari data contoh).
- Panel admin: `http://localhost:8000/admin`.
- Mesin game (Fight Night, piala) di lokal: jalankan `php artisan schedule:work` di terminal ketiga, atau picu manual:

```bash
php artisan fightnight:run --now      # mainkan satu Fight Night sekarang juga
php artisan piala:tick                # undian/laga piala sesuai jadwal
php artisan season:close              # tutup season (minta konfirmasi)
php artisan game:simulate budi siti --seed=7
```

## Login pemain

Pemain masuk lewat DM Instagram, tanpa password. Cara menyambungkan akun Instagram resmi ada di [docs/instagram-setup.md](docs/instagram-setup.md).
Sebelum Instagram tersambung, pemain bisa minta kode di web lalu admin menyetujuinya di **panel admin → Login manual** (atau `php artisan login:approve KODE`).

## Test

```bash
php artisan test              # unit + feature (Laravel)
php tools/check-engine.php    # mesin PHP terhadap vektor (tanpa Laravel)
npm run test:engine           # mesin JavaScript + avatar terhadap vektor yang sama
```

GitHub Actions menjalankan ketiganya di setiap push ke `main`/`fase-*` dan setiap Pull Request.

## Mengubah game sesuai seleramu

| Yang diubah | Di mana |
| --- | --- |
| Nama game, akun Instagram, kata kunci DM | Panel admin → Pengaturan, atau `.env` (`GAME_NAME`, `GAME_INSTAGRAM`, `GAME_DM_KEYWORD`) |
| XP, level, bintang, jadwal Fight Night, batas chat/tantangan, fitur aktif | Panel admin → Pengaturan (mengalahkan `config/game.php`) |
| Poin stat, tier, piala, latihan, tier pendukung | `config/game.php` |
| Nama/ikon/warna 4 sasana | `config/game.php` → `sasana`, lalu `php artisan db:seed --class=GameSeeder` |
| Kata terlarang di julukan/chat | `config/game.php` → `profile.banned_words` |
| Pilihan tampilan avatar | `resources/game/looks.json` (jangan ganti/hapus `id` yang sudah dipakai) |
| Warna dan font | `resources/css/app.css` (bagian `:root`) |
| Rumus pertarungan | `resources/game/rules.v{n}.json`, baca `resources/game/README.md` dulu |
| Julukan, kota, NPC bawaan | `resources/game/content.json` (tambah di akhir daftar saja) |

## Struktur penting

```text
app/Game/              logika murni: Rng, FighterFactory, FightSimulator, Progression, Pairing, Bracket, Looks, NameRules
app/Services/Game/     FightNight, ResultApplier, Challenges, Piala, Seasons, Training, Supporters, Calendar, StatBonus
app/Http/Controllers/  Api/ (JSON untuk SPA), Admin/ (panel admin Blade)
resources/game/        aturan mesin & katalog tampilan (JSON) yang dipakai PHP dan JS
resources/js/game/     port mesin ke JavaScript + avatar.js (avatar SVG)
resources/js/views/    halaman SPA: beranda, kartu, editor, arena, ranking, mading, piala, latihan, markas, dukung
resources/views/admin/ panel admin
routes/console.php     perintah artisan + jadwal cron
tests/fixtures/        vektor kesetaraan PHP = JS
tools/                 make-vectors, check-engine, engine-stats
```

## Deploy

Ringkasnya: buat paket lewat GitHub Actions (**Actions → Paket rilis**), unggah ke hosting, isi `.env` dari `docs/env.production.example`,
`php artisan migrate --force --seed`, `php artisan admin:create`, `php artisan optimize`, lalu cron tiap menit:

```text
* * * * * /usr/bin/php /path/ke/web-boxing/artisan schedule:run >> /dev/null 2>&1
```

Langkah lengkap untuk Hostinger, termasuk tanpa SSH: [docs/deploy-hostinger.md](docs/deploy-hostinger.md).
