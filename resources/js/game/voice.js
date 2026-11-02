// Komentator bersuara: memakai suara bahasa Indonesia bawaan perangkat (Web Speech API).
// Bila perangkat tidak punya suara Indonesia, komentator diam (teks tetap tampil).

export function createVoice() {
  const synth = window.speechSynthesis;
  if (!synth || typeof SpeechSynthesisUtterance === 'undefined') return { say() {}, stop() {}, setMuted() {}, available: false };

  let voice = null;
  let muted = false;
  const pick = () => {
    const all = synth.getVoices();
    voice = all.find((v) => /^id(-|_|$)/i.test(v.lang) && /google/i.test(v.name))
      || all.find((v) => /^id(-|_|$)/i.test(v.lang))
      || null;
  };
  pick();
  synth.addEventListener?.('voiceschanged', pick);

  return {
    get available() { return !!voice; },
    // urgent = potong kalimat yang sedang dibacakan (momen besar: jatuh, KO).
    say(text, { urgent = false, rate = 1.15 } = {}) {
      if (muted || !voice || !text) return;
      if (urgent) synth.cancel();
      else if (synth.speaking || synth.pending) return; // jangan menumpuk antrean
      const u = new SpeechSynthesisUtterance(text.replace(/@/g, '').replace(/_/g, ' '));
      u.voice = voice;
      u.lang = voice.lang;
      u.rate = rate;
      u.pitch = 1.05;
      synth.speak(u);
    },
    stop() { synth.cancel(); },
    setMuted(m) { muted = m; if (m) synth.cancel(); },
  };
}
