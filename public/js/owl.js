/* Talking owl engine, shared by the homepage and the thank-you page. Exposes window.Owl. */
(() => {
    /* ---------- Talking owl ----------
       Plays voice lines, writes each line as it's spoken and moves the beak in time with
       the voice, using loudness data measured from each audio file (30 steps a second). */
    const FPS = 30;
    const audio = new Audio();
    audio.preload = 'auto';
    let current = null; // the owl that's talking right now (only one at a time)

    function createOwl(root, { lines, idleText, doneText, playLabel = 'Hear the intro' }) {
        const textEl = root.querySelector('[data-owl-text]');
        const btn = root.querySelector('[data-owl-play]');
        const label = btn && btn.querySelector('span');
        const mouth = root.querySelector('.owl__mouth');
        let index = -1, playing = false, raf = 0, open = 1, played = false;
        // Silent mode: when the browser blocks sound, the owl still "talks" on a timer,
        // driven by the same loudness data, so the beak and text move without audio.
        let silent = false, lineStart = 0;
        let run = 0; // bumps on every start/stop so leftover timers from an old run are ignored
        const now = () => silent ? (performance.now() - lineStart) / 1000 : audio.currentTime;
        const lineDuration = line => silent ? line.lipsync.length / FPS : (audio.duration || line.lipsync.length / FPS || 3);

        const setMouth = v => {
            open += (v - open) * 0.55; // ease so it doesn't flicker
            root.classList.toggle('is-closed', open < 0.14);
            mouth.style.transform = `scaleY(${(0.3 + 0.7 * Math.min(1, open)).toFixed(3)})`;
        };

        const tick = () => {
            const line = lines[index];
            if (line && playing && current === owl) {
                const t = now();
                const f = Math.floor(t * FPS);
                const level = f < line.lipsync.length ? Number(line.lipsync[f]) / 9 : 0;
                setMouth(level < 0.12 ? 0 : Math.min(1, level * 1.15));
                const dur = lineDuration(line);
                const shown = Math.ceil(line.text.length * Math.min(1, t / (dur * 0.88)));
                if (textEl.textContent.length !== shown) textEl.textContent = line.text.slice(0, shown);
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
            else { open = 1; mouth.style.transform = ''; root.classList.remove('is-closed'); }
        };

        const playLine = i => {
            index = i;
            if (i >= lines.length) { owl.stop(true); return Promise.resolve(); }
            textEl.textContent = '';
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
                setMouth(0);
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
