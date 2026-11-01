/* Owlvix phone boxes: a flag + calling code picker in front of every phone box on owlvix.com's builders and tools
   (input[type=tel] — not the quote form, which has its own picker [data-intl-phone], the app wizard's own picker,
   or anything inside [data-no-dial]). The number stays in the box itself as "+44 7911 123456": picking a country
   changes the "+44" at the start, and typing "+92…" moves the flag — so every builder keeps reading and saving the
   box exactly as before. The first country is the visitor's, from their IP address (geojs.io, as the quote form
   does), else the browser's language. The same list is in app/Support/Phone.php (PhoneFieldTest checks it). */
(() => {
    if (window.OwlPhone) return;
    const DIAL = Object.fromEntries('AD376 AE971 AF93 AG1268 AI1264 AL355 AM374 AO244 AR54 AS1684 AT43 AU61 AW297 AX358 AZ994 BA387 BB1246 BD880 BE32 BF226 BG359 BH973 BI257 BJ229 BL590 BM1441 BN673 BO591 BQ599 BR55 BS1242 BT975 BW267 BY375 BZ501 CA1 CC61 CD243 CF236 CG242 CH41 CI225 CK682 CL56 CM237 CN86 CO57 CR506 CU53 CV238 CW599 CX61 CY357 CZ420 DE49 DJ253 DK45 DM1767 DO1809 DZ213 EC593 EE372 EG20 EH212 ER291 ES34 ET251 FI358 FJ679 FK500 FM691 FO298 FR33 GA241 GB44 GD1473 GE995 GF594 GG44 GH233 GI350 GL299 GM220 GN224 GP590 GQ240 GR30 GT502 GU1671 GW245 GY592 HK852 HN504 HR385 HT509 HU36 ID62 IE353 IL972 IM44 IN91 IQ964 IR98 IS354 IT39 JE44 JM1876 JO962 JP81 KE254 KG996 KH855 KI686 KM269 KN1869 KP850 KR82 KW965 KY1345 KZ7 LA856 LB961 LC1758 LI423 LK94 LR231 LS266 LT370 LU352 LV371 LY218 MA212 MC377 MD373 ME382 MF590 MG261 MH692 MK389 ML223 MM95 MN976 MO853 MP1670 MQ596 MR222 MS1664 MT356 MU230 MV960 MW265 MX52 MY60 MZ258 NA264 NC687 NE227 NF672 NG234 NI505 NL31 NO47 NP977 NR674 NU683 NZ64 OM968 PA507 PE51 PF689 PG675 PH63 PK92 PL48 PM508 PN64 PR1787 PS970 PT351 PW680 PY595 QA974 RE262 RO40 RS381 RU7 RW250 SA966 SB677 SC248 SD249 SE46 SG65 SH290 SI386 SK421 SL232 SM378 SN221 SO252 SR597 SS211 ST239 SV503 SX1721 SY963 SZ268 TC1649 TD235 TG228 TH66 TJ992 TK690 TL670 TM993 TN216 TO676 TR90 TT1868 TV688 TW886 TZ255 UA380 UG256 US1 UY598 UZ998 VA39 VC1784 VE58 VG1284 VI1340 VN84 VU678 WF681 WS685 XK383 YE967 YT262 ZA27 ZM260 ZW263'.split(' ').map(x => [x.slice(0, 2), x.slice(2)]));
    const flag = c => String.fromCodePoint(...[...c].map(ch => 0x1F1E6 + ch.charCodeAt(0) - 65));
    const names = (() => { try { return new Intl.DisplayNames(['en'], { type: 'region' }); } catch { return null; } })();
    const name = c => { try { return names?.of(c) || c; } catch { return c; } };
    const esc = s => String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
    const OPTIONS = Object.keys(DIAL).map(c => [c, name(c)]).sort((a, b) => a[1].localeCompare(b[1]))
        .map(([c, n]) => `<option value="${c}">${flag(c)} ${esc(n)} (+${DIAL[c]})</option>`).join('');
    let home = (navigator.languages || [navigator.language]).map(l => (String(l).split('-')[1] || '').toUpperCase()).find(r => DIAL[r]) || 'US';
    const boxes = new Set();

    /** "+44 7911…" → "GB" (the longest code wins; a code shared by several countries goes to the visitor's). */
    const countryOf = v => {
        const t = String(v || '').trim();
        if (!t.startsWith('+')) return null;
        const digits = t.slice(1).replace(/\D/g, '');
        let best = null;
        Object.entries(DIAL).forEach(([c, code]) => {
            if (digits.startsWith(code) && (!best || code.length > DIAL[best].length || (code.length === DIAL[best].length && c === home))) best = c;
        });
        return best;
    };
    /** The number without its "+code" at the start (a national 0 in front goes too). */
    const rest = v => {
        const t = String(v || '').trim(), c = countryOf(t);
        if (!c) return t.replace(/^\+/, '').replace(/^0+/, '');
        let i = 1, n = 0;
        while (i < t.length && n < DIAL[c].length) { if (/\d/.test(t[i])) n++; i++; }
        return t.slice(i).replace(/^[\s\-.)]+/, '');
    };

    const enhance = input => {
        if (input.dataset.dial || input.hasAttribute('data-intl-phone') || input.closest('[data-no-dial], .wz-tel, .iti')) return;
        input.dataset.dial = '1';
        const wrap = document.createElement('span'); wrap.className = 'opf';
        const pick = document.createElement('span'); pick.className = 'opf__cc';
        pick.innerHTML = `<span class="opf__face" aria-hidden="true"></span><select aria-label="Country calling code">${OPTIONS}</select>`;
        // the picker comes after the box in the page (so a label around it still names the box) but shows in front
        input.before(wrap); wrap.append(input, pick);
        const sel = pick.querySelector('select'), face = pick.querySelector('.opf__face');
        const show = () => { const c = countryOf(input.value) || wrap.dataset.cc || home; face.textContent = `${flag(c)} +${DIAL[c]}`; sel.value = c; };
        sel.addEventListener('change', () => {
            const c = sel.value, r = rest(input.value);
            wrap.dataset.cc = c;
            input.value = `+${DIAL[c]} ${r}`.trimEnd() + (r ? '' : ' ');
            ['input', 'change'].forEach(t => input.dispatchEvent(new Event(t, { bubbles: true })));
            show(); input.focus();
        });
        input.addEventListener('input', show);
        input.addEventListener('focus', () => { if (!input.value.trim()) { const c = wrap.dataset.cc || home; input.value = `+${DIAL[c]} `; } show(); });
        input.addEventListener('blur', () => { if (/^\+\d{1,4}\s*$/.test(input.value)) input.value = ''; show(); });
        boxes.add(show); show();
    };
    const scan = root => root.querySelectorAll?.('input[type=tel]').forEach(enhance);
    scan(document);
    new MutationObserver(list => list.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType !== 1) return;
        if (n.matches('input[type=tel]')) enhance(n); else scan(n);
    }))).observe(document.body, { childList: true, subtree: true });

    let lookup = null;
    try { lookup = sessionStorage.getItem('owlvix-cc'); } catch {}
    const setHome = c => { if (DIAL[c]) { home = c; boxes.forEach(show => show()); } };
    if (lookup) setHome(lookup);
    else fetch('https://get.geojs.io/v1/ip/country.json').then(r => r.json()).then(j => {
        const c = String(j.country || '').toUpperCase();
        try { sessionStorage.setItem('owlvix-cc', c); } catch {}
        setHome(c);
    }).catch(() => {});
    window.OwlPhone = { countryOf, rest, scan };
})();
