# Aturan game

Ringkasan aturan yang benar-benar diterapkan kode. Angka di sini adalah bawaan `config/game.php`;
owner bisa mengubah sebagian dari panel admin → **Pengaturan** tanpa deploy ulang.

## Petarung

- Setiap username Instagram punya kartu: gaya, stat dasar (35–75), julukan, kota, kelas, dan **tampilan avatar**
  diturunkan dari hash username. Sama persis di PHP dan JavaScript (diuji lewat `tests/fixtures/engine_vectors.json`).
- Pemain yang masuk bisa mengatur:
  - **Tampilan**: 8 bagian (kulit, rambut, warna rambut, wajah, kumis/jenggot, sarung tinju, celana, aksesori).
    Sebagian item terbuka di level tertentu, sebagian khusus pendukung. Katalog: `resources/game/looks.json`.
  - **Julukan & kota**: 3–24 huruf, tanpa kata terlarang; jeda ganti 24 jam (ganti pertama bebas).
  - **Poin stat**: 10 poin di Level 1, +2 per level, bonus +3 di Level 5/10/15/20 (total 60 di Level 20).
    Menambah poin selalu boleh; memindah poin yang sudah dipasang hanya sekali per 7 hari. Stat maksimal 99.
- **Level**: XP ke level berikutnya = 50 + 25 × (level − 1), maksimal Level 20.

## Fight Night (otomatis)

- Jam 00.00, 06.00, 12.00, 18.00 WIB. Ikut otomatis bila aktif (membuka game) dalam 14 hari terakhir dan tidak disuspend.
- 60 menit sebelum slot: pemasangan menurut bintang lalu NILAI (OVR), dengan sedikit acak; lawan yang sama dengan laga sebelumnya dihindari;
  jumlah ganjil → satu pemain melawan NPC. Laga dihitung lalu disembunyikan sampai jam slot.
- Hasil: menang +10 XP (+5 bila KO/TKO), seri +5, kalah +3. Bintang season: menang +1, kalah −1 (minimal 0).
- Laga 3 ronde × 10 pertukaran. Rumus di `resources/game/rules.v1.json` (lihat `resources/game/README.md`).

## Tantangan

- Pemain menantang petarung terdaftar mana pun (termasuk NPC) dan langsung bertanding. Link `/t/{kode}` bisa dibagikan.
- Hanya penantang yang dapat XP: 50% XP Fight Night, untuk 5 tantangan pertama per hari. Tidak mengubah bintang/rekor season.
- Batas 20 tantangan per hari, jeda 10 menit untuk lawan yang sama.

## Latihan

- 5 mini-game 30 detik: Samsak (Pukulan), Hindari (Kecepatan), Lompat tali (Stamina), Tahan (Pertahanan), Sit-up (Daya tahan).
- XP maksimal 20 per game per hari. Skor tinggi memberi **bonus stat +1/+2/+3** untuk semua laga (Fight Night, tantangan, piala)
  pada tanggal WIB yang sama. Ambang skor per game ada di `config/game.php` → `training.games`.
- Server menolak skor yang tidak wajar (token sekali pakai, durasi, batas ketukan 15/detik, skor maksimal).

## Season

- Season ditutup manual oleh owner (admin → Operasi, atau `php artisan season:close`).
- Saat ditutup: peringkat akhir disimpan (Hall of Fame), 10 besar dapat lencana **Juara Season N**,
  semua peserta Season 0 dapat lencana **Perintis**. Season baru: bintang mulai 0, XP dibawa 100%.

## Piala Mingguan

- Pendaftaran Senin 00.00 – Kamis 12.00 WIB. Saat undian, pemain yang aktif 2 hari terakhir ikut otomatis kecuali sudah menekan **Mundur**.
- Minimal 8 peserta, kalau kurang piala batal. Bagan pangkat dua, unggulan dari bintang lalu NILAI, bye untuk unggulan teratas.
- Babak awal Sabtu mulai 19.00 tiap 15 menit; tiga babak terakhir Minggu 19.00 / 19.30 / 20.00 WIB.
- Seri di piala: menang yang sisa tenaganya lebih banyak, lalu unggulan lebih tinggi. Menang laga piala +10 XP (+5 KO).
  Juara dapat lencana **Juara Piala**.

## Sasana

- Empat kubu (nama, ikon, warna, moto di `config/game.php` → `sasana`). Pilih satu per season, tidak bisa pindah sampai season berikutnya.
- Perang Sasana: anggota menyumbang 3 poin per menang, 1 per seri, +1 tiap KO di Fight Night season ini.
- Anggota punya ruang chat sasana sendiri di Markas.

## Markas

- Chat Alun-alun (semua bisa membaca, pemain masuk bisa menulis) dan chat sasana (anggota saja). Polling tiap 4 detik.
- Maksimal 80 karakter, jeda 3 detik, filter kata terlarang. 3 laporan dari pemain berbeda → pesan disembunyikan dan penulis dibisukan 24 jam.
- Pesan disimpan 7 hari. **Cari lawan**: pemain aktif 3 hari terakhir dengan selisih bintang ≤ 3, daftar berganti tiap hari.

## Dukung

- Donasi lewat Saweria (webhook). Pemain menulis `@username` di pesan donasi. Total donasi menentukan tier
  (Tribun ≥ Rp10.000, Ringside ≥ Rp25.000, VIP ≥ Rp50.000), dan tier tidak pernah turun.
- Keuntungan: lencana ♥, tanda ♥ di ranking/chat, item tampilan khusus (sarung & celana Neon, rambut Emas). Pendukung bisa memilih anonim.

## Admin

| Peran | Akses |
| --- | --- |
| editor | Pengumuman Mading |
| moderator | + petarung (suspend, bisu, reset, XP, pendukung), chat, login manual |
| owner | + operasi (Fight Night, season, piala), pengaturan game, akun admin, Dukung, log audit |

Login email + password, opsional autentikator 6 digit (TOTP). Semua tindakan admin tercatat di log audit.
