/* Owlvix landing page scripts. Settings (audio files, lip-sync data) come from window.OWLVIX. */
(() => {
    const cfg = window.OWLVIX || {};

    /* ---------- Mobile menu ---------- */
    const toggle = document.querySelector('.nav__toggle');
    const links = document.querySelector('.nav__links');
    toggle.addEventListener('click', () => {
        const open = links.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open);
    });
    links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
        links.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', false);
    }));

    /* ---------- Pricing buttons preselect the service in the form ---------- */
    document.querySelectorAll('[data-service]').forEach(btn => btn.addEventListener('click', () => {
        document.getElementById('service').value = btn.dataset.service;
    }));

    /* ---------- Phone field: country flag + dial code picker ---------- */
    // Default country = the visitor's country, found from their IP address.
    // Falls back to the browser's language region (e.g. "en-GB" → gb), then the US.
    const localeCountry = () => {
        const region = (navigator.languages || [navigator.language]).map(l => (l || '').split('-')[1]).find(Boolean);
        return region && /^[a-z]{2}$/i.test(region) ? region.toLowerCase() : 'us';
    };
    let countryLookup;
    const lookupCountry = () => countryLookup ||= fetch('https://get.geojs.io/v1/ip/country.json')
        .then(r => r.json())
        .then(d => (d.country || '').toLowerCase() || localeCountry())
        .catch(localeCountry);

    if (window.intlTelInput) {
        document.querySelectorAll('[data-intl-phone]').forEach(input => {
            input.iti = intlTelInput(input, {
                initialCountryLookup: lookupCountry,
                countryOrder: ['us', 'gb', 'ca', 'au', 'ae', 'sa', 'pk', 'in'],
                // Phone rules for every country, loaded only when needed
                loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/js/utils.js'),
                // On phones the list opens full screen; keep it inside the popup so it's clickable
                fullscreenParent: input.closest('dialog') || null,
            });
        });
    }

    /* ---------- "Your project": rich text editor (Quill) ---------- */
    // The original textarea stays in the form (hidden) and always holds the editor's HTML,
    // so the form still sends a normal "message" field. The server cleans the HTML.
    if (window.Quill) {
        document.querySelectorAll('textarea[data-rich]').forEach(ta => {
            const box = document.createElement('div');
            box.className = 'rich-editor';
            const area = document.createElement('div');
            box.appendChild(area);
            ta.after(box);
            ta.hidden = true;

            const quill = new Quill(area, {
                theme: 'snow',
                placeholder: ta.placeholder,
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block', 'link'],
                        ['clean'],
                    ],
                },
            });
            if (ta.value.trim()) quill.clipboard.dangerouslyPasteHTML(ta.value);

            const label = document.querySelector(`label[for="${ta.id}"]`);
            quill.root.setAttribute('aria-label', label ? label.textContent.trim() : 'Your project');
            quill.root.setAttribute('role', 'textbox');
            quill.root.setAttribute('aria-multiline', 'true');
            // Accessibility/SEO: name the style dropdown, and give the link pop-up's
            // action links a harmless address so they count as proper links
            box.querySelectorAll('.ql-picker-label').forEach(el => el.setAttribute('aria-label', 'Text style'));
            box.querySelectorAll('.ql-tooltip a:not([href])').forEach(a => {
                a.setAttribute('href', '#');
                a.addEventListener('click', e => e.preventDefault());
            });
            if (label) label.addEventListener('click', () => quill.focus());

            // getSemanticHTML() writes every space as &nbsp;, which stops lines wrapping in emails
            const sync = () => { ta.value = quill.getText().trim() ? quill.getSemanticHTML().replace(/&nbsp;/g, ' ') : ''; };
            sync();
            quill.on('text-change', () => { sync(); ta.dispatchEvent(new Event('input')); });
            quill.root.addEventListener('blur', () => ta.dispatchEvent(new Event('blur')));
            if (ta.getAttribute('aria-invalid') === 'true') box.setAttribute('aria-invalid', 'true');
            ta.quill = quill;
            ta.editorBox = box;
        });
    }

    /* ---------- Google reCAPTCHA: load only when needed ---------- */
    // Its script is large (~800 KB), so it loads the first time someone starts filling in a form,
    // not on every page view. The check itself still runs when the form is sent.
    let recaptchaPromise;
    const loadRecaptcha = () => recaptchaPromise ||= new Promise((resolve, reject) => {
        if (!cfg.recaptchaKey) return reject(new Error('no key'));
        if (window.grecaptcha) return resolve();
        const sc = document.createElement('script');
        sc.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(cfg.recaptchaKey);
        sc.async = true;
        sc.onload = () => resolve();
        sc.onerror = () => { recaptchaPromise = null; reject(new Error('reCAPTCHA failed to load')); };
        document.head.appendChild(sc);
    });
    document.querySelectorAll('form[data-validate]').forEach(form => {
        // Real interaction only (isTrusted): the popup focuses its form by itself, and the phone
        // picker and editor send their own "input" events while starting up
        const events = ['input', 'keydown', 'pointerdown'];
        const warm = e => {
            if (!e.isTrusted) return;
            events.forEach(ev => form.removeEventListener(ev, warm));
            loadRecaptcha().catch(() => {});
            window.track?.('form_start', { form: form.classList.contains('quote-form') ? 'quote_popup' : 'contact_form' });
        };
        events.forEach(ev => form.addEventListener(ev, warm));
    });

    /* ---------- Form validation (popup + contact form) ---------- */
    // Each rule returns an error message, or '' when the value is fine.
    const rules = {
        name: v => {
            if (!v) return 'Please enter your name.';
            if (v.length < 2) return 'Name must be at least 2 characters.';
            if (v.length > 100) return 'Name must be 100 characters or less.';
            if (!/\p{L}/u.test(v)) return 'Please enter a real name.';
            return '';
        },
        email: v => {
            if (!v) return 'Please enter your email.';
            if (v.length > 150) return 'Email must be 150 characters or less.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Please enter a valid email, like name@example.com.';
            return '';
        },
        phone: (v, field) => {
            if (!v) return ''; // optional
            // With the country picker loaded, check the number against that country's rules.
            const iti = field.iti;
            if (iti && window.intlTelInput?.utils) {
                if (iti.isValidNumber()) return '';
                const country = iti.getSelectedCountry()?.name || 'this country';
                return {
                    TOO_SHORT: `This number is too short for ${country}.`,
                    IS_POSSIBLE_LOCAL_ONLY: `This number is too short for ${country}.`,
                    TOO_LONG: `This number is too long for ${country}.`,
                    INVALID_COUNTRY_CODE: 'Please pick your country from the list.',
                }[iti.getValidationError()] || `Please enter a valid ${country} phone number.`;
            }
            const digits = v.replace(/\D/g, '').length;
            if (!/^\+?[\d\s().-]+$/.test(v)) return 'Use only digits, spaces, + ( ) . or -.';
            if (digits < 7 || digits > 15) return 'Please enter a valid phone number (7–15 digits).';
            return '';
        },
        message: (v, field) => {
            if (field?.quill) v = field.quill.getText().trim(); // count the words, not the HTML
            if (!v) return 'Please tell us a little about your project.';
            if (v.length < 10) return `A few more words please (${10 - v.length} more character${v.length === 9 ? '' : 's'}).`;
            if (v.length > 5000) return 'Please keep it under 5000 characters.';
            return '';
        },
    };

    let errorId = 0;
    const showError = (field, msg) => {
        const label = field.closest('.field, label');
        let note = label.querySelector('small');
        if (msg) {
            if (!note) {
                note = document.createElement('small');
                note.id = `err-${++errorId}`;
                label.appendChild(note);
            }
            if (!note.id) note.id = `err-${++errorId}`;
            note.textContent = msg;
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', note.id);
            field.editorBox?.setAttribute('aria-invalid', 'true');
            field.quill?.root.setAttribute('aria-describedby', note.id);
        } else {
            note?.remove();
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
            field.editorBox?.removeAttribute('aria-invalid');
            field.quill?.root.removeAttribute('aria-describedby');
        }
    };

    const checkField = field => {
        const msg = rules[field.name](field.value.trim(), field);
        showError(field, msg);
        return !msg;
    };

    document.querySelectorAll('form[data-validate]').forEach(form => {
        const fields = [...form.elements].filter(el => rules[el.name]);

        fields.forEach(field => {
            // Check when the visitor leaves a field they typed in; once a field shows an error,
            // re-check as they type so the message clears as soon as it's fixed.
            field.addEventListener('blur', () => { if (field.value.trim() || field.dataset.touched) checkField(field); });
            field.addEventListener('input', () => {
                field.dataset.touched = '1';
                if (field.getAttribute('aria-invalid') === 'true') checkField(field);
            });
        });

        form.addEventListener('submit', e => {
            const bad = fields.filter(f => !checkField(f));
            if (bad.length) {
                e.preventDefault();
                bad[0].quill ? bad[0].quill.focus() : bad[0].focus();
                return;
            }
            // Send the phone in full international format, e.g. "+92 300 1234567".
            fields.forEach(f => {
                if (f.iti && f.value.trim() && window.intlTelInput?.utils) {
                    f.value = f.iti.getNumber(intlTelInput.NUMBER_FORMAT?.INTERNATIONAL || 'INTERNATIONAL');
                }
            });

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Sending…';

            // reCAPTCHA v3: fetch a fresh token from Google, then send the form.
            // If Google's script can't load, send anyway and let the server decide.
            const key = cfg.recaptchaKey;
            if (!key) return;
            e.preventDefault();
            loadRecaptcha().then(() => grecaptcha.ready(() => {
                grecaptcha.execute(key, { action: 'contact' }).then(token => {
                    let input = form.querySelector('input[name="g-recaptcha-response"]');
                    if (!input) {
                        input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'g-recaptcha-response' });
                        form.appendChild(input);
                    }
                    input.value = token;
                    form.submit();
                }, () => form.submit());
            }), () => form.submit());
        });
    });

    const { createOwl } = window.Owl;

    /* Hero headline: cycle the highlighted word (web app? → mobile app? → ...) */
    const rotator = document.querySelector('[data-rotate]');
    if (rotator && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const words = JSON.parse(rotator.dataset.rotate);
        const article = document.querySelector('[data-article]');
        let w = 0;
        setInterval(() => {
            if (document.hidden) return;
            rotator.classList.add('is-out');
            setTimeout(() => {
                w = (w + 1) % words.length;
                rotator.textContent = words[w];
                if (article) article.textContent = /^[aeiou]/i.test(words[w]) ? 'an' : 'a'; // "an AI agent", "a web app"
                rotator.classList.replace('is-out', 'is-in');
                rotator.offsetWidth; // apply the start position before animating in
                rotator.classList.remove('is-in');
            }, 350);
        }, 2800);
    }

    /* Hero: the owl "chats" in the bubble. Plays when the visitor presses the play button. */
    const heroRoot = document.getElementById('owl-intro');
    if (heroRoot) {
        createOwl(heroRoot, {
            lines: cfg.intro,
            idleText: heroRoot.querySelector('[data-owl-text]').textContent,
            doneText: cfg.intro[cfg.intro.length - 1].text,
        });
    }

    /* ---------- Our work: category filter + "Show all" ---------- */
    const work = document.querySelector('[data-work]');
    if (work) {
        const cards = [...work.children];
        const limit = Number(work.dataset.limit) || 8;
        const more = document.querySelector('.work__more');
        const moreBtn = document.querySelector('[data-work-more]');
        let filter = 'all', expanded = false;
        const render = () => {
            const matching = cards.filter(c => filter === 'all' || c.dataset.category === filter);
            cards.forEach(c => { c.hidden = true; });
            matching.forEach((c, i) => { c.hidden = !expanded && i >= limit; });
            more.hidden = expanded || matching.length <= limit;
            moreBtn.textContent = `Show all ${matching.length} projects`;
        };
        document.querySelectorAll('[data-filter]').forEach(btn => btn.addEventListener('click', () => {
            document.querySelectorAll('[data-filter]').forEach(b => { b.classList.toggle('is-active', b === btn); b.setAttribute('aria-pressed', b === btn); });
            filter = btn.dataset.filter; expanded = false; render();
            window.track?.('project_filter', { category: filter });
        }));
        moreBtn.addEventListener('click', () => { expanded = true; render(); });
        render();
    }

    /* ---------- Reviews: visitor's own country first ---------- */
    // Uses the same country lookup as the phone field. Others keep the default order.
    // A spinner shows until this finishes; after 2.5s we show the default order anyway.
    const reviewBoxes = document.querySelectorAll('[data-reviews]');
    const showReviews = () => reviewBoxes.forEach(box => { box.classList.remove('is-loading'); box.removeAttribute('aria-busy'); });
    const reviewTimeout = setTimeout(showReviews, 2500);
    lookupCountry().then(code => {
        clearTimeout(reviewTimeout);
        reviewBoxes.forEach(box => {
            if (!box.classList.contains('is-loading')) return; // already shown, don't reshuffle under the reader
            const mine = [...box.children].filter(el => el.dataset.country === code);
            mine.reverse().forEach(el => box.prepend(el));
            if (mine.length && box.hasAttribute('data-rotate-reviews')) {
                [...box.children].forEach((el, i) => { el.hidden = i > 0; });
            }
        });
        showReviews();
    });

    /* Popup: show one review at a time, cycling every few seconds */
    // Keep the box as tall as the longest review, so the owl below never moves
    const fitTallest = box => {
        if (!box.offsetWidth) return;
        const items = [...box.children];
        const shown = items.map(el => el.hidden);
        box.style.minHeight = '';
        let max = 0;
        items.forEach(el => { items.forEach(o => { o.hidden = o !== el; }); max = Math.max(max, el.offsetHeight); });
        items.forEach((el, i) => { el.hidden = shown[i]; });
        box.style.minHeight = max + 'px';
    };
    document.querySelectorAll('[data-rotate-reviews]').forEach(box => {
        // Re-measure only when the width changes (fitTallest changes the height itself, which would
        // otherwise re-trigger this and log "ResizeObserver loop" errors), and do it on the next frame
        let lastWidth = 0;
        new ResizeObserver(([entry]) => {
            const width = Math.round(entry.contentRect.width);
            if (width === lastWidth) return;
            lastWidth = width;
            requestAnimationFrame(() => fitTallest(box));
        }).observe(box.parentElement);
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        setInterval(() => {
            const items = [...box.children];
            if (document.hidden || items.length < 2) return;
            if (box.classList.contains('is-loading')) return;
            // brief spinner, then the next review
            box.classList.add('is-loading');
            setTimeout(() => {
                const i = items.findIndex(el => !el.hidden);
                items[i].hidden = true;
                items[(i + 1) % items.length].hidden = false;
                box.classList.remove('is-loading');
            }, 600);
        }, 5000);
    });

    /* ---------- Free quote popup ---------- */
    const modal = document.getElementById('quote-modal');
    const popupOwl = createOwl(modal.querySelector('.quote-owl'), {
        lines: cfg.quote,
        idleText: cfg.quote[cfg.quote.length - 1].text,
        playLabel: 'Hear the owl',
    });

    // Browsers block sound until the visitor clicks. When that happens the owl still talks
    // (beak and text move, sound muted) and a "Tap for sound" button shows. The first tap
    // anywhere in the popup restarts the intro with the voice on.
    let mutedIntro = false;
    const setMuted = on => { mutedIntro = on; modal.classList.toggle('is-muted', on); };
    const tryTalk = () => {
        if (popupOwl.playing) return;
        popupOwl.start().catch(() => {
            setMuted(true);
            popupOwl.start(true);
        });
    };
    const enableSound = () => {
        if (!mutedIntro) return;
        setMuted(false);
        popupOwl.start().catch(() => {});
    };
    modal.addEventListener('pointerdown', e => {
        if (mutedIntro && !e.target.closest('[data-close]') && e.target !== modal) enableSound();
    });
    modal.addEventListener('keydown', e => { if (e.key !== 'Escape' && e.key !== 'Tab') enableSound(); });

    const openModal = (how = 'button') => {
        if (modal.open) return;
        modal.showModal();
        window.track?.('quote_open', { trigger: how });
        document.body.classList.add('modal-open');
        tryTalk();
    };
    const closeModal = () => modal.open && modal.close();

    modal.addEventListener('close', () => {
        if (!document.querySelector('dialog[open]')) document.body.classList.remove('modal-open');
        popupOwl.stop(popupOwl.played);
        setMuted(false);
    });
    modal.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', closeModal));
    // Click on the dark backdrop closes it
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // "Get a free quote" buttons open the popup (they still link to #contact without JavaScript)
    document.querySelectorAll('[data-quote]').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        links.classList.remove('is-open');
        openModal('button');
    }));

    // Open automatically once the visitor has had a look: after 15 seconds, or after scrolling 40% of the page,
    // whichever comes first. (Opening it straight away covered the page before people saw what Owlvix does.)
    // Skip it right after a form was sent, or when the visitor came straight to the contact form, and never
    // open it over another popup (the exit survey) or while someone is typing in the contact form.
    // The landing page has the form at the top, so it sets data-auto-quote="off".
    const skip = document.body.dataset.noPopup === '1' || document.body.dataset.autoQuote === 'off' || location.hash === '#contact';
    if (!skip) {
        let autoDone = false;
        const autoOpen = trigger => {
            if (autoDone) return;
            if (document.getElementById('exit-survey')?.dataset.shown) { autoDone = true; return; } // one popup per visit is enough
            if (document.querySelector('dialog[open]') || document.activeElement?.matches?.('input, textarea, select, [contenteditable="true"]')) return;
            autoDone = true;
            removeEventListener('scroll', onScrollDepth);
            clearTimeout(timer);
            openModal(trigger);
        };
        const onScrollDepth = () => {
            const depth = (scrollY + innerHeight) / document.documentElement.scrollHeight;
            if (depth >= 0.4) autoOpen('auto_scroll');
        };
        const timer = setTimeout(() => autoOpen('auto_time'), 15000);
        addEventListener('scroll', onScrollDepth, { passive: true });
    }

    /* ---------- "Before you go" exit survey (partials/exit-survey) ---------- */
    // Shown at most once per 30 days, never to someone who sent an enquiry, not in the first 3 seconds,
    // and never while someone is typing. Most visitors leave within seconds, often with the quote popup
    // still open: if they haven't typed in it, the survey takes its place.
    // Computer: the mouse leaves through the top of the window (towards the tabs / close button).
    // Phone: a fast scroll back up after reading part of the page.
    // Browsers don't allow anything to show while a tab is actually closing, so those exits can't be caught.
    (() => {
        const survey = document.getElementById('exit-survey');
        if (!survey) return;
        const store = {
            get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } },
            set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} },
        };
        const DAYS_30 = 30 * 864e5;
        const shownAt = Number(store.get('owlvix-survey')) || 0;
        if (store.get('owlvix-lead') || Date.now() - shownAt < DAYS_30 || document.body.dataset.noPopup === '1') return;

        const touch = matchMedia('(pointer: coarse)').matches;
        const start = Date.now();
        const minTime = 3000;
        let done = false;

        const typing = () => document.activeElement?.matches?.('input, textarea, select, [contenteditable="true"]');
        const show = trigger => {
            if (done || Date.now() - start < minTime || typing()) return;
            const open = document.querySelector('dialog[open]');
            if (open) {
                // Only swap out an untouched quote popup; anything else (or a half-filled form) stays
                const touched = [...open.querySelectorAll('input:not([type="hidden"]), textarea, .ql-editor')]
                    .some(f => (f.value ?? f.textContent).trim());
                if (open !== modal || touched) return;
                closeModal();
            }
            done = true;
            store.set('owlvix-survey', String(Date.now()));
            survey.dataset.shown = '1';
            survey.showModal();
            document.body.classList.add('modal-open');
            window.track?.('exit_survey_shown', { trigger });
            cleanup();
        };

        // Computer: pointer leaves through the top edge
        const onLeave = e => { if (!e.relatedTarget && e.clientY <= 0) show('exit_intent'); };
        // Phone: scrolled at least half a screen down, then a fast flick back up (often just before "back")
        let maxY = 0, lastY = scrollY, lastT = performance.now();
        const onScroll = () => {
            const y = scrollY, t = performance.now();
            maxY = Math.max(maxY, y);
            const speed = (lastY - y) / Math.max(1, t - lastT); // px per ms, upwards
            if (maxY > innerHeight * 0.5 && speed > 2.5 && y < maxY - innerHeight * 0.4) show('fast_scroll_up');
            lastY = y; lastT = t;
        };
        const cleanup = () => {
            document.removeEventListener('mouseout', onLeave);
            removeEventListener('scroll', onScroll);
        };
        if (touch) addEventListener('scroll', onScroll, { passive: true });
        else document.addEventListener('mouseout', onLeave);

        // Closing
        const close = () => survey.open && survey.close();
        survey.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
        survey.addEventListener('click', e => { if (e.target === survey) close(); });
        survey.addEventListener('close', () => { if (!document.querySelector('dialog[open]')) document.body.classList.remove('modal-open'); });

        // The form: pick a reason, then (optionally) a comment and email
        const form = survey.querySelector('[data-survey]');
        const more = form.querySelector('[data-survey-more]');
        const commentLabel = form.querySelector('[data-survey-comment-label]');
        const comment = form.elements.comment;
        const error = form.querySelector('[data-survey-error]');
        const submit = form.querySelector('button[type="submit"]');
        const showError = msg => { error.textContent = msg; error.hidden = !msg; };

        form.addEventListener('change', e => {
            if (e.target.name !== 'reason') return;
            more.hidden = false;
            submit.disabled = false;
            const other = e.target.value === 'other';
            commentLabel.innerHTML = other ? 'Please tell us a few words' : 'Anything else you’d like to tell us? <span class="optional">(optional)</span>';
            comment.required = other;
            showError('');
        });

        const doneText = {
            price: 'Most quotes are free and without obligation, and our monthly developer plan starts at $100.',
            info: 'Ask us anything in a quote request. We reply within one business day.',
            later: 'No rush. Your quote is free whenever you’re ready.',
        };

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const reason = form.elements.reason.value;
            if (!reason) return showError('Please pick an answer.');
            if (reason === 'other' && !comment.value.trim()) { comment.focus(); return showError('Please tell us a few words.'); }
            const email = form.elements.email.value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { form.elements.email.focus(); return showError('Please enter a valid email, or leave it empty.'); }

            const body = new FormData(form);
            body.set('page', location.pathname + location.hash);
            submit.disabled = true;
            submit.textContent = 'Sending…';
            try {
                const res = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(Object.values(data.errors || {})[0]?.[0] || (res.status === 429 ? 'Too many tries. Please wait a minute.' : 'Something went wrong. Please try again.'));
                }
                window.track?.('exit_survey_answered', { reason });
                survey.querySelector('[data-survey-done-text]').textContent = doneText[reason] || 'We read every answer.';
                form.hidden = true;
                const thanks = survey.querySelector('[data-survey-done]');
                thanks.hidden = false;
                thanks.querySelector('h2').focus();
            } catch (err) {
                showError(err.message);
                submit.disabled = false;
                submit.textContent = 'Send';
            }
        });

        // "Get a free quote" from the thank-you step opens the quote popup
        survey.querySelector('[data-survey-quote]').addEventListener('click', () => {
            close();
            openModal('exit_survey');
        });
    })();

    /* ---------- Built-in visitor stats (Admin → Traffic) ---------- */
    // One small message per page view: page, where the visitor came from, country (the same lookup
    // as the phone flag) and whether this browser has been here before. No cookies, nothing personal.
    (() => {
        let returning = false;
        try { returning = localStorage.getItem('owlvix-seen') === '1'; localStorage.setItem('owlvix-seen', '1'); } catch (e) {}
        const utm = new URLSearchParams(location.search).get('utm_source') || '';
        const send = country => {
            const body = new URLSearchParams({ path: location.pathname, ref: document.referrer || '', country: country || '', new: returning ? '0' : '1', utm });
            if (!navigator.sendBeacon?.('/t', body)) fetch('/t', { method: 'POST', body, keepalive: true }).catch(() => {});
        };
        lookupCountry().then(c => send(/^[a-z]{2}$/.test(c) ? c : ''), () => send(''));
    })();
})();
