import { $, esc, api, normalizeUsername } from '../ui.js';
import { navigate } from '../router.js';
import { baseFighter, simulate, signature, ovr } from '../game/index.js';
import { describe, resultLine } from '../game/commentary.js';

export function homeView(el) {
  const brand = window.GAME?.brand ?? {};
  el.innerHTML = `
    <section class="hero">
      <span class="eyebrow">Fase 1 · fondasi</span>
      <h1>Mana <em>lawan</em>mu?</h1>
      <p>Ketik username Instagram untuk melihat kartu petarungnya.</p>
    </section>

    <form class="field" id="search" autocomplete="off">
      <span class="at">@</span>
      <input id="q" type="text" autocapitalize="none" spellcheck="false" placeholder="username" aria-label="Username Instagram">
      <button class="btn btn-accent btn-sm" type="submit">Cari</button>
    </form>
    <p class="err" id="qErr" hidden>Username tidak valid.</p>

    <section class="card" aria-labelledby="demoH">
      <h2 id="demoH">Uji mesin</h2>
      <p class="sub">Laga dihitung di browser dari seed, lalu dibandingkan dengan hitungan server. Hasilnya harus identik.</p>
      <div class="row">
        <label class="field"><span class="at">@</span><input id="ua" value="si.biru" aria-label="Petarung sudut biru"></label>
        <label class="field"><span class="at">@</span><input id="ub" value="si.merah" aria-label="Petarung sudut merah"></label>
      </div>
      <div class="row">
        <label class="field"><span class="at">#</span><input id="seed" inputmode="numeric" value="2026" aria-label="Seed"></label>
        <button class="btn btn-ghost" id="rand" type="button">Acak seed</button>
      </div>
      <button class="btn btn-accent btn-block" id="go" type="button">Tanding!</button>
      <div id="ring" hidden>
        <div class="versus">
          <div><div class="side-name blue" id="na"></div><div class="hp blue"><i id="ha" style="width:100%"></i></div></div>
          <span class="vs">VS</span>
          <div><div class="side-name red" id="nb"></div><div class="hp red"><i id="hb" style="width:100%"></i></div></div>
        </div>
        <ul class="feed" id="feed"></ul>
        <div class="result" id="res" hidden><b id="resT"></b><span id="resS"></span></div>
        <p class="parity" id="parity"></p>
      </div>
    </section>

    <p class="sub" style="text-align:center;color:var(--muted)">Follow <b>@${esc(brand.instagram)}</b> lalu DM <b>${esc(brand.keyword)}</b> untuk jadi petarung (aktif mulai Fase 2).</p>
  `;

  $('#search', el).onsubmit = (e) => {
    e.preventDefault();
    const u = normalizeUsername($('#q', el).value);
    $('#qErr', el).hidden = !!u;
    if (u) navigate(`/${u}`);
  };
  $('#rand', el).onclick = () => { $('#seed', el).value = String((Math.random() * 2 ** 32) >>> 0); };

  let timer = null;
  $('#go', el).onclick = () => {
    clearInterval(timer);
    const ua = normalizeUsername($('#ua', el).value);
    const ub = normalizeUsername($('#ub', el).value);
    const seed = Number($('#seed', el).value) >>> 0;
    if (!ua || !ub) return;

    const fa = baseFighter(ua);
    const fb = baseFighter(ub);
    const A = { style: fa.style, stats: fa.base };
    const B = { style: fb.style, stats: fb.base };
    const r = simulate(A, B, seed);
    const names = { a: `@${ua}`, b: `@${ub}` };

    $('#ring', el).hidden = false;
    $('#res', el).hidden = true;
    $('#na', el).textContent = `${names.a} · ${ovr(fa.base)}`;
    $('#nb', el).textContent = `${ovr(fb.base)} · ${names.b}`;
    const feed = $('#feed', el);
    feed.innerHTML = '';
    const setHp = (e) => {
      $('#ha', el).style.width = `${e.ha / 10}%`;
      $('#hb', el).style.width = `${e.hb / 10}%`;
    };
    setHp({ ha: 1000, hb: 1000 });

    let i = 0;
    timer = setInterval(() => {
      const e = r.events[i++];
      if (!e) {
        clearInterval(timer);
        const line = resultLine(r, names);
        $('#resT', el).textContent = line.title;
        $('#resS', el).textContent = line.sub;
        $('#res', el).hidden = false;
        return;
      }
      setHp(e);
      const li = document.createElement('li');
      li.textContent = describe(e, names);
      if (['big', 'kd', 'ko'].includes(e.t)) li.className = 'big';
      if (e.t === 'bell' || e.t === 'end') li.className = 'bell';
      feed.prepend(li);
    }, 260);

    // Bandingkan dengan hitungan server.
    const sig = signature(r);
    const p = $('#parity', el);
    p.className = 'parity';
    p.textContent = `Browser: ${sig} · menghubungi server…`;
    api(`/api/engine/preview/${ua}/${ub}?seed=${seed}`)
      .then(({ data }) => {
        const same = data.sig === sig;
        p.className = `parity ${same ? 'ok' : 'bad'}`;
        p.textContent = same
          ? `✓ Server menghitung hasil yang sama (sidik jari ${sig}).`
          : `✗ Berbeda! browser ${sig}, server ${data.sig}. Jalankan test vektor.`;
      })
      .catch(() => { p.textContent = `Browser: ${sig} · server tidak terjangkau.`; });
  };

  return { cleanup: () => clearInterval(timer) };
}
