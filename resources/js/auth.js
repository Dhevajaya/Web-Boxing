// Status masuk pemain + modal "Masuk" dengan semua jalurnya.
import { $, esc, api, normalizeUsername } from './ui.js';
import { navigate, render } from './router.js';

export const session = { me: null, options: null, ready: null };

const listeners = new Set();
export const onAuthChange = (fn) => listeners.add(fn);
let lastUser;
const emit = () => {
  const user = session.me?.username ?? null;
  if (user === lastUser) return;
  const first = lastUser === undefined;
  lastUser = user;
  listeners.forEach((fn) => fn(session.me, first));
};

export function loadSession() {
  session.ready = Promise.all([
    api('/api/me').then((r) => (session.me = r.data)).catch(() => (session.me = null)),
    api('/api/auth/options').then((r) => (session.options = r.data)).catch(() => (session.options = null)),
  ]).then(() => {
    emit();
    return session;
  });
  return session.ready;
}

export async function logout(everywhere = false) {
  await api('/api/me/logout', { method: 'POST', body: JSON.stringify({ everywhere }) });
  session.me = null;
  emit();
  navigate('/');
}

const isInstagramBrowser = () => /Instagram/i.test(navigator.userAgent);
const isMobile = () => /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

// ------------------------------------------------------------------ modal

let dialog = null;
let pollTimer = null;

function stopPolling() {
  clearTimeout(pollTimer);
  pollTimer = null;
}

function poll(id, onUpdate) {
  stopPolling();
  const tick = async () => {
    try {
      const { data } = await api(`/api/auth/status/${id}`);
      if (data.status === 'pending') {
        pollTimer = setTimeout(tick, 2000);
      } else {
        onUpdate(data);
      }
    } catch (e) {
      onUpdate({ status: e.status === 404 ? 'lost' : 'error' });
    }
  };
  pollTimer = setTimeout(tick, 1500);
}

function finishLogin(username) {
  stopPolling();
  dialog?.close();
  loadSession().then(() => navigate(`/${username}?selamat=1`));
}

const STATUS_TEXT = {
  not_follower: (o) => `Akun itu belum follow @${o.instagram}. Follow dulu, lalu coba lagi.`,
  unreadable: (o) => `Kami belum bisa membaca akunmu. Ketik ${o.keyword} di DM, lalu coba lagi.`,
  expired: () => 'Waktunya habis. Coba lagi ya.',
  rejected: () => 'Permintaan ditolak. Pastikan kode dikirim dari akun dengan username yang sama.',
  used: () => 'Permintaan ini sudah dipakai. Muat ulang halaman.',
  lost: () => 'Permintaan tidak ditemukan di browser ini. Coba lagi.',
  error: () => 'Koneksi bermasalah. Coba lagi.',
};

