# Aturan mesin game

File di folder ini dipakai **bersama** oleh server (PHP, `app/Game/`) dan browser (JavaScript, `resources/js/game/`).
Keduanya harus menghasilkan pertarungan yang identik dari input yang sama (stat, gaya, seed, versi).

| File | Isi | Boleh diubah? |
| --- | --- | --- |
| `rules.v2.json` | **Aktif.** Aturan KAMPUNG TENDANG: 10 atribut, 5 archetype, 8 teknik, pertahanan, napas, rusak kaki, Meter Bara, 8 jurus, skor ronde. Mesin: `app/Game/FightSimulatorV2.php` + `resources/js/game/simulate2.js`. Diperiksa `app/Game/RulesSchema.php` saat dimuat | **Tidak**, setelah dipakai di produksi (buat `rules.v3.json`) |
| `rules.v1.json` | Arsip era tinju. Rumus pertarungan versi 1: pukulan, peluang kena, damage, knockdown, poin ronde, bobot OVR, cara membuat stat dasar dari username | **Tidak**, setelah dipakai di produksi |
| `content.json` | Daftar julukan, kota, kelas bawaan, NPC | Tambah di **akhir** daftar saja |
| `looks.json` | Katalog tampilan avatar: bagian, item, warna, level buka, `defaultPool` untuk tampilan bawaan | Tambah item kapan saja; **jangan** ganti/hapus `id` yang sudah dipakai. Mengubah `defaultPool` mengubah wajah bawaan semua petarung yang belum menyimpan tampilan |

Tampilan bawaan dari username juga dicek kesetaraannya (PHP = JS) lewat bagian `looks` di `tests/fixtures/engine_vectors.json`.

## Mengubah rumus

1. Salin `rules.v2.json` menjadi `rules.v3.json`, ubah `"version": 3` dan angkanya (cek keseimbangan dengan `php tools/balance-v2.php`).
2. Tambahkan versi 2 di `resources/js/game/index.js` (`RULES`) dan di daftar versi `tools/make-vectors.php`.
3. Ubah `game.engine.version` di `config/game.php` menjadi `2`.
4. Setel keseimbangan: `php tools/engine-stats.php 2 20000 6`.
5. Buat ulang vektor: `php tools/make-vectors.php`. Pastikan vektor versi 1 **tidak berubah** di git diff.
6. Jalankan `php tools/check-engine.php` dan `npm run test:engine`.

Pertarungan lama menyimpan versinya, jadi tetap diputar ulang dengan rumus lamanya.

## Hasil penyetelan versi 1

Diukur dengan `php tools/engine-stats.php 1 20000 6`: KO sekitar 40%, menang angka sekitar 59%, seri sekitar 5%, 0,5 knockdown per laga.
Petarung dengan OVR lebih rendah menang sekitar 36% laga, dan sekitar 20% bila selisih OVR 8 atau lebih.
