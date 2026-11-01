import { esc } from '../ui.js';

const PAGES = {
  mading: ['Mading Ring', 'Pengumuman, jadwal, dan kabar Fight Night.', 5],
  latihan: ['Latihan', 'Lima mini-game 30 detik yang menambah XP dan bonus stat.', 8],
  markas: ['Markas', 'Chat Alun-alun, markas sasana, dan cari lawan sparing.', 9],
  piala: ['Piala Mingguan', 'Turnamen gugur massal dengan final Minggu malam.', 7],
  profil: ['Profil', 'Masuk lewat DM Instagram untuk melihat petarungmu.', 2],
  dukung: ['Dukung', 'Dukung server dan dapat lencana pendukung.', 10],
  privasi: ['Privasi', 'Kebijakan privasi akan ditulis sebelum rilis.', 11],
};

export function soonView(el, page) {
  const [title, desc, phase] = PAGES[page] ?? ['Segera', 'Halaman ini sedang dibangun.', '?'];
  el.innerHTML = `
    <section class="soon">
      <span class="eyebrow">Hadir di Fase ${esc(phase)}</span>
      <h1>${esc(title)}</h1>
      <p>${esc(desc)}</p>
    </section>`;
}
