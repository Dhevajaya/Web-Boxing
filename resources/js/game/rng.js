// PRNG 32-bit deterministik (mulberry32) + hash FNV-1a.
// Harus identik dengan app/Game/Rng.php. Semua nilai unsigned 32-bit.

export function fnv1a(str) {
  const bytes = new TextEncoder().encode(str);
  let h = 0x811c9dc5;
  for (let i = 0; i < bytes.length; i++) {
    h ^= bytes[i];
    h = Math.imul(h, 0x01000193) >>> 0;
  }
  return h >>> 0;
}

export class Rng {
  constructor(seed) {
    this.state = seed >>> 0;
  }

  static fromString(s) {
    return new Rng(fnv1a(s));
  }

  next() {
    this.state = (this.state + 0x6d2b79f5) >>> 0;
    const s = this.state;
    let t = Math.imul(s ^ (s >>> 15), s | 1) >>> 0;
    t = (((t + (Math.imul(t ^ (t >>> 7), t | 61) >>> 0)) >>> 0) ^ t) >>> 0;
    return (t ^ (t >>> 14)) >>> 0;
  }

  int(n) {
    return n <= 1 ? 0 : this.next() % n;
  }

  weighted(weights) {
    const total = weights.reduce((a, b) => a + b, 0);
    let roll = this.int(total);
    for (let i = 0; i < weights.length; i++) {
      if (roll < weights[i]) return i;
      roll -= weights[i];
    }
    return weights.length - 1;
  }
}
