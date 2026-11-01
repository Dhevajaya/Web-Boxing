// Editor profil pemain: /profil/tampilan, /profil/identitas, /profil/stat.
// Satu tampilan dengan tiga tab; pindah tab tidak memuat ulang data, jadi pilihan
// yang belum disimpan di tab lain tetap ada.
import { $, esc, api, STAT_NAMES } from '../ui.js';
import { LOOKS, avatar, lookRandom, content, effective, ovr } from '../game/index.js';
import { session } from '../auth.js';

const TABS = [
  ['tampilan', 'Tampilan'],
  ['identitas', 'Julukan'],
  ['stat', 'Poin stat'],
];

const STAT_HINTS = {
  power: 'Besar damage dan peluang pukulan keras.',
  speed: 'Seberapa sering menyerang duluan dan peluang pukulan kena.',
  stamina: 'Tenaga di ronde akhir dan pemulihan antar-ronde.',
  defense: 'Mengurangi peluang pukulan lawan kena.',
  chin: 'Mengurangi damage dan peluang jatuh (knockdown).',
};

const fmtTime = (iso) => new Date(iso).toLocaleString('id-ID', {
  weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: window.GAME?.brand?.timezone,
});

const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

export function editorView(el, initialTab) {
  let alive = true;
  let tab = initialTab;
  let p = null;        // profil dari server
  let look = null;     // draf tampilan
  let points = null;   // draf poin
  let ident = null;    // draf julukan/kota

  el.innerHTML = '<p class="loading">Memuat profil…</p>';
  document.title = `Atur petarung · ${window.GAME?.brand?.name ?? ''}`;

  const load = (data) => {
    p = data;
    look = { ...p.look };
    points = { ...p.points };
    ident = { nick: p.nick ?? '', kota: p.kota ?? '' };
  };

  api('/api/me/profile', { cache: 'no-store' })
    .then(({ data }) => {
      if (!alive) return;
      load(data);
      frame();
    })
    .catch((e) => {
      if (alive) el.innerHTML = `<p class="banner bad">Profil tidak bisa dimuat (${esc(e.message)}). Coba muat ulang.</p>`;
    });

  // ------------------------------------------------------------ kerangka

  function frame() {
    el.innerHTML = `
      <a href="/${esc(p.username)}" data-link class="sub back">← Kembali ke kartu</a>
      <div class="tabs" role="tablist" aria-label="Bagian profil">
        ${TABS.map(([id, label]) => `<button role="tab" type="button" data-tab="${id}" id="tab-${id}" aria-controls="pane">${label}</button>`).join('')}
      </div>
      <section id="pane" class="card editor" role="tabpanel"></section>`;
    el.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => switchTab(b.dataset.tab)));
    switchTab(tab);
  }

  function switchTab(id) {
    tab = id;
    history.replaceState({}, '', `/profil/${id}`);
    el.querySelectorAll('[data-tab]').forEach((b) => {
      const on = b.dataset.tab === id;
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
    });
    $('#pane', el).setAttribute('aria-labelledby', `tab-${id}`);
    ({ tampilan: drawLook, identitas: drawIdentity, stat: drawStats })[id]();
  }

  // Simpan ke server; perbarui profil bila berhasil, tampilkan pesan bila gagal.
  async function save(path, body, btn, status, okText) {
    btn.disabled = true;
    status.className = 'm-status';
    status.textContent = 'Menyimpan…';
    try {
      const { data } = await api(path, { method: 'PUT', body: JSON.stringify(body) });
      if (!alive) return;
      load(data);
      switchTab(tab);
      const s = $('#saveStatus', el);
      s.className = 'm-status ok';
      s.textContent = okText;
    } catch (e) {
      if (!alive) return;
      status.textContent = e.message;
      btn.disabled = false;
    }
  }

  // ------------------------------------------------------------ tampilan

  function drawLook() {
    const pane = $('#pane', el);
    const lv = p.level.lv;
    const parts = LOOKS.parts.map((part) => {
      const opts = part.items.map((it) => {
        const need = it.lv ?? 1;
        const locked = need > lv && p.look[part.key] !== it.id;
        const on = look[part.key] === it.id;
        const lockTag = locked ? `<small class="lock">🔒 Lv${need}</small>` : '';
        return part.kind === 'swatch'
          ? `<button type="button" class="sw${on ? ' on' : ''}" data-part="${part.key}" data-id="${it.id}" aria-pressed="${on}" ${locked ? 'disabled' : ''}
               title="${esc(it.name)}${locked ? ` (Level ${need})` : ''}" aria-label="${esc(it.name)}${locked ? `, terbuka di Level ${need}` : ''}" style="--c:${it.color}">${lockTag}</button>`
          : `<button type="button" class="chip${on ? ' on' : ''}" data-part="${part.key}" data-id="${it.id}" aria-pressed="${on}" ${locked ? 'disabled' : ''}>${esc(it.name)}${lockTag}</button>`;
      }).join('');
      return `<fieldset class="part"><legend>${esc(part.name)}</legend><div class="opts ${part.kind}s">${opts}</div></fieldset>`;
    }).join('');

    pane.innerHTML = `
      <div class="stage">
        <div class="stage-av" id="preview">${avatar(look, { size: 168, title: `Pratinjau @${p.username}` })}</div>
        <div class="stage-tools">
          <button class="btn btn-ghost btn-sm" type="button" id="rnd">🎲 Acak</button>
          <button class="btn btn-ghost btn-sm" type="button" id="def">↺ Bawaan</button>
        </div>
      </div>
      ${parts}
      <p class="hint">Item bergembok terbuka saat levelmu naik. Kamu sekarang Level ${lv}.</p>
      <div class="savebar">
        <p class="m-status" id="saveStatus" aria-live="polite"></p>
        <button class="btn btn-accent" type="button" id="saveLook">Simpan tampilan</button>
      </div>`;

    const refresh = () => {
      $('#preview', pane).innerHTML = avatar(look, { size: 168, title: `Pratinjau @${p.username}` });
      pane.querySelectorAll('[data-part]').forEach((b) => {
        const on = look[b.dataset.part] === b.dataset.id;
        b.classList.toggle('on', on);
        b.setAttribute('aria-pressed', on);
      });
      const dirty = !same(look, p.look);
      $('#saveLook', pane).disabled = !dirty;
      if (dirty) $('#saveStatus', pane).textContent = '';
    };

    pane.querySelectorAll('[data-part]').forEach((b) => b.addEventListener('click', () => {
      look[b.dataset.part] = b.dataset.id;
      refresh();
    }));
    $('#rnd', pane).onclick = () => { look = lookRandom(lv); refresh(); };
    $('#def', pane).onclick = () => { look = { ...p.defaults.look }; refresh(); };
    $('#saveLook', pane).onclick = (e) => {
      // Sama persis dengan bawaan: simpan null agar tetap mengikuti bawaan.
      const body = same(look, p.defaults.look) ? { look: null } : { look };
      save('/api/me/look', body, e.currentTarget, $('#saveStatus', pane), 'Tampilan tersimpan.');
    };
    refresh();
  }

  // ------------------------------------------------------------ julukan

  function drawIdentity() {
    const pane = $('#pane', el);
    const cfg = window.GAME?.profile ?? { nick_max: 24, kota_max: 24, identity_cooldown_hours: 24 };
    const locked = !!p.identityNextAt;

    pane.innerHTML = `
      <div class="nick-preview">
        <div class="fcard-av">${avatar(p.look, { size: 64 })}</div>
        <div><div class="fcard-u">@${esc(p.username)}</div><div class="fcard-nick" id="nickLine"></div></div>
      </div>
      ${locked ? `<p class="note">Julukan dan kota bisa diganti lagi <b>${esc(fmtTime(p.identityNextAt))}</b>.</p>` : ''}
      <label class="lbl" for="nickIn">Julukan <small id="nickCount"></small></label>
      <div class="field"><input id="nickIn" maxlength="${cfg.nick_max}" autocomplete="off" placeholder="${esc(p.defaults.nick)}" value="${esc(ident.nick)}" ${locked ? 'disabled' : ''}></div>
      <label class="lbl" for="kotaIn">Kota <small id="kotaCount"></small></label>
      <div class="field"><input id="kotaIn" maxlength="${cfg.kota_max}" autocomplete="off" list="kotaList" placeholder="${esc(p.defaults.kota)}" value="${esc(ident.kota)}" ${locked ? 'disabled' : ''}></div>
      <datalist id="kotaList">${content.kota.map((k) => `<option value="${esc(k)}">`).join('')}</datalist>
      <p class="hint">Kosongkan untuk memakai bawaan (“${esc(p.defaults.nick)}” dari ${esc(p.defaults.kota)}). Huruf, angka, spasi, titik, tanda petik, dan tanda hubung saja. Setelah diganti, jeda ${cfg.identity_cooldown_hours} jam sebelum bisa diganti lagi.</p>
      <div class="savebar">
        <p class="m-status" id="saveStatus" aria-live="polite"></p>
        <button class="btn btn-accent" type="button" id="saveId">Simpan julukan</button>
      </div>`;

    const nickIn = $('#nickIn', pane);
    const kotaIn = $('#kotaIn', pane);
    const clean = (s) => s.replace(/\s+/g, ' ').trim();
    const refresh = () => {
      ident = { nick: nickIn.value, kota: kotaIn.value };
      const n = clean(ident.nick) || p.defaults.nick;
      const k = clean(ident.kota) || p.defaults.kota;
      $('#nickLine', pane).textContent = `“${n} dari ${k}”`;
      $('#nickCount', pane).textContent = `${clean(ident.nick).length}/${cfg.nick_max}`;
      $('#kotaCount', pane).textContent = `${clean(ident.kota).length}/${cfg.kota_max}`;
      const dirty = (clean(ident.nick) || null) !== p.nick || (clean(ident.kota) || null) !== p.kota;
      $('#saveId', pane).disabled = locked || !dirty;
      if (dirty) $('#saveStatus', pane).textContent = '';
    };
    nickIn.addEventListener('input', refresh);
    kotaIn.addEventListener('input', refresh);
    $('#saveId', pane).onclick = (e) => save('/api/me/identity', { nick: clean(nickIn.value), kota: clean(kotaIn.value) },
      e.currentTarget, $('#saveStatus', pane), 'Julukan tersimpan.');
    refresh();
  }

  // ------------------------------------------------------------ poin stat

  function drawStats() {
    const pane = $('#pane', el);
    const respecLocked = !!p.respecNextAt;
    const before = ovr(effective(p.base, p.points));

    pane.innerHTML = `
      <div class="pts-head">
        <div><b id="free"></b><small>poin tersisa</small></div>
        <div class="ovr-change"><small>NILAI</small><b id="ovrNow"></b></div>
      </div>
      ${respecLocked
        ? `<p class="note">Poin yang sudah terpasang terkunci sampai <b>${esc(fmtTime(p.respecNextAt))}</b>. Poin baru tetap bisa ditambah.</p>`
        : `<p class="hint">Poin baru bebas ditambah kapan saja. Memindah poin yang sudah terpasang hanya bisa sekali tiap ${p.respecDays} hari.</p>`}
      <div class="pts">
        ${Object.entries(STAT_NAMES).map(([k, label]) => `
          <div class="pt" data-k="${k}">
            <div class="pt-top"><b>${label}</b><span class="pt-val" id="v-${k}"></span></div>
            <div class="pt-row">
              <button type="button" class="step" data-d="-1" aria-label="Kurangi ${label}">−</button>
              <div class="bar2" aria-hidden="true"><i class="b" style="width:${p.base[k]}%"></i><i class="a" id="a-${k}"></i></div>
              <button type="button" class="step" data-d="1" aria-label="Tambah ${label}">+</button>
            </div>
            <small class="pt-hint">${STAT_HINTS[k]}</small>
          </div>`).join('')}
      </div>
      <p class="note warn" id="respecWarn" hidden>Kamu memindah poin yang sudah terpasang. Setelah disimpan, pemindahan berikutnya baru bisa ${p.respecDays} hari lagi.</p>
      <div class="savebar">
        <p class="m-status" id="saveStatus" aria-live="polite"></p>
        <button class="btn btn-ghost" type="button" id="resetPts">Batal</button>
        <button class="btn btn-accent" type="button" id="savePts">Simpan poin</button>
      </div>`;

    const refresh = () => {
      const used = Object.values(points).reduce((a, b) => a + b, 0);
      const left = p.pointsTotal - used;
      const eff = effective(p.base, points);
      const now = ovr(eff);
      $('#free', pane).textContent = left;
      $('#free', pane).className = left > 0 ? 'has' : '';
      const delta = now - before;
      $('#ovrNow', pane).innerHTML = `${now}${delta ? ` <em class="${delta > 0 ? 'up' : 'down'}">${delta > 0 ? '+' : ''}${delta}</em>` : ''}`;
      let respec = false;
      for (const k of Object.keys(STAT_NAMES)) {
        const add = points[k];
        if (add < p.points[k]) respec = true;
        $(`#v-${k}`, pane).innerHTML = `${eff[k]} <small>${p.base[k]}${add ? ` + ${add}` : ''}</small>`;
        $(`#a-${k}`, pane).style.width = `${eff[k] - p.base[k]}%`;
        const row = pane.querySelector(`.pt[data-k="${k}"]`);
        row.querySelector('[data-d="-1"]').disabled = add <= 0 || (respecLocked && add <= p.points[k]);
        row.querySelector('[data-d="1"]').disabled = left <= 0 || eff[k] >= p.statMax;
      }
      $('#respecWarn', pane).hidden = !respec;
      const dirty = !same(points, p.points);
      $('#savePts', pane).disabled = !dirty || left < 0;
      $('#resetPts', pane).disabled = !dirty;
      if (dirty) $('#saveStatus', pane).textContent = '';
    };

    pane.querySelectorAll('.pt').forEach((row) => row.querySelectorAll('.step').forEach((b) => b.addEventListener('click', () => {
      const k = row.dataset.k;
      points[k] = Math.max(0, points[k] + Number(b.dataset.d));
      refresh();
    })));
    $('#resetPts', pane).onclick = () => { points = { ...p.points }; refresh(); };
    $('#savePts', pane).onclick = (e) => save('/api/me/points', { points }, e.currentTarget, $('#saveStatus', pane), 'Poin tersimpan.');
    refresh();
  }

  return { cleanup: () => { alive = false; } };
}

// Dipanggil router: halaman ini hanya untuk pemain yang sudah masuk.
export const needsLogin = () => !session.me;
