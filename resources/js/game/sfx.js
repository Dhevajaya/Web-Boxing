// Efek suara arena, disintesis langsung dengan Web Audio (tanpa file audio).
//   const sfx = createSfx(); sfx.punch(true); sfx.bell(); sfx.crowd(0.6); sfx.setMuted(true)

export function createSfx() {
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return nullSfx();
  const ctx = new AC();
  const master = ctx.createGain();
  master.gain.value = 0.9;
  master.connect(ctx.destination);

  // Derau putih 2 detik, dipakai ulang untuk pukulan, desir, dan penonton.
  const noise = ctx.createBuffer(1, ctx.sampleRate * 2, ctx.sampleRate);
  const data = noise.getChannelData(0);
  for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
  const src = (loop = false) => {
    const s = ctx.createBufferSource();
    s.buffer = noise;
    s.loop = loop;
    return s;
  };
  const env = (g, t, peak, attack, decay) => {
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(peak, t + attack);
    g.gain.exponentialRampToValueAtTime(0.0001, t + attack + decay);
  };

  // Penonton: derau ber-filter yang terus berjalan; levelnya naik turun mengikuti laga.
  const crowdGain = ctx.createGain();
  crowdGain.gain.value = 0;
  const crowdBp = ctx.createBiquadFilter();
  crowdBp.type = 'bandpass';
  crowdBp.frequency.value = 900;
  crowdBp.Q.value = 0.6;
  const crowdLp = ctx.createBiquadFilter();
  crowdLp.type = 'lowpass';
  crowdLp.frequency.value = 2400;
  let crowdSrc = null;
  crowdBp.connect(crowdLp).connect(crowdGain).connect(master);
  let base = 0.05;

  const api = {
    get ctx() { return ctx; },
    resume() { if (ctx.state === 'suspended') ctx.resume().catch(() => {}); },
    setMuted(m) { master.gain.setTargetAtTime(m ? 0 : 0.9, ctx.currentTime, 0.05); },

    startCrowd() {
      if (crowdSrc) return;
      crowdSrc = src(true);
      crowdSrc.connect(crowdBp);
      crowdSrc.start();
      crowdGain.gain.setTargetAtTime(base, ctx.currentTime, 0.5);
    },
    // Sorakan sesaat (0..1), lalu kembali ke dengung latar.
    roar(level = 0.5, hold = 0.6) {
      const t = ctx.currentTime;
      crowdGain.gain.cancelScheduledValues(t);
      crowdGain.gain.setTargetAtTime(base + 0.35 * level, t, 0.08);
      crowdGain.gain.setTargetAtTime(base, t + hold, 0.6);
      crowdBp.frequency.setTargetAtTime(900 + 500 * level, t, 0.1);
      crowdBp.frequency.setTargetAtTime(900, t + hold, 0.6);
    },
    stopCrowd() {
      if (!crowdSrc) return;
      crowdGain.gain.setTargetAtTime(0, ctx.currentTime, 0.3);
      const s = crowdSrc;
      crowdSrc = null;
      setTimeout(() => { try { s.stop(); } catch { /* sudah berhenti */ } }, 1500);
    },

    // Desir pukulan yang meleset / ayunan.
    whoosh() {
      const t = ctx.currentTime;
      const s = src();
      const f = ctx.createBiquadFilter();
      f.type = 'bandpass';
      f.Q.value = 1.2;
      f.frequency.setValueAtTime(500, t);
      f.frequency.exponentialRampToValueAtTime(2600, t + 0.18);
      const g = ctx.createGain();
      env(g, t, 0.35, 0.03, 0.2);
      s.connect(f).connect(g).connect(master);
      s.start(t, Math.random());
      s.stop(t + 0.3);
    },

    // Pukulan masuk: dentum rendah + tepukan kulit. heavy = pukulan keras.
    punch(heavy = false) {
      const t = ctx.currentTime;
      const o = ctx.createOscillator();
      o.type = 'sine';
      o.frequency.setValueAtTime(heavy ? 150 : 190, t);
      o.frequency.exponentialRampToValueAtTime(heavy ? 42 : 70, t + (heavy ? 0.25 : 0.12));
      const og = ctx.createGain();
      env(og, t, heavy ? 1.0 : 0.6, 0.004, heavy ? 0.3 : 0.14);
      o.connect(og).connect(master);
      o.start(t);
      o.stop(t + 0.4);

      const s = src();
      const f = ctx.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = heavy ? 1600 : 2600;
      const g = ctx.createGain();
      env(g, t, heavy ? 0.9 : 0.55, 0.002, heavy ? 0.12 : 0.06);
      s.connect(f).connect(g).connect(master);
      s.start(t, Math.random());
      s.stop(t + 0.2);
    },

    // Tubuh menghantam kanvas.
    thud() {
      const t = ctx.currentTime;
      const o = ctx.createOscillator();
      o.frequency.setValueAtTime(90, t);
      o.frequency.exponentialRampToValueAtTime(35, t + 0.35);
      const g = ctx.createGain();
      env(g, t, 1.0, 0.005, 0.45);
      o.connect(g).connect(master);
      o.start(t);
      o.stop(t + 0.6);
    },

    // Ketukan hitungan wasit.
    tick() {
      const t = ctx.currentTime;
      const o = ctx.createOscillator();
      o.type = 'triangle';
      o.frequency.setValueAtTime(320, t);
      o.frequency.exponentialRampToValueAtTime(120, t + 0.08);
      const g = ctx.createGain();
      env(g, t, 0.5, 0.003, 0.12);
      o.connect(g).connect(master);
      o.start(t);
      o.stop(t + 0.2);
    },

    // Aktivasi jurus: dentum rendah + sapuan naik.
    jurus() {
      const t = ctx.currentTime;
      const o = ctx.createOscillator();
      o.type = 'sine';
      o.frequency.setValueAtTime(70, t);
      o.frequency.exponentialRampToValueAtTime(38, t + 0.6);
      const g = ctx.createGain();
      env(g, t, 0.8, 0.01, 0.7);
      o.connect(g).connect(master);
      o.start(t);
      o.stop(t + 0.8);
      const sw = ctx.createOscillator();
      sw.type = 'sawtooth';
      sw.frequency.setValueAtTime(220, t);
      sw.frequency.exponentialRampToValueAtTime(1400, t + 0.45);
      const f = ctx.createBiquadFilter();
      f.type = 'bandpass';
      f.frequency.value = 900;
      const g2 = ctx.createGain();
      env(g2, t, 0.12, 0.05, 0.45);
      sw.connect(f).connect(g2).connect(master);
      sw.start(t);
      sw.stop(t + 0.55);
    },

    // Nada "tepat" (latihan): makin tinggi untuk combo besar.
    ding(level = 0) {
      const t = ctx.currentTime;
      [1, 1.5].forEach((m, i) => {
        const o = ctx.createOscillator();
        o.type = 'sine';
        o.frequency.value = (880 + Math.min(level, 10) * 40) * m;
        const g = ctx.createGain();
        env(g, t + i * 0.05, 0.22 / (i + 1), 0.004, 0.25);
        o.connect(g).connect(master);
        o.start(t + i * 0.05);
        o.stop(t + 0.45);
      });
    },

    // Dengung "salah" (latihan).
    buzz() {
      const t = ctx.currentTime;
      const o = ctx.createOscillator();
      o.type = 'sawtooth';
      o.frequency.setValueAtTime(150, t);
      o.frequency.linearRampToValueAtTime(95, t + 0.22);
      const f = ctx.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = 900;
      const g = ctx.createGain();
      env(g, t, 0.25, 0.01, 0.25);
      o.connect(f).connect(g).connect(master);
      o.start(t);
      o.stop(t + 0.35);
    },

    // Bel ronde: beberapa nada logam yang meluruh. times = berapa kali dipukul.
    bell(times = 1) {
      for (let i = 0; i < times; i++) {
        const t = ctx.currentTime + i * 0.32;
        [[830, 0.5], [1662, 0.28], [2490, 0.16], [3320, 0.08]].forEach(([fq, a]) => {
          const o = ctx.createOscillator();
          o.frequency.value = fq * (1 + (Math.random() - 0.5) * 0.004);
          const g = ctx.createGain();
          env(g, t, a, 0.005, 1.6);
          o.connect(g).connect(master);
          o.start(t);
          o.stop(t + 1.8);
        });
      }
    },

    // Sorakan besar + siulan (KO, menang).
    cheer() {
      api.roar(1, 2.5);
      for (let i = 0; i < 3; i++) {
        const t = ctx.currentTime + 0.2 + i * 0.35 + Math.random() * 0.2;
        const o = ctx.createOscillator();
        o.type = 'sine';
        o.frequency.setValueAtTime(1800 + Math.random() * 400, t);
        o.frequency.linearRampToValueAtTime(2600 + Math.random() * 500, t + 0.25);
        const g = ctx.createGain();
        env(g, t, 0.07, 0.03, 0.3);
        o.connect(g).connect(master);
        o.start(t);
        o.stop(t + 0.4);
      }
    },

    close() {
      api.stopCrowd();
      setTimeout(() => ctx.close().catch(() => {}), 1800);
    },
  };
  return api;
}

function nullSfx() {
  const n = () => {};
  return { resume: n, setMuted: n, startCrowd: n, roar: n, stopCrowd: n, whoosh: n, punch: n, thud: n, tick: n, ding: n, buzz: n, jurus: n, bell: n, cheer: n, close: n };
}
