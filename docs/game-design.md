# Aturan game KAMPUNG TENDANG

Kickboxing kampung original (bukan regulasi resmi federasi mana pun). Mesin aktif: **v2** (`resources/game/rules.v2.json`).
Laga lama era tinju tetap tersimpan dan diputar dengan mesin v1 sebagai arsip.

Ringkasan aturan yang benar-benar diterapkan kode. Angka di sini adalah bawaan `config/game.php`;
owner bisa mengubah sebagian dari panel admin → **Pengaturan** tanpa deploy ulang.

## Petarung

- Setiap username Instagram punya berkas: gaya bertarung bawaan, **10 atribut** dasar (35–75), julukan, kota, kelas, dan **tampilan avatar**
  diturunkan dari hash username. Sama persis di PHP dan JavaScript (diuji lewat `tests/fixtures/engine_vectors.json`).
- Pemain yang masuk bisa mengatur:
  - **Koleksi** (tampilan): 8 bagian (kulit, rambut, warna rambut, wajah, kumis/jenggot, sarung, celana, aksesori).
    Sebagian item terbuka di level tertentu, sebagian khusus pendukung. Katalog: `resources/game/looks.json`.
  - **Julukan & kota**: 3–24 huruf, tanpa kata terlarang; jeda ganti 24 jam (ganti pertama bebas).
  - **Gaya bertarung** (archetype): Jago Gebuk, Raja Tendang, Ahli Balas, Jaga Jarak, Petarung Komplet. Mengubah bias atribut,
    kecenderungan aksi, sifat pasif, dan jurus khas. Ganti tiap 7 hari (ganti pertama bebas).
  - **Jurus Andalan**: pasang 2. Jurus khas archetype terbuka di Lv 1, Patah Ranting / Sambar Badan di Lv 5, Pukulan Pamungkas di Lv 10.
  - **Poin atribut**: 20 poin di Level 1, +4 per level, bonus +5 di Level 5/10/15/20 (total 116 di Level 20).
    Menambah poin selalu boleh; memindah poin yang sudah dipasang hanya sekali per 7 hari. Stat maksimal 99.
- **Level**: XP ke level berikutnya = 50 + 25 × (level − 1), maksimal Level 20.

## Mesin laga v2 (ringkas)

- 3 ronde × 16 tick. Tiap tick, petarung yang lebih cepat lebih sering mengambil inisiatif lalu memilih aksi menurut archetype:
  pukulan (jab, cross, hook, uppercut), tendangan (low kick, tendangan badan, tendangan kepala, tendangan dorong), atur napas, atau atur jarak.
- Jarak abstrak dekat/sedang/jauh menentukan teknik yang mungkin. Tendangan dorong mendorong lawan menjauh.
- Pertahanan otomatis: **guard** (kerusakan tinggal 40%, tanpa skor), **hindar** (meleset), **check** khusus low kick (kaki penyerang yang rusak).
- **Napas**: tiap teknik memakai napas; napas rendah menurunkan akurasi dan kerusakan. **Rusak kaki** dari low kick: 50% = lambat, 100% = TKO.
- **Meter Bara** terisi dari serangan bersih, pertahanan, dan terkena serangan. Saat penuh, jurus keluar (maks 1 per ronde).
- Skor ronde: tangan 1, tendangan badan 2, tendangan kepala 3, jatuh +3. Ronde 10–9 (−1 tiap jatuh); poin sama → kerusakan lebih banyak menang ronde.
- Hasil: KO, TKO (3 jatuh dalam satu ronde atau kaki rusak), atau Angka; seri dipecah kerusakan total lalu serangan bersih.
- Keseimbangan diuji dengan `php tools/balance-v2.php` (10.000 laga: ±35% KO/TKO, tiap archetype 47–53%).

## Malam Gelanggang (otomatis, dulu Fight Night)

- Jam 00.00, 06.00, 12.00, 18.00 WIB. Ikut otomatis bila aktif (membuka game) dalam 14 hari terakhir dan tidak disuspend.
- 60 menit sebelum slot: pemasangan menurut bintang lalu NILAI (OVR), dengan sedikit acak; lawan yang sama dengan laga sebelumnya dihindari;
  jumlah ganjil → satu pemain melawan NPC. Laga dihitung lalu disembunyikan sampai jam slot.
- Hasil: menang +10 XP (+5 bila KO/TKO), seri +5, kalah +3. Bintang season: menang +1, kalah −1 (minimal 0).
- Rumus di `resources/game/rules.v2.json` (lihat `resources/game/README.md`).

## Ajak Duel (tantangan)

- Pemain menantang petarung terdaftar mana pun (termasuk NPC) dan langsung bertanding. Link `/t/{kode}` bisa dibagikan.
- Hanya penantang yang dapat XP: 50% XP Fight Night, untuk 5 tantangan pertama per hari. Tidak mengubah bintang/rekor season.
- Batas 20 tantangan per hari, jeda 10 menit untuk lawan yang sama.

## Latihan

- Mini-game 30 detik di sasana (petinju memakai tampilan pemain):
  - **Samsak** (Tenaga): garis bergerak di bilah; PUKUL di zona emas = PERFECT (100), hijau = BAGUS (50), meleset = -40.
    TENDANG = 140 / 70 / -60 dengan jeda lebih lama.
  - **Hindari Pelatih** (Kelincahan): `!` merah di kepala = NUNDUK, `!` biru di perut = MUNDUR, `!` hijau di kaki = CHECK, `?` kuning = tipuan (diam).
  - **Tahan Serangan** (Pertahanan): `!` = TAHAN sampai pukulan mendarat (mulai di 35% akhir aba-aba = PARRY), `?` = jangan tahan;
    menahan dari sebelum aba-aba = GUARD JEBOL.
  - Lompat Tali dan Sit-up tampil "Segera" (`soon: true`).
- Combo menggandakan poin sampai 2×, dan permainan makin cepat.
- **XP**: skor terbaik hari ini per game ÷ `xp_div`; hanya kenaikan yang dibayar, total semua game maks **30 XP/hari**,
  plus **streak +5 XP** di sesi pertama tiap hari.
- **Bintang** 1–3 (ambang `stars`) = **bonus stat +1/+2/+3** untuk semua laga (Fight Night, tantangan, piala) pada tanggal WIB yang sama.
- Rekor dan **Top 100 per game per season** (`GET /api/training/{game}/top`).
- Server menolak skor yang tidak wajar: token sekali pakai, durasi, maks 15 ketukan/detik, skor maks, dan skor ≤ (ketukan + free) × per_tap.

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

## Migrasi dari era tinju

1. Backup database.
2. `php artisan migrate --force` (hanya menambah kolom).
3. `php artisan game:reset-competitive --dry-run` lalu `php artisan game:reset-competitive`.
   Menutup season lama (era tinju, peringkat & lencana juara tetap), membuka season baru, menolkan XP/level/poin/archetype/jurus,
   memberi lencana **Veteran Gelanggang** (tanpa bonus stat), menjaga keanggotaan geng. Laga lama jadi arsip.
