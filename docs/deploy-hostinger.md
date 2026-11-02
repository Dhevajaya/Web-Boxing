# Deploy ke Hostinger (shared hosting)

Panduan ini untuk paket **Premium/Business** Hostinger (ada akses SSH). Di bagian akhir ada cara tanpa SSH.
Semua langkah dilakukan sekali; update berikutnya cukup bagian **Update versi**.

Yang dibutuhkan:

- Domain yang sudah mengarah ke Hostinger, dengan SSL aktif (hPanel → Security → SSL → pasang gratis).
- Repo GitHub `Dhevajaya/Web-Boxing`.
- 20–30 menit.

---

## 1. Siapkan hPanel

1. **PHP 8.3**: hPanel → Websites → Manage → Advanced → **PHP Configuration** → pilih PHP 8.3.
   Di tab *PHP extensions* pastikan aktif: `pdo_mysql`, `mbstring`, `openssl`, `intl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`.
2. **Database**: hPanel → Databases → **MySQL Databases** → buat database + user. Catat nama database, user, dan password
   (formatnya `u123456789_namadb`).
3. **SSH**: hPanel → Advanced → **SSH Access** → aktifkan, catat host, port (biasanya `65002`), dan username.

## 2. Buat paket rilis (vendor + aset sudah jadi)

Hosting tidak punya Node.js untuk `npm run build`, jadi paket dibuat oleh GitHub Actions:

1. Push kode ke GitHub.
2. Buka repo → tab **Actions** → **Paket rilis** → **Run workflow**.
3. Setelah hijau (±3 menit), buka run tersebut → bagian *Artifacts* → unduh `web-boxing-....zip`.
   (Workflow ini juga menjalankan semua test dulu; kalau ada yang gagal, paket tidak dibuat.)

Atau buat sendiri di komputer: `composer install --no-dev --optimize-autoloader && npm ci && npm run build`, lalu zip foldernya (tanpa `node_modules`, `.git`, `.env`).

## 3. Unggah

Struktur yang dituju (folder aplikasi **di luar** `public_html`, supaya `.env` tidak pernah bisa diakses dari web):

```text
/home/u123456789/domains/domainmu.com/
├── web-boxing/          ← isi ZIP di sini
└── public_html  →  web-boxing/public   (symlink)
```

1. hPanel → **File Manager** → buka `domains/domainmu.com/` → buat folder `web-boxing` → unggah ZIP ke dalamnya → klik kanan → **Extract**.
2. Masuk SSH lalu buat symlink:

   ```bash
   ssh -p 65002 u123456789@IP-SERVER
   cd ~/domains/domainmu.com
   mv public_html public_html.lama        # simpan cadangan halaman bawaan Hostinger
   ln -s web-boxing/public public_html
   ```

## 4. Isi `.env`

```bash
cd ~/domains/domainmu.com/web-boxing
cp docs/env.production.example .env
php artisan key:generate
nano .env                               # isi APP_URL, DB_*, GAME_*, dll. Simpan: Ctrl+O, Enter, Ctrl+X
```

Yang wajib diisi: `APP_URL` (pakai `https://`), `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `GAME_NAME` (mis. `"KAMPUNG TENDANG"`), `GAME_INSTAGRAM`, `GAME_DM_KEYWORD` (mis. `KAMPUNGTENDANG`).
Pastikan `APP_DEBUG=false`, `AUTH_DEV_LOGIN=false`, dan `SESSION_SECURE_COOKIE=true`.

## 5. Database, admin, cache

```bash
php artisan migrate --force             # buat semua tabel
php artisan db:seed --force             # 4 sasana, Season 0, 16 NPC
php artisan admin:create emailmu@contoh.com   # ditanya password (min. 10 karakter)
php artisan optimize                    # cache config, route, view
chmod -R 775 storage bootstrap/cache
```

Buka `https://domainmu.com`, lalu `https://domainmu.com/admin` dan masuk. Di **Akun**, aktifkan autentikator (2FA).

## 6. Cron (wajib, mesin game berjalan dari sini)

hPanel → Advanced → **Cron Jobs** → *Custom* → interval **setiap menit** (`* * * * *`), perintah:

```text
/usr/bin/php /home/u123456789/domains/domainmu.com/web-boxing/artisan schedule:run >> /dev/null 2>&1
```

Cek path PHP lewat SSH dengan `which php` (kadang `/opt/alt/php83/usr/bin/php`). Cron ini menjalankan:

| Tugas | Kapan |
| --- | --- |
| `fightnight:tick` | tiap menit: memasangkan petarung 60 menit sebelum slot, menghitung, menerbitkan hasil |
| `piala:tick` | tiap menit: undian Kamis 12.00, laga Sabtu/Minggu malam |
| `ig:process`, `ig:send` | tiap menit: cadangan pemrosesan DM Instagram |
| `game:prune`, `auth:prune` | harian: hapus chat > 7 hari, data login lama |

