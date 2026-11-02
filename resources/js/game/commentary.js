// Teks komentar untuk setiap kejadian. Murni tampilan, tidak memengaruhi hasil.
// Pilihan kalimat ditentukan dari data kejadian (bukan Math.random), jadi semua
// penonton membaca komentar yang sama.

const PUNCH = {
  // v1 (arsip tinju)
  jab: 'jab',
  straight: 'straight kanan',
  hook_l: 'hook kiri',
  hook_r: 'hook kanan',
  upper: 'uppercut',
  body: 'pukulan ke badan',
  // v2 (KAMPUNG TENDANG)
  cross: 'cross',
  hook: 'hook',
  low_kick: 'low kick',
  body_kick: 'tendangan badan',
  high_kick: 'tendangan kepala',
  teep: 'tendangan dorong',
};

// Nama jurus untuk komentar (diisi dari aturan v2 oleh index.js).
export const JURUS_NAMES = {};

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
      if (e.why === 'leg') return `Kaki ${foe} sudah tidak sanggup berdiri! ${who} menang TKO.`;
      return e.how === 'TKO'
        ? `Wasit menghentikan laga! ${who} menang TKO.`
        : `${foe} TUMBANG! ${who} menang KO dengan ${p}!`;
    case 'block':
      return pick([`${foe} menahan ${p} ${who} dengan guard rapat.`, `${p} ${who} tertahan guard.`, `Guard ${foe} kokoh, ${p} tidak tembus.`], n);
    case 'check':
      return pick([`${foe} CHECK! Low kick ${who} dipatahkan tulang kering.`, `Low kick ${who} di-check ${foe}, sakit!`, `${foe} mengangkat lutut, low kick ${who} mental.`], n);
    case 'set':
      return pick([`${who} mundur sebentar, atur napas.`, `${who} merapatkan guard sambil mengatur napas.`, `${who} menunggu, membaca gerak ${foe}.`], n);
    case 'tired':
      return `${who} ngos-ngosan, napasnya habis!`;
    case 'move':
      if (e.x) return `${who} mencoba masuk, tapi ${foe} menjaga jarak.`;
      return { close: `${who} merangsek ke jarak dekat.`, mid: `${who} mengatur jarak sedang.`, far: `${who} mundur menjaga jarak jauh.` }[e.r] ?? `${who} bergerak.`;
    case 'jurus':
      return `${who} mengeluarkan jurus ${JURUS_NAMES[e.j] ?? e.j}!`;
    case 'leg':
      return `Kaki ${who} mulai goyah kena low kick bertubi-tubi!`;
    case 'card':
      return `Ronde ${e.rd} selesai. Kartu juri ronde ini: ${e.sa} - ${e.sb}.`;
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

// Kalimat saat petarung baru jatuh (hasil hitungan belum diketahui penonton).
export function fallLine(e, names) {
  const who = names[e.who];
  const foe = names[e.who === 'a' ? 'b' : 'a'];
  const p = PUNCH[e.p] ?? e.p;
  return pick([`${foe} JATUH! ${p} ${who} telak sekali!`, `${foe} TERJATUH kena ${p} ${who}! Wasit mulai menghitung…`, `Astaga! ${foe} roboh oleh ${p} ${who}!`], (e.rd * 3 + (e.d ?? 0)) | 0);
}

// Kalimat sesudah hitungan: bangkit (kd) atau tamat (ko).
export function afterCountLine(e, names) {
  const who = names[e.who];
  const foe = names[e.who === 'a' ? 'b' : 'a'];
  if (e.t === 'kd') return pick([`${foe} BANGKIT! Laga berlanjut!`, `${foe} berdiri lagi sebelum hitungan sepuluh!`, `Belum selesai! ${foe} bangkit!`], (e.d ?? 0) | 0);
  return e.how === 'TKO' ? `Wasit menghentikan laga! ${who} menang TKO!` : `${foe} tidak bangkit! ${who} menang KO!`;
}
