/* Owlvix QR code maker (window.OwlQR) — used by the builders' "QR code for your page" and the QR code generator.
   Follows the QR Code standard (ISO/IEC 18004): byte mode (UTF-8), versions 1–40 and all four error correction levels:
   L (~7% of the code can be damaged or covered and it still scans), M (~15%, the default), Q (~25%), H (~30% — use it
   when a logo sits in the middle).

   OwlQR.make(text, { ec, mask }) → { size, version, ec, dark(x, y) }
   OwlQR.svg(text, { color, margin, size, ec })      OwlQR.canvas(text, scale, color, { ec })
   OwlQR.fits(text, ec) → true when the text fits in one code */
(() => {
    // Per level: error-correction codewords per block and number of blocks, versions 1–40 (index 0 unused)
    const EC = {
        L: [0, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        M: [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        Q: [0, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        H: [0, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    };
    const BLOCKS = {
        L: [0, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        M: [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        Q: [0, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        H: [0, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    };
    const LEVEL_BITS = { L: 1, M: 0, Q: 3, H: 2 };
    // Codewords that fit in a version (all modules minus finders, timing, alignment, format and version areas)
    const totalCw = ver => {
        let n = (16 * ver + 128) * ver + 64;
        if (ver >= 2) { const a = Math.floor(ver / 7) + 2; n -= (25 * a - 10) * a - 55; if (ver >= 7) n -= 36; }
        return Math.floor(n / 8);
    };
    const alignAt = ver => {
        if (ver === 1) return [];
        const a = Math.floor(ver / 7) + 2, step = ver === 32 ? 26 : Math.ceil((ver * 4 + 4) / (a * 2 - 2)) * 2, r = [6];
        for (let pos = ver * 4 + 10; r.length < a; pos -= step) r.splice(1, 0, pos);
        return r;
    };
    const level = ec => (LEVEL_BITS[String(ec || 'M').toUpperCase()] !== undefined ? String(ec || 'M').toUpperCase() : 'M');
    const versionFor = (len, ec) => {
        for (let ver = 1; ver <= 40; ver++) {
            const dataCw = totalCw(ver) - EC[ec][ver] * BLOCKS[ec][ver];
            if (4 + (ver < 10 ? 8 : 16) + len * 8 <= dataCw * 8) return ver;
        }
        return 0;
    };

    /* Galois field GF(2^8) maths for Reed–Solomon error correction */
    const mul = (x, y) => { let z = 0; for (let i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11d); z ^= ((y >>> i) & 1) * x; } return z & 0xff; };
    const divisor = degree => {
        const r = new Array(degree).fill(0); r[degree - 1] = 1; let root = 1;
        for (let i = 0; i < degree; i++) {
            for (let j = 0; j < degree; j++) { r[j] = mul(r[j], root); if (j + 1 < degree) r[j] ^= r[j + 1]; }
            root = mul(root, 2);
        }
        return r;
    };
    const remainder = (data, div) => {
        const r = new Array(div.length).fill(0);
        for (const b of data) { const f = b ^ r.shift(); r.push(0); div.forEach((d, i) => (r[i] ^= mul(d, f))); }
        return r;
    };

    const make = (text, o = {}) => {
        if (typeof o === 'number') o = { mask: o }; // old form make(text, mask) — tests only
        const ec = level(o.ec), forceMask = o.mask, bytes = [...new TextEncoder().encode(String(text))];
        const ver = versionFor(bytes.length, ec);
        if (!ver) throw new Error('That is too much text for one QR code.');
        const total = totalCw(ver), ecLen = EC[ec][ver], numBlocks = BLOCKS[ec][ver], dataCw = total - ecLen * numBlocks;

        // Data bits: mode (byte), length, bytes, terminator, then pad to full codewords
        const bits = [];
        const put = (v, n) => { for (let i = n - 1; i >= 0; i--) bits.push((v >>> i) & 1); };
        put(4, 4); put(bytes.length, ver < 10 ? 8 : 16); bytes.forEach(b => put(b, 8));
        put(0, Math.min(4, dataCw * 8 - bits.length));
        put(0, (8 - (bits.length % 8)) % 8);
        for (let p = 0xec; bits.length < dataCw * 8; p ^= 0xec ^ 0x11) put(p, 8);
        const data = []; for (let i = 0; i < bits.length; i += 8) data.push(parseInt(bits.slice(i, i + 8).join(''), 2));

        // Split into blocks, add error correction, interleave
        const div = divisor(ecLen), short = numBlocks - (total % numBlocks), shortLen = Math.floor(total / numBlocks), blocks = [];
        for (let i = 0, k = 0; i < numBlocks; i++) {
            const d = data.slice(k, k + shortLen - ecLen + (i < short ? 0 : 1)); k += d.length;
            const e = remainder(d, div); if (i < short) d.push(0);
            blocks.push(d.concat(e));
        }
        const cw = [];
        for (let i = 0; i < blocks[0].length; i++) blocks.forEach((b, j) => { if (i !== shortLen - ecLen || j >= short) cw.push(b[i]); });

        // The grid: function patterns first (finders, timing, alignment, format/version areas)
        const size = ver * 4 + 17, m = [...Array(size)].map(() => new Array(size).fill(false)), fn = [...Array(size)].map(() => new Array(size).fill(false));
        const set = (x, y, v) => { m[y][x] = v; fn[y][x] = true; };
        for (let i = 0; i < size; i++) { set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
        [[3, 3], [size - 4, 3], [3, size - 4]].forEach(([cx, cy]) => {
            for (let dy = -4; dy <= 4; dy++) for (let dx = -4; dx <= 4; dx++) {
                const x = cx + dx, y = cy + dy, dist = Math.max(Math.abs(dx), Math.abs(dy));
                if (x >= 0 && x < size && y >= 0 && y < size) set(x, y, dist !== 2 && dist !== 4);
            }
        });
        const al = alignAt(ver), last = al.length - 1;
        al.forEach((ay, i) => al.forEach((ax, j) => {
            if ((i === 0 && j === 0) || (i === 0 && j === last) || (i === last && j === 0)) return;
            for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) set(ax + dx, ay + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }));
        const drawFormat = mask => {
            const d = (LEVEL_BITS[ec] << 3) | mask;
            let r = d; for (let i = 0; i < 10; i++) r = (r << 1) ^ ((r >>> 9) * 0x537);
            const b = ((d << 10) | r) ^ 0x5412, bit = i => ((b >>> i) & 1) === 1;
            for (let i = 0; i <= 5; i++) set(8, i, bit(i));
            set(8, 7, bit(6)); set(8, 8, bit(7)); set(7, 8, bit(8));
            for (let i = 9; i < 15; i++) set(14 - i, 8, bit(i));
            for (let i = 0; i < 8; i++) set(size - 1 - i, 8, bit(i));
            for (let i = 8; i < 15; i++) set(8, size - 15 + i, bit(i));
            set(8, size - 8, true);
        };
        drawFormat(0); // reserves the format areas
        if (ver >= 7) {
            let r = ver; for (let i = 0; i < 12; i++) r = (r << 1) ^ ((r >>> 11) * 0x1f25);
            const b = (ver << 12) | r;
            for (let i = 0; i < 18; i++) { const v = ((b >>> i) & 1) === 1, a = size - 11 + (i % 3), c = Math.floor(i / 3); set(a, c, v); set(c, a, v); }
        }

        // Data in the zig-zag order, two columns at a time from the bottom right
        let i = 0;
        for (let right = size - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (let v = 0; v < size; v++) for (let j = 0; j < 2; j++) {
                const x = right - j, up = ((right + 1) & 2) === 0, y = up ? size - 1 - v : v;
                if (!fn[y][x] && i < cw.length * 8) { m[y][x] = ((cw[i >>> 3] >>> (7 - (i & 7))) & 1) === 1; i++; }
            }
        }

        // Try the 8 masks and keep the one with the lowest penalty (fewest look-alike patterns)
        const MASK = [(x, y) => (x + y) % 2 === 0, (x, y) => y % 2 === 0, x => x % 3 === 0, (x, y) => (x + y) % 3 === 0,
            (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0, (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0,
            (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0, (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0];
        const apply = k => { for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) if (!fn[y][x] && MASK[k](x, y)) m[y][x] = !m[y][x]; };
        const penalty = () => {
            let p = 0, dark = 0;
            const line = get => { // runs of 5+ same colour, and finder-like 1:1:3:1:1 patterns
                let run = 1;
                for (let k = 1; k <= size; k++) {
                    if (k < size && get(k) === get(k - 1)) run++;
                    else { if (run >= 5) p += run - 2; run = 1; }
                }
                for (let k = 0; k + 6 < size; k++) {
                    const s = [0, 1, 2, 3, 4, 5, 6].map(o => get(k + o));
                    if (s[0] && !s[1] && s[2] && s[3] && s[4] && !s[5] && s[6]) {
                        const before = k >= 4 && [1, 2, 3, 4].every(o => !get(k - o)), after = k + 10 < size && [7, 8, 9, 10].every(o => !get(k + o));
                        if (before || after) p += 40;
                    }
                }
            };
            for (let y = 0; y < size; y++) line(x => m[y][x]);
            for (let x = 0; x < size; x++) line(y => m[y][x]);
            for (let y = 0; y + 1 < size; y++) for (let x = 0; x + 1 < size; x++) { const c = m[y][x]; if (c === m[y][x + 1] && c === m[y + 1][x] && c === m[y + 1][x + 1]) p += 3; }
            m.forEach(r => r.forEach(c => c && dark++));
            return p + Math.floor(Math.abs(dark * 20 - size * size * 10) / (size * size)) * 10;
        };
        let best = 0, bestScore = Infinity;
        for (let k = 0; k < 8; k++) { apply(k); drawFormat(k); const s = penalty(); if (s < bestScore) { bestScore = s; best = k; } apply(k); }
        if (forceMask >= 0 && forceMask < 8) best = forceMask;
        apply(best); drawFormat(best);
        return { size, version: ver, ec, mask: best, dark: (x, y) => m[y][x] };
    };

    /** An SVG image of the code (one path, crisp at any size). */
    const svg = (text, o = {}) => {
        const q = make(text, { ec: o.ec }), mg = o.margin ?? 4, n = q.size + mg * 2;
        let d = '';
        for (let y = 0; y < q.size; y++) for (let x = 0; x < q.size; x++) if (q.dark(x, y)) d += `M${x + mg},${y + mg}h1v1h-1z`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${n} ${n}" shape-rendering="crispEdges"${o.size ? ` width="${o.size}" height="${o.size}"` : ''}><rect width="${n}" height="${n}" fill="#fff"/><path d="${d}" fill="${o.color || '#000'}"/></svg>`;
    };
    /** A canvas with the code drawn at `scale` pixels per square (for PNG downloads). */
    const canvas = (text, scale = 12, color = '#000', o = {}) => {
        const q = make(text, { ec: o.ec }), mg = 4, n = (q.size + mg * 2) * scale, c = document.createElement('canvas');
        c.width = c.height = n; const g = c.getContext('2d');
        g.fillStyle = '#fff'; g.fillRect(0, 0, n, n); g.fillStyle = color;
        for (let y = 0; y < q.size; y++) for (let x = 0; x < q.size; x++) if (q.dark(x, y)) g.fillRect((x + mg) * scale, (y + mg) * scale, scale, scale);
        return c;
    };
    const fits = (text, ec) => versionFor(new TextEncoder().encode(String(text)).length, level(ec)) > 0;
    const api = { make, svg, canvas, fits };
    if (typeof window !== 'undefined') window.OwlQR = api;
    if (typeof module === 'object' && module.exports) module.exports = api; // for tests
})();