export async function openLogin() {
  await session.ready;
  if (session.me) return navigate(`/${session.me.username}`);
  const o = session.options ?? { instagram: window.GAME?.brand?.instagram, keyword: window.GAME?.brand?.keyword, instagram_live: false };

  dialog?.remove();
  dialog = document.createElement('dialog');
  dialog.className = 'modal';
  dialog.innerHTML = `
    <form method="dialog" class="modal-x"><button aria-label="Tutup">×</button></form>
    <h2>Masuk</h2>
    <p class="sub">Tanpa password. Kamu masuk lewat DM Instagram dengan akun yang mau jadi petarung.</p>
    ${isInstagramBrowser() ? `<p class="note">Kamu sedang di browser Instagram. Sesi di sini terpisah dari Chrome/Safari. Untuk main di browser biasa, buka situs ini di Chrome/Safari dulu.</p>` : ''}
    ${o.dm_busy ? `<p class="note">⏳ Balasan DM sedang antre karena ramai. Pakai <b>masuk pakai kode</b> di bawah agar lebih cepat.</p>` : ''}
    ${o.instagram_live === false && !o.dev_login ? `<p class="note">Integrasi Instagram belum aktif di server ini. Masuk pakai kode lalu tunggu admin menyetujui.</p>` : ''}

    ${o.ref_login ? `
    <section class="m-step">
      <button class="btn btn-accent btn-block" id="refBtn" type="button">Masuk lewat Instagram</button>
      <p class="hint">Instagram terbuka, ketuk <b>Kirim</b> (atau tombol ${esc(o.keyword)}), lalu kembali ke sini. ${isMobile() ? '' : 'Link ini paling lancar dibuka di HP.'}</p>
      <p class="m-status" id="refStatus" hidden></p>
    </section>` : ''}

    ${o.instagram_live ? `
    <section class="m-step">
      <h3>Atau lewat link di DM</h3>
      <ol class="steps">
        <li>Follow <b>@${esc(o.instagram)}</b></li>
        <li>Kirim DM <b>${esc(o.keyword)}</b></li>
        <li>Ketuk link masuk di balasannya (berlaku ${Math.round((o.link_ttl_min ?? 120) / 60)} jam)</li>
      </ol>
      <a class="btn btn-ghost btn-block" href="${esc(o.dm_url)}" target="_blank" rel="noopener">Kirim DM ${esc(o.keyword)}</a>
    </section>` : ''}

    <section class="m-step">
      <h3>${o.instagram_live ? 'Belum dapat balasan? Pakai kode' : 'Masuk pakai kode'}</h3>
      <form class="field" id="codeForm" autocomplete="off">
        <span class="at">@</span>
        <input name="username" autocapitalize="none" spellcheck="false" placeholder="username IG kamu" aria-label="Username Instagram">
        <button class="btn btn-accent btn-sm" type="submit">Minta kode</button>
      </form>
      <div id="codeBox" hidden>
        <p class="hint">Kirim kode ini lewat DM ke <b>@${esc(o.instagram)}</b> dari akun <b id="codeUser"></b>. Halaman ini masuk otomatis.</p>
        <div class="code-row"><code id="codeVal"></code><button class="btn btn-ghost btn-sm" id="copyBtn" type="button">Salin</button></div>
        <a class="btn btn-ghost btn-block" href="${esc(o.dm_url)}" target="_blank" rel="noopener">Buka DM @${esc(o.instagram)}</a>
      </div>
      <p class="m-status" id="codeStatus" hidden></p>
    </section>

    ${o.dev_login ? `
    <section class="m-step dev">
      <h3>Mode pengembang</h3>
      <form class="field" id="devForm" autocomplete="off">
        <span class="at">@</span>
        <input name="username" autocapitalize="none" spellcheck="false" placeholder="username apa saja" aria-label="Username untuk mode pengembang">
        <button class="btn btn-ghost btn-sm" type="submit">Masuk</button>
      </form>
      <p class="hint">Hanya muncul saat APP_ENV=local dan AUTH_DEV_LOGIN=true.</p>
    </section>` : ''}
  `;
  document.body.append(dialog);
  dialog.addEventListener('close', () => { stopPolling(); dialog.remove(); dialog = null; });
  dialog.showModal();

  const show = (el, text, ok = false) => {
    el.hidden = false;
    el.className = `m-status${ok ? ' ok' : ''}`;
    el.textContent = text;
  };
  const onResult = (statusEl) => (d) => {
    if (d.status === 'ok') return finishLogin(d.username);
    show(statusEl, (STATUS_TEXT[d.status] ?? STATUS_TEXT.error)(o));
  };

  $('#refBtn', dialog)?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const st = $('#refStatus', dialog);
    btn.disabled = true;
    try {
      const { data } = await api('/api/auth/ref/start', { method: 'POST' });
      show(st, 'Menunggu konfirmasi dari Instagram…', true);
      poll(data.id, onResult(st));
      // HP: buka aplikasi Instagram di tempat; desktop: tab baru agar halaman ini tetap menunggu.
      if (isMobile()) location.href = data.url;
      else {
        const w = window.open(data.url, '_blank');
        if (w) w.opener = null;
      }
    } catch (err) {
      show(st, err.message);
    } finally {
      btn.disabled = false;
    }
  });

  $('#codeForm', dialog).addEventListener('submit', async (e) => {
    e.preventDefault();
    const st = $('#codeStatus', dialog);
    const username = normalizeUsername(e.currentTarget.username.value);
    if (!username) return show(st, 'Username Instagram tidak valid.');
    try {
      const { data } = await api('/api/auth/code/start', { method: 'POST', body: JSON.stringify({ username }) });
      $('#codeUser', dialog).textContent = `@${data.username}`;
      $('#codeVal', dialog).textContent = data.code;
      $('#codeBox', dialog).hidden = false;
      show(st, 'Menunggu kode masuk lewat DM…', true);
      poll(data.id, onResult(st));
    } catch (err) {
      show(st, err.status === 429 ? 'Terlalu sering. Tunggu beberapa menit.' : err.message);
    }
  });

  $('#copyBtn', dialog)?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    try {
      await navigator.clipboard.writeText($('#codeVal', dialog).textContent);
      btn.textContent = 'Tersalin';
    } catch { /* clipboard ditolak: biarkan pemain menyalin manual */ }
  });

  $('#devForm', dialog)?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = normalizeUsername(e.currentTarget.username.value);
    if (!username) return;
    const { data } = await api('/api/auth/dev', { method: 'POST', body: JSON.stringify({ username }) });
    finishLogin(data.username);
  });
}

// Setelah login/keluar, halaman yang sedang dibuka di-render ulang agar tombol pemilik muncul.
onAuthChange((me, first) => { if (!first) render(); });
