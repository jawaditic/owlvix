/* Admin stacked column chart, used for "Enquiries over time" (dashboard) and "Visitors" (traffic).
   window.DASHBOARD = { daily: [{date, <key>: n, ...}], series: [{key, label}], noun: ['enquiry', 'enquiries'] }
   Plain SVG, no library. 7 and 30 days show one column per day; 90 days groups by week. */
(() => {
    const cfg = window.DASHBOARD || {};
    const data = cfg.daily || [];
    const series = cfg.series || [{ key: 'popup', label: 'Quote popup' }, { key: 'contact', label: 'Contact form' }];
    const noun = cfg.noun || ['enquiry', 'enquiries'];
    const sum = b => series.reduce((s, x) => s + (b[x.key] || 0), 0);
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
        const pick = d => Object.fromEntries(series.map(x => [x.key, d[x.key] || 0]));
        if (range <= 30) return days.map(d => ({ label: fmtDay(d.date), short: fmtDay(d.date), ...pick(d) }));
        const weeks = [];
        for (let i = 0; i < days.length; i += 7) {
            const w = days.slice(i, i + 7);
            weeks.push({
                label: `${fmtDay(w[0].date)} – ${fmtDay(w[w.length - 1].date)}`,
                short: fmtDay(w[0].date),
                ...Object.fromEntries(series.map(x => [x.key, w.reduce((s, d) => s + (d[x.key] || 0), 0)])),
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
        const { step, max } = niceScale(Math.max(...buckets.map(sum)));
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

            // Stacked column: first series at the bottom, 2px gap between,
            // rounded only at the top end of the column.
            const x = cx - bw / 2;
            const g = el('g', { class: 'col' }, svg);
            const total = sum(b);
            let base = 0;
            series.map((x, n) => [x.key, 's' + (n + 1)]).forEach(([key, cls]) => {
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
                tip.innerHTML = `<strong>${b.label}</strong>` +
                    series.map((x, n) => `<span><i class="sw sw--${n + 1}"></i>${x.label} <b>${b[x.key]}</b></span>`).join('') +
                    `<span class="chart-tip__total">Total <b>${total}</b></span>`;
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
        const totals = Object.fromEntries(series.map(x => [x.key, buckets.reduce((s, b) => s + b[x.key], 0)]));
        const all = Object.values(totals).reduce((a, c) => a + c, 0);
        document.getElementById('trend-summary').textContent =
            `${all} ${all === 1 ? noun[0] : noun[1]} in the last ${range} days` + (range > 30 ? ' (by week)' : '');
        series.forEach(x => { const el = document.getElementById('leg-' + x.key); if (el) el.textContent = totals[x.key]; });
        document.getElementById('trend-table').innerHTML =
            `<table><thead><tr><th>${range > 30 ? 'Week' : 'Day'}</th>${series.map(x => `<th>${x.label}</th>`).join('')}<th>Total</th></tr></thead><tbody>` +
            buckets.slice().reverse().map(b => `<tr><td>${b.label}</td>${series.map(x => `<td>${b[x.key]}</td>`).join('')}<td>${sum(b)}</td></tr>`).join('') +
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
