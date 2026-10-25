/* Admin dashboard: "Enquiries over time" stacked column chart (quote popup + contact form).
   Plain SVG, no library. 7 and 30 days show one column per day; 90 days groups by week. */
(() => {
    const data = (window.DASHBOARD || {}).daily || [];
    const box = document.getElementById('trend');
    const tip = document.getElementById('chart-tip');
    if (!box || !data.length) return;

    const NS = 'http://www.w3.org/2000/svg';
    const el = (name, attrs = {}, parent) => {
        const n = document.createElementNS(NS, name);
        for (const k in attrs) n.setAttribute(k, attrs[k]);
        if (parent) parent.appendChild(n);
        return n;
    };
    const fmtDay = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

    // Group the days for the chosen range
    const bucketsFor = range => {
        const days = data.slice(-range);
        if (range <= 30) return days.map(d => ({ label: fmtDay(d.date), short: fmtDay(d.date), popup: d.popup, contact: d.contact }));
        const weeks = [];
        for (let i = 0; i < days.length; i += 7) {
            const w = days.slice(i, i + 7);
            weeks.push({
                label: `${fmtDay(w[0].date)} – ${fmtDay(w[w.length - 1].date)}`,
                short: fmtDay(w[0].date),
                popup: w.reduce((s, d) => s + d.popup, 0),
                contact: w.reduce((s, d) => s + d.contact, 0),
            });
        }
        return weeks;
    };

    // Friendly whole-number axis: pick a step (1, 2, 5, 10, 20, ...) giving about 4 gridlines
    const niceScale = v => {
        const raw = Math.max(1, v) / 4;
        const mag = Math.pow(10, Math.floor(Math.log10(raw)));
        const step = Math.max(1, [1, 2, 5, 10].map(m => m * mag).find(s => s >= raw));
        return { step, max: Math.ceil(Math.max(1, v) / step) * step };
    };

    let range = 30;

    const draw = () => {
        const buckets = bucketsFor(range);
        const W = box.clientWidth || 800, H = 260;
        const pad = { t: 12, r: 8, b: 28, l: 34 };
        const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        const { step, max } = niceScale(Math.max(...buckets.map(b => b.popup + b.contact)));
        const y = v => pad.t + ih - (v / max) * ih;
        const slot = iw / buckets.length;
        const bw = Math.max(3, Math.min(28, slot * 0.62));

        box.innerHTML = '';
        const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, width: '100%', height: H }, box);

        // Grid + y labels
        const ticks = [];
        for (let v = 0; v <= max; v += step) ticks.push(v);
        ticks.forEach(v => {
            el('line', { x1: pad.l, x2: W - pad.r, y1: y(v), y2: y(v), class: v === 0 ? 'axis' : 'grid' }, svg);
            el('text', { x: pad.l - 8, y: y(v) + 4, class: 'tick', 'text-anchor': 'end' }, svg).textContent = v;
        });

        // x labels: thin out so they never collide
        const every = Math.ceil(buckets.length / Math.max(1, Math.floor(iw / 64)));
        buckets.forEach((b, i) => {
            const cx = pad.l + slot * i + slot / 2;
            if (i % every === 0 || i === buckets.length - 1 && buckets.length <= 13) {
                el('text', { x: cx, y: H - 8, class: 'tick', 'text-anchor': 'middle' }, svg).textContent = b.short;
            }

            // Stacked column: popup at the bottom, contact form on top, 2px gap between,
            // rounded only at the top end of the column.
            const x = cx - bw / 2;
            const g = el('g', { class: 'col' }, svg);
            const total = b.popup + b.contact;
            let base = 0;
            [['popup', 's1'], ['contact', 's2']].forEach(([key, cls]) => {
                const v = b[key];
                if (!v) return;
                const top = y(base + v), bottom = y(base) - (base ? 2 : 0);
                const h = Math.max(1, bottom - top);
                const isTop = base + v === total;
                const r = isTop ? Math.min(4, bw / 2, h) : 0;
                el('path', {
                    class: cls,
                    d: `M${x},${top + h} V${top + r} Q${x},${top} ${x + r},${top} H${x + bw - r} Q${x + bw},${top} ${x + bw},${top + r} V${top + h} Z`,
                }, g);
                base += v;
            });

            // Hover target: the whole column height, wider than the bar
            const hit = el('rect', { x: pad.l + slot * i, y: pad.t, width: slot, height: ih, class: 'hit' }, g);
            hit.addEventListener('mouseenter', () => {
                g.classList.add('is-hover');
                tip.innerHTML = `<strong>${b.label}</strong>
                    <span><i class="sw sw--1"></i>Quote popup <b>${b.popup}</b></span>
                    <span><i class="sw sw--2"></i>Contact form <b>${b.contact}</b></span>
                    <span class="chart-tip__total">Total <b>${total}</b></span>`;
                tip.hidden = false;
            });
            hit.addEventListener('mousemove', e => {
                const pad = 14, w = tip.offsetWidth, h = tip.offsetHeight;
                let left = e.clientX + pad, top = e.clientY - h - pad;
                if (left + w > innerWidth - 8) left = e.clientX - w - pad;
                if (top < 8) top = e.clientY + pad;
                tip.style.left = left + 'px'; tip.style.top = top + 'px';
            });
            hit.addEventListener('mouseleave', () => { g.classList.remove('is-hover'); tip.hidden = true; });
        });

        // Summary, legend totals and table view
        const sumP = buckets.reduce((s, b) => s + b.popup, 0), sumC = buckets.reduce((s, b) => s + b.contact, 0);
        document.getElementById('trend-summary').textContent =
            `${sumP + sumC} ${sumP + sumC === 1 ? 'enquiry' : 'enquiries'} in the last ${range} days` + (range > 30 ? ' (by week)' : '');
        document.getElementById('leg-popup').textContent = sumP;
        document.getElementById('leg-contact').textContent = sumC;
        document.getElementById('trend-table').innerHTML =
            `<table><thead><tr><th>${range > 30 ? 'Week' : 'Day'}</th><th>Quote popup</th><th>Contact form</th><th>Total</th></tr></thead><tbody>` +
            buckets.slice().reverse().map(b => `<tr><td>${b.label}</td><td>${b.popup}</td><td>${b.contact}</td><td>${b.popup + b.contact}</td></tr>`).join('') +
            '</tbody></table>';
    };

    document.querySelectorAll('[data-range]').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('[data-range]').forEach(b => b.classList.toggle('is-active', b === btn));
        range = Number(btn.dataset.range);
        draw();
    }));
    let t;
    addEventListener('resize', () => { clearTimeout(t); t = setTimeout(draw, 120); });
    draw();
})();
