// Teks komentar untuk setiap kejadian. Murni tampilan, tidak memengaruhi hasil.
// Pilihan kalimat ditentukan dari data kejadian (bukan Math.random), jadi semua
// penonton membaca komentar yang sama.

const PUNCH = {
  jab: 'jab',
  straight: 'straight kanan',
  hook_l: 'hook kiri',
  hook_r: 'hook kanan',
  upper: 'uppercut',
  body: 'pukulan ke badan',
};

const pick = (list, n) => list[n % list.length];

export function describe(e, names) {
  const who = names[e.who];
  const foe = names[e.who === 'a' ? 'b' : 'a'];
  const p = PUNCH[e.p] ?? e.p;
  const n = (e.rd * 7 + (e.d ?? 0) + (e.ha ?? 0)) | 0;

  switch (e.t) {
    case 'bell':
      return e.rd === 1 ? 'Ronde 1. Bel berbunyi!' : `Ronde ${e.rd} dimulai.`;
    case 'miss':
      return pick([`${foe} mengelak dari ${p} ${who}.`, `${p} ${who} meleset.`, `${foe} menunduk, ${p} lewat begitu saja.`], n);
    case 'hit':
      return pick([`${p} ${who} masuk.`, `${who} mendaratkan ${p}.`, `${p} dari ${who} mengenai ${foe}.`], n);
    case 'big':
      return pick([`${p.toUpperCase()} KERAS dari ${who}!`, `${foe} goyah kena ${p} ${who}!`, `Penonton berdiri! ${p} ${who} telak!`], n);
    case 'kd':
      return `${foe} JATUH! ${who} menjatuhkannya dengan ${p}. ${foe} bangkit sebelum hitungan sepuluh.`;
    case 'ko':
      return e.how === 'TKO'
        ? `Wasit menghentikan laga! ${who} menang TKO.`
        : `${foe} TUMBANG! ${who} menang KO dengan ${p}!`;
    case 'end':
      return `Tiga ronde selesai. Keputusan juri: ${e.sa} - ${e.sb}.`;
    default:
      return '';
  }
}

export function resultLine(r, names) {
  if (r.win === 'draw') return { title: 'Seri', sub: `Poin ${r.scoreA} - ${r.scoreB} setelah ${r.rd} ronde` };
  const w = names[r.win];
  if (r.how === 'Angka') return { title: `${w} menang`, sub: `Menang angka ${Math.max(r.scoreA, r.scoreB)} - ${Math.min(r.scoreA, r.scoreB)}` };
  return { title: `${w} menang`, sub: `${r.how} di ronde ${r.rd}` };
}