Uji tanpa menunggu: `php artisan schedule:list`, lalu `php artisan fightnight:tick`.
Setelah ±1 jam, panel admin → **Dasbor** menampilkan run Fight Night berstatus `ready`/`done`.

## 7. Instagram dan Saweria

- **Instagram**: ikuti [instagram-setup.md](instagram-setup.md). Webhook: `https://domainmu.com/api/webhooks/instagram`.
  Sampai Instagram tersambung, pemain bisa masuk pakai kode dan kamu menyetujuinya di panel admin → **Login manual**.
- **Saweria** (opsional): isi `SUPPORT_SAWERIA_URL` dan `SUPPORT_WEBHOOK_TOKEN` di `.env`, jalankan `php artisan config:cache`,
  lalu di dasbor Saweria → Integrasi → Webhook isi URL `https://domainmu.com/api/webhooks/saweria/TOKEN-KAMU`.
  Pemain menulis `@username` di pesan donasi; donasi tanpa username tetap tercatat di admin → **Dukung**
  dan bisa dicocokkan manual.

## 8. Pemeriksaan akhir

- [ ] `https://domainmu.com/up` menampilkan halaman hijau "Application up".
- [ ] Beranda menampilkan hitung mundur Fight Night.
- [ ] `https://domainmu.com/.env` → **404** (bukan isi file!).
- [ ] Masuk pakai kode → setujui di admin → browser masuk otomatis.
- [ ] Panel admin: Dasbor tampil, autentikator aktif.
- [ ] Setelah slot Fight Night berikutnya, laga muncul di kartu dan ranking.

---

## Update versi

```bash
# 1) Buat paket rilis baru (langkah 2), unggah ZIP ke ~/domains/domainmu.com/
cd ~/domains/domainmu.com
php web-boxing/artisan down                       # mode perawatan
unzip -oq web-boxing-baru.zip -d web-boxing       # timpa file; .env dan storage tetap
cd web-boxing
php artisan migrate --force
# Sekali saja saat pindah dari versi tinju ke KAMPUNG TENDANG (sesudah backup database!):
#   php artisan game:reset-competitive --dry-run
#   php artisan game:reset-competitive
php artisan optimize
php artisan up
```

Data (database, `.env`, `storage/`) tidak tersentuh oleh ZIP.

## Tanpa SSH (paket Single/hosting tanpa terminal)

1. Ekstrak ZIP ke `public_html/web-boxing`.
2. Buat `public_html/.htaccess`:

   ```apache
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteCond %{REQUEST_URI} !^/web-boxing/public/
     RewriteRule ^(.*)$ web-boxing/public/$1 [L]
   </IfModule>
   ```

   Semua alamat diarahkan ke `public/`, jadi `domainmu.com/web-boxing/.env` ikut diarahkan ke `public/` dan hasilnya 404.
3. Buat `.env` lewat File Manager (salin isi `docs/env.production.example`). Untuk `APP_KEY`, buat di komputer:
   `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`.
4. Perintah artisan dijalankan lewat **Cron Jobs** sekali jalan (buat, tunggu 1 menit, lalu hapus):

   ```text
   /usr/bin/php /home/u123456789/public_html/web-boxing/artisan migrate --force --seed
   /usr/bin/php /home/u123456789/public_html/web-boxing/artisan admin:create emailmu@contoh.com --password=PasswordPanjangmu
   /usr/bin/php /home/u123456789/public_html/web-boxing/artisan optimize
   ```

   Setelah itu pasang cron `schedule:run` tiap menit seperti langkah 6 (path disesuaikan), lalu ganti password admin dari panel → **Akun**.

## Masalah umum

| Gejala | Penyebab / solusi |
| --- | --- |
| Error 500 putih | `storage/logs/laravel-*.log`. Biasanya izin folder: `chmod -R 775 storage bootstrap/cache`. |
| "419 Sesi kedaluwarsa" saat masuk admin | `SESSION_SECURE_COOKIE=true` tapi situs dibuka lewat `http://`. Paksa HTTPS di hPanel. |
| Tampilan polos tanpa gaya | Folder `public/build` tidak ikut terunggah. Pakai ZIP dari GitHub Actions. |
| Fight Night tidak jalan | Cron belum aktif atau path PHP salah. Coba perintah cron di SSH. |
| Pengaturan admin tidak berubah | Tunggu 60 detik (cache), atau `php artisan cache:clear`. |
| Mengubah `.env` tidak berpengaruh | Jalankan `php artisan config:cache` setelah mengubah `.env`. |
