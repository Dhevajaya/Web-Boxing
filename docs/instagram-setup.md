# Menyambungkan Instagram

Login pemain berjalan lewat **Instagram API with Instagram Login** (Messaging). Selama langkah ini belum selesai, game tetap bisa dipakai lewat **mode pengembang** (lokal) dan **persetujuan manual**: panel admin → Login manual, atau `php artisan login:approve KODE`.

## 1. Akun Instagram resmi

- Ubah akun resmi game menjadi akun **profesional** (Business atau Creator): Pengaturan → Jenis akun dan alat.
- Di aplikasi Instagram: Pengaturan → Pesan dan balasan cerita → Kontrol pesan → aktifkan **Izinkan akses ke pesan** untuk alat yang terhubung.
- Buat **Icebreaker** (pertanyaan cepat) berisi kata kunci, misalnya `GELUD`, dengan payload `GELUD`. Dengan ini, pemain baru yang membuka link `ig.me` cukup mengetuk satu tombol.

## 2. Aplikasi Meta

1. Buka [developers.facebook.com](https://developers.facebook.com/) → My Apps → Create App → pilih kasus penggunaan Instagram (Instagram API with Instagram Login).
2. Tambahkan izin `instagram_business_basic` dan `instagram_business_manage_messages`.
3. Di bagian *Generate access tokens*, tambahkan akun Instagram resmi lalu buat token. Salin:
   - **Access token** → `INSTAGRAM_ACCESS_TOKEN`
   - **ID akun Instagram** (IG user ID) → `INSTAGRAM_ACCOUNT_ID`
4. App settings → Basic → **App secret** → `INSTAGRAM_APP_SECRET`.
5. Karena aplikasi hanya melayani akun Instagram milikmu sendiri, **Standard Access** cukup; App Review untuk Advanced Access tidak diperlukan. Aplikasi tetap harus berstatus **Published (Live)** agar webhook terkirim.

## 3. Webhook

- Callback URL: `https://domainmu/api/webhooks/instagram` (harus HTTPS publik).
- Verify token: string acak buatanmu → `INSTAGRAM_WEBHOOK_VERIFY_TOKEN`.
- Berlangganan field: `messages`, `messaging_postbacks`, `messaging_referral`.

## 4. `.env` di server

```dotenv
GAME_INSTAGRAM=akunresmi        # tanpa @
GAME_DM_KEYWORD=GELUD
INSTAGRAM_GRAPH_VERSION=v26.0
INSTAGRAM_APP_SECRET=...
INSTAGRAM_ACCESS_TOKEN=...
INSTAGRAM_ACCOUNT_ID=...
INSTAGRAM_WEBHOOK_VERIFY_TOKEN=...
AUTH_DEV_LOGIN=false
```

Lalu `php artisan config:cache`.

## 5. Uji

1. Dari akun Instagram lain (bukan akun resmi), follow akun resmi lalu kirim DM `GELUD`.
2. Dalam beberapa detik balasan berisi link `/masuk/...` datang. Ketuk, kamu masuk.
3. Di Chrome HP, buka situs → Masuk → **Masuk lewat Instagram** → Instagram terbuka → kirim → kembali ke Chrome, halaman masuk otomatis.

Kalau balasan tidak datang: `php artisan ig:process`, lalu `php artisan ig:send`, dan lihat kolom `error` di tabel `ig_events` / `ig_outbox`.

## Cara kerja singkat

| Jalur | Pemain | Server |
| --- | --- | --- |
| Sekali ketuk | Ketuk tombol di web → Instagram terbuka → kirim | Webhook membawa `ref` + IGSID → username dari Profile API → browser yang menunggu langsung masuk |
| Link DM | Kirim `GELUD` | Petarung diaktifkan, balasan berisi link sekali pakai (2 jam) |
| Kode | Ketik username di web, kirim kode yang tampil lewat DM | Kode cocok **dan** username pengirim sama dengan yang diketik → browser masuk |
| Manual | Sama seperti kode | Admin mengetik kode di panel admin → Login manual (atau `php artisan login:approve KODE`) |

Identitas yang dipegang adalah **IGSID** dari Meta, bukan username yang diketik, jadi nama orang lain tidak bisa diklaim. Kalau pemain mengganti username Instagram, petarungnya ikut berganti nama saat ia DM lagi.
