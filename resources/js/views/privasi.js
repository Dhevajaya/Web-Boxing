import { esc } from '../ui.js';

// Kebijakan privasi & ketentuan main. Teks disesuaikan dengan data yang benar-benar dikumpulkan game ini.
export function privasiView(el) {
  const b = window.GAME?.brand ?? {};
  const name = esc(b.name ?? 'Game ini');
  const ig = esc(b.instagram ?? '');
  const mail = b.business_email ? `<a href="mailto:${esc(b.business_email)}">${esc(b.business_email)}</a>` : `DM <b>@${ig}</b>`;
  document.title = `Privasi · ${b.name ?? ''}`;
  el.innerHTML = `
    <section class="hero"><span class="eyebrow">Berlaku sejak 1 November 2026</span><h1>Priva<em>si</em></h1></section>
    <article class="card prose">
      <h2>Ringkasnya</h2>
      <p>${name} adalah game komunitas. Kami hanya menyimpan yang dibutuhkan agar kamu bisa bertanding: username Instagram, ID akun dari Instagram, dan data permainanmu. Kami tidak meminta password dan tidak menjual data.</p>

      <h2>Data yang kami simpan</h2>
      <ul>
        <li><b>Username Instagram</b> dan <b>ID akun Instagram</b> (IGSID) yang dikirim Meta saat kamu mengirim DM ke @${ig}. Dipakai untuk membuktikan akun itu milikmu.</li>
        <li><b>Status follow</b> ke @${ig} saat masuk (bila diwajibkan).</li>
        <li><b>Data permainan</b>: tampilan avatar, julukan, kota, poin stat, XP, rekor laga, latihan, sasana, piala, lencana.</li>
        <li><b>Pesan chat</b> di Markas, disimpan paling lama 7 hari.</li>
        <li><b>Sesi masuk</b>: cookie sesi dan jenis browser, agar kamu bisa "keluar dari semua perangkat".</li>
        <li><b>Donasi</b> (bila kamu mendukung): nama donatur, jumlah, dan pesan yang dikirim platform donasi.</li>
        <li><b>Alamat IP</b> hanya disimpan dalam bentuk hash untuk mencegah penyalahgunaan.</li>
      </ul>

      <h2>Yang tidak kami lakukan</h2>
      <ul>
        <li>Membaca DM selain pesan yang kamu kirim ke @${ig} untuk masuk.</li>
        <li>Memposting atau mengirim pesan atas namamu.</li>
        <li>Menjual atau membagikan data ke pengiklan.</li>
      </ul>

      <h2>Yang terlihat publik</h2>
      <p>Siapa pun bisa melihat kartu petarung dari username apa pun: avatar, julukan, stat, rekor, dan riwayat laga. Kartu untuk username yang belum daftar dibuat otomatis dari username saja, tanpa data dari Instagram. Pendukung bisa menyembunyikan namanya dari daftar pendukung.</p>

      <h2>Menghapus data</h2>
      <p>Hubungi ${mail} dari akun yang sama untuk meminta data petarungmu dihapus. Kami memprosesnya paling lambat 30 hari. Kamu juga bisa berhenti mengikuti Fight Night cukup dengan tidak membuka game selama 14 hari.</p>

      <h2>Aturan main</h2>
      <ul>
        <li>Satu akun Instagram = satu petarung. Akun ganda, skrip otomatis, atau mengakali skor latihan bisa disuspend.</li>
        <li>Julukan, kota, dan chat tidak boleh berisi kata kasar, SARA, atau menyamar sebagai admin.</li>
        <li>Admin boleh mereset julukan/tampilan, membisukan chat, atau menangguhkan akun yang melanggar.</li>
        <li>${name} tidak berafiliasi dengan Instagram atau Meta.</li>
      </ul>
    </article>`;
}
