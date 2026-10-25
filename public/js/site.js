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
            // If Google's script didn't load, send anyway and let the server decide.
            const key = cfg.recaptchaKey;
            if (!key || !window.grecaptcha) return;
            e.preventDefault();
            grecaptcha.ready(() => {
                grecaptcha.execute(key, { action: 'contact' }).then(token => {
                    let input = form.querySelector('input[name="g-recaptcha-response"]');
                    if (!input) {
                        input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'g-recaptcha-response' });
                        form.appendChild(input);
                    }
                    input.value = token;
                    form.submit();
                }, () => form.submit());
            });
        });
    });

    const { createOwl } = window.Owl;

    /* Hero headline: cycle the highlighted word (web app? → mobile app? → ...) */
    const rotator = document.querySelector('[data-rotate]');
    if (rotator && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const words = JSON.parse(rotator.dataset.rotate);
        let w = 0;
        setInterval(() => {
            if (document.hidden) return;
            rotator.classList.add('is-out');
            setTimeout(() => {
                w = (w + 1) % words.length;
                rotator.textContent = words[w];
                rotator.classList.replace('is-out', 'is-in');
                rotator.offsetWidth; // apply the start position before animating in
                rotator.classList.remove('is-in');
            }, 350);
        }, 2800);
    }

    /* Hero: the owl "chats" in the bubble. Plays when the visitor presses the play button. */
    const heroRoot = document.getElementById('owl-intro');
    const heroText = heroRoot.querySelector('[data-owl-text]').textContent;
    createOwl(heroRoot, {
        lines: cfg.intro,
        idleText: heroText,
        doneText: cfg.intro[cfg.intro.length - 1].text,
    });

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
            document.querySelectorAll('[data-filter]').forEach(b => b.classList.toggle('is-active', b === btn));
            filter = btn.dataset.filter; expanded = false; render();
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
        new ResizeObserver(() => fitTallest(box)).observe(box.parentElement);
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

    const openModal = () => {
        if (modal.open) return;
        modal.showModal();
        document.body.classList.add('modal-open');
        tryTalk();
    };
    const closeModal = () => modal.open && modal.close();

    modal.addEventListener('close', () => {
        document.body.classList.remove('modal-open');
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
        openModal();
    }));

    // Open automatically on every page load, shortly after the page appears.
    // Skip it right after a form was sent, or when the visitor came straight to the contact form.
    const skip = document.body.dataset.noPopup === '1' || location.hash === '#contact';
    if (!skip) {
        setTimeout(openModal, 900);
    }
})();
