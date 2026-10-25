/* Talking owl engine, shared by the homepage and the thank-you page. Exposes window.Owl. */
(() => {
    /* ---------- Talking owl ----------
       Plays voice lines, writes each line as it's spoken and moves the beak and body in time
       with the voice, using loudness data measured from each audio file (30 steps a second). */
    const FPS = 30;
    const audio = new Audio();
    audio.preload = 'auto';
    let current = null; // the owl that's talking right now (only one at a time)

    /* ---------- Body language (vector owl) ----------
       The owl is an SVG with separate parts: body, head, eyes, eyelids, brows, beak, stick arm,
       free wing, feet and the cap's tassel. Every part moves on a spring, so motion eases in,
       overshoots a little and settles, like hand animation.
       - Idle: breathing, blinking, glancing around, a slow sway.
       - Talking: the head bobs on stressed syllables, brows lift with loud words, and
         key words trigger gestures (wave on "Welcome", point the stick at "web apps", ...). */
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

    const GESTURES = [
        { re: /hoo-hoo|welcome|thank|talk soon/gi, name: 'wave' },
        { re: /free quote|today|launch|one (business )?day/gi, name: 'excited' },
        { re: /web apps?|mobile apps?|\bai\b|website|\bapps?\b|project|everything|design/gi, name: 'point' },
        { re: /\byou(r|'ll)?\b|\btell\b/gi, name: 'open' },
    ];

    // Gesture keyframes: [progress 0..1, value] per channel. Small opposite moves first (anticipation).
    // arm: + lifts the stick. wing: - swings out. head/body: degrees. bodyY: + is down. eyes: px.
    const MOVES = {
        wave: { dur: 1.6, keys: {
            wing: [[0, 0], [.12, 4], [.28, -16], [.42, -6], [.56, -16], [.7, -6], [1, 0]],
            head: [[0, 0], [.25, 5], [.8, 4], [1, 0]],
            body: [[0, 0], [.3, 2], [.8, 2], [1, 0]],
            footR: [[0, 0], [.25, 7], [.45, 0], [1, 0]],
            brow: [[0, 0], [.2, -5], [.8, -4], [1, 0]],
        } },
        point: { dur: 0.9, keys: {
            arm: [[0, 0], [.2, 5], [.45, -11], [.6, -8], [1, 0]],
            head: [[0, 0], [.35, -6], [.8, -4], [1, 0]],
            body: [[0, 0], [.4, -2.5], [1, 0]],
            eyeX: [[0, 0], [.25, -7], [.8, -6], [1, 0]],
            eyeY: [[0, 0], [.25, -4], [.8, -3], [1, 0]],
        } },
        open: { dur: 1.1, keys: {
            wing: [[0, 0], [.15, 3], [.4, -12], [.75, -10], [1, 0]],
            head: [[0, 0], [.4, 4], [1, 0]],
            brow: [[0, 0], [.3, -5], [.8, -4], [1, 0]],
            eyeX: [[0, 0], [.3, 2], [1, 0]],
        } },
        excited: { dur: 1.3, keys: {
            arm: [[0, 0], [.18, -4], [.42, 15], [.7, 11], [1, 0]],
            bodyY: [[0, 0], [.15, 4], [.35, -12], [.55, 3], [.7, 0], [1, 0]],
            footL: [[0, 0], [.25, 0], [.35, 8], [.55, 0], [1, 0]],
            footR: [[0, 0], [.25, 0], [.35, 8], [.55, 0], [1, 0]],
            brow: [[0, 0], [.3, -7], [.85, -6], [1, 0]],
            head: [[0, 0], [.35, -3], [.6, 3], [1, 0]],
        } },
    };
    const ease = t => t * t * (3 - 2 * t);
    const sample = (keys, p) => {
        for (let i = 1; i < keys.length; i++) {
            if (p <= keys[i][0]) {
                const [p0, v0] = keys[i - 1], [p1, v1] = keys[i];
                return v0 + (v1 - v0) * ease((p - p0) / (p1 - p0 || 1));
            }
        }
        return keys[keys.length - 1][1];
    };

    // A damped spring: stiffness k, damping ratio z (below 1 overshoots a little)
    const spring = (k, z) => ({ x: 0, v: 0, k, c: 2 * Math.sqrt(k) * z });
    const step = (s, target, dt) => { s.v += (s.k * (target - s.x) - s.c * s.v) * dt; s.x += s.v * dt; return s.x; };

    function createRig(root) {
        const svg = root.querySelector('.owl-svg');
        if (!svg) return null;
        const $ = sel => svg.querySelector(sel);
        const el = {
            body: $('.o-body'), head: $('.o-head'), arm: $('.o-arm'), wing: $('.o-wing'),
            footL: $('.o-foot-l'), footR: $('.o-foot-r'), tassel: $('.o-tassel'), pupils: $('.o-pupils'),
            brows: $('.o-brows'), mouth: $('.o-mouth'), jaw: $('.o-jaw'), lids: svg.querySelectorAll('.o-lid'),
        };
        const S = {
            body: spring(60, .6), bodyY: spring(140, .45), squash: spring(160, .4),
            head: spring(90, .5), headY: spring(220, .45),
            arm: spring(110, .55), wing: spring(100, .5),
            footL: spring(260, .6), footR: spring(260, .6),
            eyeX: spring(260, .8), eyeY: spring(260, .8), brow: spring(180, .6),
            tassel: spring(28, .18),
        };
        let talking = false, level = 0, lvl = 0, lastLevel = 0, beatSign = 1, lastBeat = 0;
        let events = [], active = [], jaw = 0;
        let nextBlink = 1.5, blinkAt = -1, glance = { x: 0, y: 0 }, nextGlance = 2;
        let t0 = performance.now(), last = t0, raf = 0, visible = true;

        const frame = now => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            const T = (now - t0) / 1000;

            // fire gestures whose key word has just been written
            active = active.filter(g => now - g.start < g.dur * 1000);
            const add = {};
            for (const g of active) {
                const p = (now - g.start) / (g.dur * 1000);
                for (const k in g.keys) add[k] = (add[k] || 0) + sample(g.keys[k], p);
            }
            const A = k => add[k] || 0;

            // speech rhythm: a quick head bob on each stressed syllable
            lvl += (level - lvl) * Math.min(1, dt * 10);
            if (talking && level > 0.45 && level - lastLevel > 0.18 && T - lastBeat > 0.22) {
                lastBeat = T; beatSign = -beatSign;
                S.headY.v += 55; S.head.v += 22 * beatSign; S.bodyY.v += 18;
            }
            lastLevel = level;

            // blinking (sometimes twice) and glancing around when idle
            if (T > nextBlink) { blinkAt = T; nextBlink = T + 2.2 + Math.random() * 3.5 + (Math.random() < .2 ? -2 : 0); }
            const bt = (T - blinkAt) / 0.16;
            const blink = bt >= 0 && bt <= 1 ? Math.sin(Math.PI * bt) : 0;
            if (!talking && T > nextGlance) {
                nextGlance = T + 1.5 + Math.random() * 2.5;
                glance = Math.random() < .4 ? { x: 0, y: 0 } : { x: (Math.random() * 2 - 1) * 7, y: (Math.random() * 2 - 1) * 3 };
            }
            if (talking) glance = { x: 0, y: 1 };

            const breathe = Math.sin(T * 1.7);
            const tgt = {
                body: (talking ? -1 : 0) + 0.8 * Math.sin(T * 0.6) + A('body'),
                bodyY: 1.2 * breathe + A('bodyY'),
                squash: 0.012 * breathe - (A('bodyY') > 2 ? 0.03 : 0),
                head: 2.5 * Math.sin(T * 0.45) + (talking ? 2 * lvl : 0) + A('head'),
                headY: 0.6 * breathe,
                arm: 2 * Math.sin(T * 0.9) + (talking ? 3 + 3 * lvl : 0) + A('arm'),
                wing: 1.5 * Math.sin(T * 1.1 + 1) + (talking ? -2 * lvl : 0) + A('wing'),
                footL: A('footL'), footR: A('footR'),
                eyeX: glance.x + A('eyeX'), eyeY: glance.y + A('eyeY'),
                brow: (talking ? -3 * lvl : 0) + A('brow'),
            };
            const v = {};
            for (const k in tgt) v[k] = step(S[k], tgt[k], dt);
            // the tassel swings opposite to how the head and body move (secondary motion)
            v.tassel = step(S.tassel, -(v.head + v.body) * 1.6 - S.head.v * 0.15, dt);
            jaw += ((talking ? level : 0) - jaw) * Math.min(1, dt * 25);

            el.body.style.transform = `translateY(${v.bodyY.toFixed(2)}px) rotate(${v.body.toFixed(2)}deg) scale(${(1 - v.squash).toFixed(4)}, ${(1 + v.squash).toFixed(4)})`;
            el.head.style.transform = `translateY(${v.headY.toFixed(2)}px) rotate(${v.head.toFixed(2)}deg)`;
            el.arm.style.transform = `rotate(${v.arm.toFixed(2)}deg)`;
            el.wing.style.transform = `rotate(${v.wing.toFixed(2)}deg)`;
            el.footL.style.transform = `translateY(${(-v.footL).toFixed(2)}px) rotate(${(-v.footL * 1.2).toFixed(2)}deg)`;
            el.footR.style.transform = `translateY(${(-v.footR).toFixed(2)}px) rotate(${(v.footR * 1.2).toFixed(2)}deg)`;
            el.tassel.style.transform = `rotate(${v.tassel.toFixed(2)}deg)`;
            el.pupils.style.transform = `translate(${v.eyeX.toFixed(2)}px, ${v.eyeY.toFixed(2)}px)`;
            el.brows.style.transform = `translateY(${v.brow.toFixed(2)}px)`;
            const lid = 0.14 + 0.86 * blink;
            el.lids.forEach(l => { l.style.transform = `scaleY(${lid.toFixed(3)})`; });
            el.mouth.style.transform = `scaleY(${Math.min(1, jaw * 1.1).toFixed(3)})`;
            el.jaw.style.transform = `translateY(${(jaw * 9).toFixed(2)}px)`;

            raf = visible ? requestAnimationFrame(frame) : 0;
        };
        const run = () => { if (!raf && visible) { last = performance.now(); raf = requestAnimationFrame(frame); } };

        // Only animate while the owl is on screen
        new IntersectionObserver(([e]) => { visible = e.isIntersecting; run(); }).observe(svg);
        if (reduceMotion) { // keep the beak and blinking, skip body movement
            for (const k in MOVES) MOVES[k] = { dur: MOVES[k].dur, keys: {} };
        }

        return {
            // New line: find where each key word starts so its gesture fires as it's spoken
            line(text) {
                events = [];
                const taken = [];
                GESTURES.forEach(({ re, name }) => {
                    for (const m of text.matchAll(re)) {
                        if (taken.some(i => Math.abs(i - m.index) < 10)) continue;
                        taken.push(m.index);
                        events.push({ at: m.index, name });
                    }
                });
                events.sort((a, b) => a.at - b.at);
            },
            // Every frame while talking: characters written so far and voice loudness 0..1
            speak(shown, lv) {
                talking = true; level = lv;
                while (events.length && events[0].at <= shown) {
                    const name = events.shift().name;
                    // don't stack the same gesture on itself
                    if (!active.some(g => g.name === name)) active.push({ ...MOVES[name], name, start: performance.now() });
                }
            },
            quiet() { talking = false; level = 0; events = []; },
        };
    }

    function createOwl(root, { lines, idleText, doneText, playLabel = 'Hear the intro' }) {
        const textEl = root.querySelector('[data-owl-text]');
        const btn = root.querySelector('[data-owl-play]');
        const label = btn && btn.querySelector('span');
        const rig = createRig(root);
        let index = -1, playing = false, raf = 0, played = false;
        // Silent mode: when the browser blocks sound, the owl still "talks" on a timer,
        // driven by the same loudness data, so the beak and text move without audio.
        let silent = false, lineStart = 0;
        let run = 0; // bumps on every start/stop so leftover timers from an old run are ignored
        const now = () => silent ? (performance.now() - lineStart) / 1000 : audio.currentTime;
        const lineDuration = line => silent ? line.lipsync.length / FPS : (audio.duration || line.lipsync.length / FPS || 3);

        const tick = () => {
            const line = lines[index];
            if (line && playing && current === owl) {
                const t = now();
                const f = Math.floor(t * FPS);
                const level = f < line.lipsync.length ? Number(line.lipsync[f]) / 9 : 0;
                const dur = lineDuration(line);
                const shown = Math.ceil(line.text.length * Math.min(1, t / (dur * 0.88)));
                if (textEl.textContent.length !== shown) textEl.textContent = line.text.slice(0, shown);
                if (rig) rig.speak(shown, level < 0.12 ? 0 : Math.min(1, level * 1.15));
                if (silent && t >= dur && !line.done) { line.done = true; owl.onEnded(); }
            }
            raf = requestAnimationFrame(tick);
        };

        const setPlaying = on => {
            playing = on;
            root.classList.toggle('is-talking', on);
            if (btn) {
                label.textContent = on ? 'Stop' : (played ? 'Replay' : playLabel);
                btn.setAttribute('aria-label', on ? 'Stop the owl' : 'Play the owl\'s intro');
            }
            cancelAnimationFrame(raf);
            if (on) raf = requestAnimationFrame(tick);
            else if (rig) rig.quiet();
        };

        const playLine = i => {
            index = i;
            if (i >= lines.length) { owl.stop(true); return Promise.resolve(); }
            textEl.textContent = '';
            if (rig) rig.line(lines[i].text);
            if (silent) {
                lines[i].done = false;
                lineStart = performance.now();
                return Promise.resolve();
            }
            audio.src = lines[i].src;
            return audio.play();
        };

        const owl = {
            root,
            get playing() { return playing; },
            get played() { return played; },
            // start(true) talks without sound (used when the browser blocks audio).
            start(quiet = false) {
                if (current && current !== owl) current.stop();
                current = owl;
                run++;
                silent = quiet;
                if (!quiet) audio.pause();
                setPlaying(true);
                return playLine(0).then(() => { played = true; }).catch(err => {
                    setPlaying(false);
                    textEl.textContent = idleText;
                    throw err;
                });
            },
            stop(finished = false) {
                if (current === owl) { audio.pause(); current = null; }
                run++;
                silent = false;
                if (finished) played = true;
                setPlaying(false);
                textEl.textContent = finished ? (doneText || idleText) : idleText;
            },
            onEnded() {
                if (!playing) return;
                textEl.textContent = lines[index].text;
                if (rig) rig.speak(lines[index].text.length, 0);
                const thisRun = run;
                setTimeout(() => playing && run === thisRun && playLine(index + 1).catch(() => owl.stop()), 450);
            },
        };

        if (btn) btn.addEventListener('click', () => owl.playing ? owl.stop() : owl.start().catch(() => {}));
        return owl;
    }

    audio.addEventListener('ended', () => current && current.onEnded());

    window.Owl = { createOwl };

})();
