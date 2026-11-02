import { esc } from '../ui.js';

const PAGES = {
  profil: ['Identitas', 'Masuk lewat DM Instagram untuk melihat dan mengatur petarungmu.'],
};

// Halaman pengisi: profil sebelum masuk, dan halaman yang tidak dikenal.
export function soonView(el, page) {
  const [title, desc] = PAGES[page] ?? ['Tidak ditemukan', 'Halaman ini tidak ada. Mungkin tautannya salah ketik.'];
  el.innerHTML = `
    <section class="soon">
      <span class="eyebrow">${page ? 'Masuk dulu' : '404'}</span>
      <h1>${esc(title)}</h1>
      <p>${esc(desc)}</p>
      <p><a class="btn btn-ghost" href="/" data-link>Ke beranda</a></p>
    </section>`;
}
