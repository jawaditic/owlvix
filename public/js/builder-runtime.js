/* Owlvix page builder runtime — the only script allowed on pages made with the builder (published pages load it
   from owlvix.com; their security policy blocks every other script). It brings the interactive widgets to life:
   scroll animations, counters, countdowns, rotating headlines, sliders, before/after images, popups, sticky header
   and back-to-top. Pages still work without it — everything is visible by default. */
(() => {
    const d = document, $$ = (s, el = d) => [...el.querySelectorAll(s)];
    const store = { get: k => { try { return sessionStorage.getItem(k); } catch { return null; } }, set: (k, v) => { try { sessionStorage.setItem(k, v); } catch {} } };
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Scroll animations: elements start hidden only once this script runs, then appear when scrolled into view */
    const anim = $$('[data-anim]');
    if (anim.length && !reduce && 'IntersectionObserver' in window) {
        d.documentElement.classList.add('ob-anim');
        const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('ob-in'); io.unobserve(e.target); } }), { threshold: 0.15 });
        anim.forEach(el => { if (el.dataset.delay) el.style.transitionDelay = el.dataset.delay + 'ms'; io.observe(el); });
    }

    /* Counters count up from 0 when they appear */
    const counters = $$('[data-count]');
    if (counters.length && 'IntersectionObserver' in window) {
        const run = el => {
            const end = parseFloat(el.dataset.count) || 0, dec = (String(el.dataset.count).split('.')[1] || '').length, t0 = performance.now(), dur = 1600;
            const step = now => { const p = Math.min(1, (now - t0) / dur), v = end * (1 - Math.pow(1 - p, 3)); el.textContent = v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); if (p < 1) requestAnimationFrame(step); };
            reduce ? (el.textContent = end.toLocaleString('en-US')) : requestAnimationFrame(step);
        };
        const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } }), { threshold: 0.4 });
        counters.forEach(el => io.observe(el));
    }

    /* Countdown timers */
    $$('[data-countdown]').forEach(el => {
        const end = Date.parse(el.dataset.countdown);
        if (!end) return;
        const parts = { d: el.querySelector('[data-u=d]'), h: el.querySelector('[data-u=h]'), m: el.querySelector('[data-u=m]'), s: el.querySelector('[data-u=s]') };
        const tick = () => {
            let left = Math.max(0, Math.floor((end - Date.now()) / 1000));
            const v = { d: Math.floor(left / 86400), h: Math.floor(left / 3600) % 24, m: Math.floor(left / 60) % 60, s: left % 60 };
            Object.entries(parts).forEach(([k, p]) => { if (p) p.textContent = String(v[k]).padStart(2, '0'); });
            if (!left) { el.classList.add('is-done'); clearInterval(timer); }
        };
        const timer = setInterval(tick, 1000); tick();
    });

    /* Rotating headline words */
    $$('[data-rotate]').forEach(el => {
        const words = $$('span', el); let i = 0;
        if (words.length < 2 || reduce) return;
        words.forEach((w, n) => w.classList.toggle('is-on', n === 0));
        setInterval(() => { words[i].classList.remove('is-on'); i = (i + 1) % words.length; words[i].classList.add('is-on'); }, 2400);
    });

    /* Sliders: arrows, dots and optional autoplay on top of CSS scroll-snap */
    $$('[data-carousel]').forEach(c => {
        const track = c.querySelector('.ob-track'); if (!track) return;
        const go = dir => track.scrollBy({ left: dir * track.clientWidth, behavior: 'smooth' });
        c.querySelector('[data-prev]')?.addEventListener('click', () => go(-1));
        c.querySelector('[data-next]')?.addEventListener('click', () => go(1));
        const dots = $$('[data-dot]', c);
        dots.forEach((dot, n) => dot.addEventListener('click', e => { e.preventDefault(); track.scrollTo({ left: n * track.clientWidth, behavior: 'smooth' }); }));
        track.addEventListener('scroll', () => { const n = Math.round(track.scrollLeft / Math.max(1, track.clientWidth)); dots.forEach((dot, k) => dot.classList.toggle('is-on', k === n)); }, { passive: true });
        if (c.dataset.auto && !reduce) {
            let hover = false; c.addEventListener('mouseenter', () => (hover = true)); c.addEventListener('mouseleave', () => (hover = false));
            setInterval(() => { if (hover) return; const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4; atEnd ? track.scrollTo({ left: 0, behavior: 'smooth' }) : go(1); }, 4500);
        }
    });

    /* Before / after image slider */
    $$('[data-ba]').forEach(el => {
        const r = el.querySelector('input[type=range]');
        r?.addEventListener('input', () => el.style.setProperty('--pos', r.value + '%'));
    });

    /* Popup: after a delay, on exit intent, or when a link to #popup is clicked */
    const pop = d.querySelector('[data-popup]');
    if (pop) {
        const key = 'ob-popup-' + location.pathname, open = () => { pop.hidden = false; requestAnimationFrame(() => pop.classList.add('is-open')); };
        const close = () => { pop.classList.remove('is-open'); setTimeout(() => (pop.hidden = true), 250); store.set(key, '1'); };
        $$('[data-popup-close]', pop).forEach(b => b.addEventListener('click', close));
        pop.addEventListener('click', e => { if (e.target === pop) close(); });
        d.addEventListener('keydown', e => { if (e.key === 'Escape' && !pop.hidden) close(); });
        $$('a[href="#popup"]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); open(); }));
        const t = pop.dataset.popup;
        if (!store.get(key)) {
            if (t === 'delay') setTimeout(open, (parseInt(pop.dataset.delay, 10) || 5) * 1000);
            if (t === 'exit') { const onOut = e => { if (e.clientY <= 0) { open(); d.removeEventListener('mouseout', onOut); } }; d.addEventListener('mouseout', onOut); setTimeout(open, 45000); }
            if (t === 'scroll') { const onScroll = () => { if (scrollY > (d.documentElement.scrollHeight - innerHeight) * 0.5) { open(); removeEventListener('scroll', onScroll); } }; addEventListener('scroll', onScroll, { passive: true }); }
        }
    }

    /* Sticky header shadow and back-to-top button */
    const nav = d.querySelector('.nav.is-sticky'), top = d.querySelector('.ob-top');
    const onScroll = () => { nav?.classList.toggle('is-scrolled', scrollY > 10); top?.classList.toggle('is-on', scrollY > 600); };
    if (nav || top) { addEventListener('scroll', onScroll, { passive: true }); onScroll(); }
})();
