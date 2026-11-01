// Markas: chat Alun-alun, chat sasana, dan cari lawan sparing. Chat memakai polling tiap 4 detik.
import { $, esc, api, sendChallenge } from '../ui.js';
import { avatar } from '../game/index.js';
import { session, openLogin } from '../auth.js';
import { navigate } from '../router.js';

const POLL_MS = 4000;
const time = (iso) => new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: window.GAME?.brand?.timezone });

export function markasView(el, initial = 'alun') {
  let alive = true;
  let tab = initial;
  let poll = null;
  let lastId = 0;
  let room = null;
  document.title = `Markas · ${window.GAME?.brand?.name ?? ''}`;
  const max = window.GAME?.chat?.max_len ?? 80;

  el.innerHTML = `
    <section class="hero"><span class="eyebrow">Tempat nongkrong petarung</span><h1>Mar<em>kas</em></h1></section>
    <div class="tabs" role="tablist" style="grid-template-columns:repeat(3,1fr)">
      <button role="tab" type="button" data-t="alun">Alun-alun</button>
      <button role="tab" type="button" data-t="sasana">Sasana</button>
      <button role="tab" type="button" data-t="spar">Cari lawan</button>
    </div>
    <section class="card mk" id="pane"></section>`;
  el.querySelectorAll('[data-t]').forEach((b) => b.addEventListener('click', () => show(b.dataset.t)));

  function stop() { clearTimeout(poll); poll = null; }

  function show(t) {
    stop();
    tab = t;
    history.replaceState({}, '', t === 'alun' ? '/markas' : `/markas?ruang=${t}`);
    el.querySelectorAll('[data-t]').forEach((b) => b.setAttribute('aria-selected', b.dataset.t === t ? 'true' : 'false'));
    if (t === 'spar') return spar();
    if (t === 'sasana') {
      if (!session.me) return gate('Masuk dulu untuk membuka ruang sasana.');
      return api('/api/me/profile', { cache: 'no-store' }).then(({ data }) => {
        if (!alive || tab !== 'sasana') return;
        if (!data.sasanaLocked || !data.sasana) {
          $('#pane', el).innerHTML = '<p class="sub">Kamu belum bergabung dengan sasana season ini.</p><a class="btn btn-accent btn-block" href="/profil/sasana" data-link>Pilih sasana</a>';
          return;
        }
        chat(`sasana:${data.sasana.slug}`, `${data.sasana.icon} Markas ${data.sasana.name}`);
      });
    }
    return chat('alun', 'Alun-alun');
  }

  function gate(text) {
    $('#pane', el).innerHTML = `<p class="sub">${esc(text)}</p><button class="btn btn-accent btn-block" type="button" id="lg">Masuk / daftar</button>`;
    $('#lg', el).onclick = () => openLogin();
  }

  function msgHtml(m) {
    const mine = session.me?.username === m.from?.username;
    return `<li class="msg${mine ? ' mine' : ''}" data-id="${m.id}">
      <a class="mini-av" href="/${esc(m.from?.username ?? '')}" data-link>${m.from ? avatar(m.from.look, { size: 28 }) : ''}</a>
      <div class="msg-b"><div class="msg-h"><a href="/${esc(m.from?.username ?? '')}" data-link><b>@${esc(m.from?.username ?? '?')}</b></a>${m.from?.supporter ? ' <span class="sup">♥</span>' : ''}<small>Lv${m.from?.level ?? 1} · ${esc(time(m.at))}</small>
        ${session.me && !mine ? '<button class="rep" type="button" title="Laporkan pesan" aria-label="Laporkan pesan">⚑</button>' : ''}</div>
        <p>${esc(m.body)}</p></div></li>`;
  }

  function chat(r, title) {
    room = r;
    lastId = 0;
    const pane = $('#pane', el);
    pane.innerHTML = `
      <div class="mk-h"><b>${esc(title)}</b><small class="sub">Sopan ya. 3 laporan = pesan hilang & bisu 24 jam.</small></div>
      <ul class="msgs" id="msgs"><li class="loading">Memuat…</li></ul>
      ${session.me ? `<form class="field mk-in" id="send" autocomplete="off">
          <input name="body" maxlength="${max}" placeholder="Tulis pesan…" aria-label="Pesan">
          <small id="cnt">0/${max}</small>
          <button class="btn btn-accent btn-sm" type="submit">Kirim</button>
        </form><p class="m-status" id="cErr" hidden></p>`
        : '<button class="btn btn-ghost btn-block" type="button" id="lg">Masuk untuk ikut ngobrol</button>'}`;
    $('#lg', pane)?.addEventListener('click', () => openLogin());

    const list = $('#msgs', pane);
    const nearBottom = () => list.scrollHeight - list.scrollTop - list.clientHeight < 60;
    const append = (msgs, force = false) => {
      const stick = force || nearBottom();
      list.querySelector('.loading')?.remove();
      for (const m of msgs) {
        if (list.querySelector(`[data-id="${m.id}"]`)) continue;
        list.insertAdjacentHTML('beforeend', msgHtml(m));
        lastId = Math.max(lastId, m.id);
      }
      while (list.children.length > 150) list.firstElementChild.remove();
      if (!list.children.length) list.innerHTML = '<li class="empty">Belum ada pesan. Sapa yang lain!</li>';
      if (stick) list.scrollTop = list.scrollHeight;
    };

    const tick = async () => {
      if (!alive || room !== r) return;
      if (document.visibilityState === 'visible') {
        try {
          const { data } = await api(`/api/chat/${encodeURIComponent(r)}${lastId ? `?after=${lastId}` : ''}`);
          if (!alive || room !== r) return;
          if (lastId === 0) list.innerHTML = '';
          append(data.messages, lastId === 0);
          for (const id of data.hidden) list.querySelector(`[data-id="${id}"]`)?.remove();
          const muted = data.me?.muted_until;
          const form = $('#send', pane);
          if (form) {
            form.querySelector('input').disabled = !!muted;
            form.querySelector('input').placeholder = muted ? `Kamu dibisukan sampai ${time(muted)}` : 'Tulis pesan…';
          }
        } catch (e) {
          if (lastId === 0) list.innerHTML = `<li class="empty">${esc(e.message)}</li>`;
        }
      }
      poll = setTimeout(tick, POLL_MS);
    };
    tick();

    list.addEventListener('click', async (e) => {
      const b = e.target.closest('.rep');
      if (!b) return;
      const li = b.closest('.msg');
      if (!confirm('Laporkan pesan ini sebagai tidak pantas?')) return;
      try {
        const { data } = await api(`/api/chat/messages/${li.dataset.id}/report`, { method: 'POST' });
        if (data.hidden) li.remove();
        else { b.disabled = true; b.textContent = '✓'; }
      } catch (err) { b.title = err.message; }
    });

    const form = $('#send', pane);
    if (form) {
      const input = form.querySelector('input');
      input.addEventListener('input', () => { $('#cnt', pane).textContent = `${input.value.length}/${max}`; });
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        const btn = form.querySelector('button');
        btn.disabled = true;
        const err = $('#cErr', pane);
        try {
          const { data } = await api(`/api/chat/${encodeURIComponent(r)}`, { method: 'POST', body: JSON.stringify({ body }) });
          if (!alive) return;
          input.value = '';
          $('#cnt', pane).textContent = `0/${max}`;
          err.hidden = true;
          list.querySelector('.empty')?.remove();
          append([data], true);
        } catch (ex) {
          if (err.isConnected) { err.hidden = false; err.textContent = ex.message; }
        } finally {
          btn.disabled = false;
          input.focus();
        }
      });
    }
  }

  function spar() {
    room = null;
    if (!session.me) return gate('Masuk dulu untuk mencari lawan sparing.');
    const pane = $('#pane', el);
    pane.innerHTML = '<p class="loading">Mencari lawan…</p>';
    api('/api/spar').then(({ data }) => {
      if (!alive || tab !== 'spar') return;
      pane.innerHTML = `
        <p class="sub">Petarung aktif dengan bintang setara denganmu (★ ${data.stars} ± ${data.range}). Daftar berganti tiap hari.</p>
        <ul class="spar">${data.rows.map((r) => `<li>
          <a class="mini-av lg" href="/${esc(r.username)}" data-link>${avatar(r.look, { size: 44 })}</a>
          <a class="rk-id" href="/${esc(r.username)}" data-link><b>@${esc(r.username)}</b><small>${r.sasana ? `${esc(r.sasana.icon)} ` : ''}Lv${r.level} · ★ ${r.stars} · NILAI ${r.ovr}</small></a>
          <button class="btn btn-accent btn-sm" type="button" data-u="${esc(r.username)}">Tantang</button></li>`).join('') || '<li class="empty">Belum ada lawan setara yang aktif. Coba lagi nanti.</li>'}</ul>
        <p class="m-status" id="sErr" hidden></p>`;
      pane.querySelectorAll('[data-u]').forEach((b) => b.addEventListener('click', async () => {
        b.disabled = true;
        const err = await sendChallenge(b.dataset.u, navigate);
        if (err && b.isConnected) {
          const p = $('#sErr', pane);
          p.hidden = false;
          p.textContent = err;
          b.disabled = false;
        }
      }));
    }).catch((e) => { pane.innerHTML = `<p class="m-status">${esc(e.message)}</p>`; });
  }

  show(tab);
  return { cleanup: () => { alive = false; stop(); } };
}
