import { $, esc, api } from '../ui.js';
import { avatar } from '../game/index.js';
import { session } from '../auth.js';

export function rankingView(el, initial = 'season') {
  let alive = true;
  let board = initial;
  let page = 1;
  document.title = `Papan Jagoan · ${window.GAME?.brand?.name ?? ''}`;

  el.innerHTML = `
    <section class="hero"><span class="eyebrow tape">Siapa paling ditakuti</span><h1>Papan <em>Jagoan</em></h1></section>
    <div class="tabs two" role="tablist">
      <button role="tab" type="button" data-b="season">Season</button>
      <button role="tab" type="button" data-b="week">Adu Reputasi</button>
      ${window.GAME?.features?.sasana === false ? '' : '<button role="tab" type="button" data-b="sasana">Perang Geng</button>'}
    </div>
    <p class="sub" id="rkSub"></p>
    <div id="rkMe"></div>
    <ol class="rank" id="rkList"></ol>
    <button class="btn btn-ghost btn-block" id="rkMore" type="button" hidden>Muat lebih banyak</button>`;
  el.querySelector('.tabs').style.gridTemplateColumns = `repeat(${el.querySelectorAll('.tabs button').length}, 1fr)`;

  const row = (r) => `
    <li class="${session.me?.username === r.username ? 'me' : ''}">
      <span class="rk">${r.rank}</span>
      <a class="rk-av" href="/${esc(r.username)}" data-link>${avatar(r.look, { size: 44, title: `@${r.username}` })}</a>
      <a class="rk-id" href="/${esc(r.username)}" data-link><b>@${esc(r.username)}${r.supporter ? ' <span class="sup" title="Pendukung">♥</span>' : ''}</b><small>${r.sasana ? `${esc(r.sasana.icon)} ` : ''}Lv${r.level} · ${esc(r.nick)}</small></a>
      <span class="rk-st">${r.stars !== null ? `<b>★ ${r.stars}</b>` : `<b>${r.w} M</b>`}<small>${r.w}-${r.l}-${r.d}${r.ko ? ` · ${r.ko} KO` : ''}</small></span>
    </li>`;

  const sasanaRow = (s, i) => `
    <li><span class="rk">${i + 1}</span>
      <span class="rk-av sas" style="--c:${esc(s.color)}">${esc(s.icon)}</span>
      <span class="rk-id"><b>${esc(s.name)}</b><small>${s.members} anggota · ${esc(s.motto ?? '')}</small></span>
      <span class="rk-st"><b>${s.points} poin</b><small>${s.w} menang · ${s.ko} KO</small></span></li>`;

  async function load(reset) {
    if (reset) {
      page = 1;
      $('#rkList', el).innerHTML = '<li class="loading">Memuat…</li>';
      $('#rkMe', el).innerHTML = '';
    }
    el.querySelectorAll('[data-b]').forEach((b) => b.setAttribute('aria-selected', b.dataset.b === board ? 'true' : 'false'));
    try {
      if (board === 'sasana') {
        const { data } = await api('/api/sasana/war');
        if (!alive) return;
        $('#rkSub', el).textContent = `Perang Geng ${data.season?.name ?? ''}: 3 poin per menang, 1 per seri, +1 tiap KO.`;
        $('#rkList', el).innerHTML = data.rows.map(sasanaRow).join('') || '<li class="empty">Belum ada geng yang bertanding.</li>';
        $('#rkMore', el).hidden = true;
        return;
      }
      const { data } = await api(`/api/ranking?board=${board}&page=${page}`);
      if (!alive) return;
      $('#rkSub', el).textContent = board === 'season'
        ? `${data.season?.name ?? 'Season'} · urut bintang, lalu kemenangan.`
        : `Minggu mulai ${new Date(`${data.week}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long' })} · urut kemenangan, lalu KO.`;
      const html = data.rows.map(row).join('');
      if (reset) $('#rkList', el).innerHTML = html || '<li class="empty">Belum ada laga. Tunggu Malam Gelanggang pertama!</li>';
      else $('#rkList', el).insertAdjacentHTML('beforeend', html);
      $('#rkMore', el).hidden = !data.more;
      if (data.me && reset) $('#rkMe', el).innerHTML = `<p class="banner">Peringkatmu: <b>#${data.me.rank}</b></p>`;
    } catch (e) {
      $('#rkList', el).innerHTML = `<li class="empty">${esc(e.message)}</li>`;
    }
  }

  el.querySelectorAll('[data-b]').forEach((b) => b.addEventListener('click', () => {
    board = b.dataset.b;
    history.replaceState({}, '', board === 'season' ? '/ranking' : `/ranking?papan=${board}`);
    load(true);
  }));
  $('#rkMore', el).onclick = () => { page++; load(false); };
  load(true);

  return { cleanup: () => { alive = false; } };
}
