# Web Boxing

Game tinju komunitas berbasis web. Pemain memakai username Instagram sebagai identitas petarung, masuk lewat DM ke akun resmi, lalu bertanding otomatis di **Fight Night** tiap 6 jam, naik level, dan bersaing di ranking, Piala Mingguan, dan Perang Sasana.

Stack: **Laravel 12 · PHP 8.3 · MySQL/SQLite · JavaScript (Vite)**. Dirancang agar bisa jalan di shared hosting (cukup satu cron tiap menit).

## Status

| Fase | Isi | Status |
| --- | --- | --- |
| 1 | Fondasi Laravel, `config/game.php`, mesin pertarungan PHP + JS + test vektor, migration inti, seeder | ✅ |
| 2 | Login Instagram (sekali ketuk, link DM, kode, manual, dev), sesi pemain | ✅ |
| 3 | Profil dan editor tampilan petarung | ⏳ |
| 4 | Fight Night batch, arena animasi, ranking | ⏳ |
| 5–11 | Tantangan, Mading, Season, Sasana, Piala, Latihan, Markas, Admin, Dukung, QA | ⏳ |

Rencana lengkap ada di dokumen blueprint proyek.

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
npm install
npm run dev                            # biarkan jalan di terminal ini
php artisan serve                      # di terminal lain, buka http://localhost:8000
```

Masuk sebagai pemain di lokal: klik **Masuk** → bagian *Mode pengembang* → ketik username apa saja (aktif karena `APP_ENV=local` dan `AUTH_DEV_LOGIN=true`).

Di beranda ada kotak **Uji mesin**: laga dihitung di browser lalu dibandingkan dengan hitungan server. Tanda ✓ berarti keduanya identik.

Coba juga dari terminal:

```bash
php artisan game:simulate budi siti --seed=7
```

Mau pakai MySQL? Ubah `DB_CONNECTION=mysql` dan isi `DB_*` di `.env`, lalu `php artisan migrate --seed`.

## Login pemain

Pemain masuk lewat DM Instagram, tanpa password. Panduan menyambungkan akun Instagram resmi ada di [docs/instagram-setup.md](docs/instagram-setup.md).

Sebelum Instagram tersambung, pemain bisa minta kode di web lalu admin menyetujuinya:

```bash
php artisan login:pending          # daftar kode yang menunggu
php artisan login:approve ABCD-EFGH
```

## Test

```bash
php artisan test              # unit + feature (Laravel)
php tools/check-engine.php    # mesin PHP terhadap vektor (tanpa Laravel)
npm run test:engine           # mesin JavaScript terhadap vektor yang sama
```

GitHub Actions menjalankan ketiganya di setiap push dan Pull Request.

## Mengubah game sesuai seleramu

| Yang diubah | Di mana |
| --- | --- |
| Nama game, akun Instagram, kata kunci DM | `.env` (`GAME_NAME`, `GAME_INSTAGRAM`, `GAME_DM_KEYWORD`) atau `config/game.php` → `brand` |
| XP, level, poin stat, bintang, tier | `config/game.php` |
| Nama/ikon/warna 4 sasana | `config/game.php` → `sasana`, lalu `php artisan db:seed --class=GameSeeder` |
| Jadwal Fight Night dan Piala | `config/game.php` → `fight_night`, `piala` |
| Fitur yang aktif | `config/game.php` → `features` |
| Warna dan font | `resources/css/app.css` (bagian `:root`) |
| Rumus pertarungan | `resources/game/rules.v{n}.json`, baca `resources/game/README.md` dulu |
| Julukan, kota, NPC bawaan | `resources/game/content.json` (tambah di akhir daftar saja) |

Nilai di tabel `settings` (nanti diubah dari panel admin) mengalahkan nilai di `config/game.php`.

## Struktur penting

```text
app/Game/            mesin murni: Rng, FighterFactory, FightSimulator, Progression
app/Support/         GameConfig (config + settings)
resources/game/      aturan mesin (JSON) yang dipakai PHP dan JS
resources/js/game/   port mesin ke JavaScript untuk animasi di browser
tests/fixtures/      vektor kesetaraan PHP = JS
tools/               make-vectors, check-engine, engine-stats
```

## Deploy ke shared hosting (ringkas)

1. `composer install --no-dev --optimize-autoloader` dan `npm ci && npm run build` (di lokal atau CI).
2. Unggah ke folder di luar `public_html`, arahkan domain ke folder `public`.
3. `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=mysql`, `SESSION_SECURE_COOKIE=true`, `AUTH_DEV_LOGIN=false`.
4. `php artisan migrate --force && php artisan db:seed --force && php artisan optimize`.
5. Cron tiap menit: `* * * * * cd ~/app && php artisan schedule:run >> /dev/null 2>&1`.
