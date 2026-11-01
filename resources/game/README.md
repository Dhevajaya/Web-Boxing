# Aturan mesin game

File di folder ini dipakai **bersama** oleh server (PHP, `app/Game/`) dan browser (JavaScript, `resources/js/game/`).
Keduanya harus menghasilkan pertarungan yang identik dari input yang sama (stat, gaya, seed, versi).

| File | Isi | Boleh diubah? |
| --- | --- | --- |
| `rules.v1.json` | Rumus pertarungan versi 1: pukulan, peluang kena, damage, knockdown, poin ronde, bobot OVR, cara membuat stat dasar dari username | **Tidak**, setelah dipakai di produksi |
| `content.json` | Daftar julukan, kota, kelas bawaan, NPC | Tambah di **akhir** daftar saja |

## Mengubah rumus

1. Salin `rules.v1.json` menjadi `rules.v2.json`, ubah `"version": 2` dan angkanya.
2. Tambahkan versi 2 di `resources/js/game/index.js` (`RULES`) dan di daftar versi `tools/make-vectors.php`.
3. Ubah `game.engine.version` di `config/game.php` menjadi `2`.
4. Setel keseimbangan: `php tools/engine-stats.php 2 20000 6`.
5. Buat ulang vektor: `php tools/make-vectors.php`. Pastikan vektor versi 1 **tidak berubah** di git diff.
6. Jalankan `php tools/check-engine.php` dan `npm run test:engine`.

Pertarungan lama menyimpan versinya, jadi tetap diputar ulang dengan rumus lamanya.

## Hasil penyetelan versi 1

Diukur dengan `php tools/engine-stats.php 1 20000 6`: KO sekitar 40%, menang angka sekitar 59%, seri sekitar 5%, 0,5 knockdown per laga.
Petarung dengan OVR lebih rendah menang sekitar 36% laga, dan sekitar 20% bila selisih OVR 8 atau lebih.
