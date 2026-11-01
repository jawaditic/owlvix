/* Owlvix landing page scripts. Settings (audio files, lip-sync data) come from window.OWLVIX. */
(() => {
    const cfg = window.OWLVIX || {};

    /* ---------- Links to a section from another page (e.g. owlvix.com/#contact) ---------- */
    // The browser jumps to the section right away, but fonts, reviews, images and owls load afterwards and
    // push it down. Keep the section in place while the page settles (about 4 s), unless the visitor scrolls.
    (() => {
        const id = decodeURIComponent(location.hash.slice(1));
        const target = id && document.getElementById(id);
        if (!target || !('ResizeObserver' in window)) return;
        let done = false;
        const stop = () => { done = true; ro.disconnect(); ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(t => removeEventListener(t, stop)); };
        const pin = () => { if (!done) target.scrollIntoView({ block: 'start', behavior: 'instant' }); };
        const ro = new ResizeObserver(pin);
        ro.observe(document.body);
        ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(t => addEventListener(t, stop, { passive: true }));
        addEventListener('load', () => { pin(); setTimeout(pin, 600); }, { once: true });
        setTimeout(stop, 4000);
    })();

    /* ---------- Mobile menu ---------- */
    const toggle = document.querySelector('.nav__toggle');
    const links = document.querySelector('.nav__links');
    // On phones it fills the screen below the header (the page behind stays still); ☰ turns into ✕.
    // The fold-out section holding the current page opens by itself.
    const setMenu = open => {
        links.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open);
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        document.documentElement.classList.toggle('nav-open', open);
        if (open) {
            links.scrollTop = 0;
            const here = links.querySelector('.nav-drop:not(.nav-drop--more) [aria-current="page"]')?.closest('[data-nav-drop]');
            // after this tap's "close dropdowns on outside click" has run
            if (here) setTimeout(() => { here.classList.add('is-open'); here.querySelector('button').setAttribute('aria-expanded', true); });
        }
    };
    toggle.addEventListener('click', () => setMenu(!links.classList.contains('is-open')));
    links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && links.classList.contains('is-open')) { setMenu(false); toggle.focus(); } });
    matchMedia('(min-width: 761px)').addEventListener?.('change', e => { if (e.matches) setMenu(false); });

    /* ---------- "Free tools" dropdown in the menu (also opens on hover/focus via CSS) ---------- */
    document.querySelectorAll('[data-nav-drop]').forEach(drop => {
        const btn = drop.querySelector('button');
        const set = open => { drop.classList.toggle('is-open', open); btn.setAttribute('aria-expanded', open); };
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const open = !drop.classList.contains('is-open');
            if (open) document.querySelectorAll('[data-nav-drop].is-open').forEach(d => { if (d !== drop) { d.classList.remove('is-open'); d.querySelector('button').setAttribute('aria-expanded', false); } });
            set(open);
        });
        document.addEventListener('click', e => { if (!drop.contains(e.target)) set(false); });
        drop.addEventListener('keydown', e => { if (e.key === 'Escape') { set(false); btn.focus(); } });
    });

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

    const setupPhones = () => {
        if (!window.intlTelInput) return;
        document.querySelectorAll('[data-intl-phone]').forEach(input => {
            if (input.iti) return;
            input.iti = intlTelInput(input, {
                initialCountryLookup: lookupCountry,
                countryOrder: ['us', 'gb', 'ca', 'au', 'ae', 'sa', 'pk', 'in'],
                // Phone rules for every country, loaded only when needed
                loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/js/utils.js'),
                // On phones the list opens full screen; keep it inside the popup so it's clickable
                fullscreenParent: input.closest('dialog') || null,
            });
        });
    };

    /* ---------- "Your project": rich text editor (Quill) ---------- */
    // The original textarea stays in the form (hidden) and always holds the editor's HTML,
    // so the form still sends a normal "message" field. The server cleans the HTML.
    const setupEditors = () => {
        if (!window.Quill) return;
        document.querySelectorAll('textarea[data-rich]').forEach(ta => {
            if (ta.quill) return;
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
            const tips = { bold: 'Bold — make the selected text thick', italic: 'Italic — slant the selected text', underline: 'Underline the selected text', strike: 'Strike through the selected text',
                ordered: 'Numbered list (1, 2, 3…)', bullet: 'Bullet list (•)', blockquote: 'Quote — set the text apart', 'code-block': 'Code — for code or exact text', link: 'Link — turn the selected text into a link', clean: 'Clear formatting from the selected text' };
            const names = { bold: 'Bold', italic: 'Italic', underline: 'Underline', strike: 'Strikethrough', blockquote: 'Quote', 'code-block': 'Code', link: 'Link', clean: 'Clear formatting' };
            box.querySelectorAll('.ql-toolbar button').forEach(b => {
                const key = [...b.classList].find(c => c.startsWith('ql-'))?.slice(3);
                const name = key === 'list' ? (b.value === 'ordered' ? 'Numbered list' : 'Bullet list') : names[key];
                if (name) b.setAttribute('aria-label', name); // Quill's own labels are lower-case code names
                if (key && tips[key === 'list' ? b.value : key]) b.dataset.tip = tips[key === 'list' ? b.value : key];
            });
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
    };

    /* ---------- Load the phone picker and editor only when a form is about to be used ---------- */
    // They add ~200 KB, so they load when a form comes near the screen, someone clicks into a form,
    // or the quote popup opens. Until then the fields are a plain phone box and text box, which work too.
    let formLibs;
    const loadScript = src => new Promise((resolve, reject) => {
        const sc = document.createElement('script');
        sc.src = src;
        sc.onload = resolve;
        sc.onerror = reject;
        document.head.appendChild(sc);
    });
    const loadFormLibs = () => formLibs ||= Promise.all([
        window.intlTelInput || loadScript('https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/js/intlTelInput.min.js').then(setupPhones),
        window.Quill || loadScript('https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js').then(setupEditors),
    ]).then(() => { setupPhones(); setupEditors(); }).catch(() => { formLibs = null; });
    if ('IntersectionObserver' in window) {
        const near = new IntersectionObserver(entries => {
            if (entries.some(e => e.isIntersecting)) { near.disconnect(); loadFormLibs(); }
        }, { rootMargin: '400px 0px' });
        document.querySelectorAll('main form[data-validate]').forEach(f => near.observe(f));
    } else {
        loadFormLibs();
    }
    document.addEventListener('focusin', e => { if (e.target.closest?.('form')) loadFormLibs(); });

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
    // A reCAPTCHA token for forms sent with fetch (the SEO tips and audit forms). Empty when it can't be had;
    // the server then decides.
    window.owlvixCaptcha = action => !cfg.recaptchaKey ? Promise.resolve('') : loadRecaptcha()
        .then(() => new Promise(done => grecaptcha.ready(() => grecaptcha.execute(cfg.recaptchaKey, { action }).then(done, () => done('')))))
        .catch(() => '');
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

    /* ---------- Live validation for the other forms (tools, tips, audit request, exit survey) ---------- */
    // Forms marked data-live. Same behaviour as the quote forms: check a field when the visitor leaves it,
    // re-check while they type once it shows an error, mark it green when fixed, and block sending until valid.
    const liveRules = {
        url: v => {
            if (!v) return 'Please enter your website address.';
            if (/\s/.test(v)) return 'A web address has no spaces, e.g. example.com.';
            let host = '';
            try { host = new URL(/^https?:\/\//i.test(v) ? v : 'https://' + v).hostname.toLowerCase(); } catch { return 'Please enter a valid address, like example.com.'; }
            if (!host.includes('.') || /^[\d.]+$/.test(host) || host === 'localhost' || /\.(local|test|localhost)$/.test(host)) return 'Please enter a public website, like example.com.';
            if (!/\.[a-z]{2,}$/i.test(host)) return 'That domain looks incomplete, e.g. example.com.';
            return '';
        },
        name: (v, f) => (!v && !f.required ? '' : rules.name(v)),
        email: (v, f) => (!v && !f.required ? '' : rules.email(v)),
        notes: v => (v.length > 1000 ? `Please keep it under 1000 characters (${v.length - 1000} too many).` : ''),
        comment: (v, f) => (f.required && !v ? 'Please tell us a few words.' : v.length > 1000 ? 'Please keep it under 1000 characters.' : ''),
    };
    const liveNote = field => {
        if (field.liveNote) return field.liveNote;
        const note = document.createElement('small');
        note.className = 'live-err';
        note.id = `live-err-${++errorId}`;
        note.setAttribute('role', 'alert');
        // In the big tool search bars the message goes under the whole bar, so the icon doesn't move
        const anchor = field.closest('.speed-form') || field;
        anchor.after(note);
        return (field.liveNote = note);
    };
    const liveCheck = field => {
        const msg = liveRules[field.name](field.value.trim(), field);
        const note = liveNote(field);
        note.textContent = msg;
        note.hidden = !msg;
        field.toggleAttribute('aria-invalid', !!msg);
        if (msg) field.setAttribute('aria-describedby', note.id); else field.removeAttribute('aria-describedby');
        field.classList.toggle('is-valid', !msg && !!field.value.trim());
        return !msg;
    };
    document.querySelectorAll('form[data-live]').forEach(form => {
        const fields = () => [...form.elements].filter(el => liveRules[el.name] && el.type !== 'hidden' && !el.disabled);
        fields().forEach(field => {
            field.addEventListener('blur', () => { if (field.value.trim() || field.dataset.touched) liveCheck(field); });
            field.addEventListener('input', () => {
                field.dataset.touched = '1';
                if (field.hasAttribute('aria-invalid') || field.classList.contains('is-valid')) liveCheck(field);
            });
        });
    });
    // Runs before each page's own submit code (document, capture phase) and stops it when something is wrong
    document.addEventListener('submit', e => {
        const form = e.target;
        if (!form.matches?.('form[data-live]')) return;
        const bad = [...form.elements].filter(el => liveRules[el.name] && el.type !== 'hidden' && !el.disabled && !liveCheck(el));
        if (bad.length) {
            e.preventDefault();
            e.stopImmediatePropagation();
            bad[0].focus();
        }
    }, true);

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

    /* Owls that don't talk (founder card, call-to-action boxes, 404, popups) still breathe, blink and glance around */
    document.querySelectorAll('[data-owl-idle]').forEach(el => { el.owlRig = window.Owl?.idleOwl(el); });

    /* Front desk owl (homepage contact section): click a question and the owl "says" the answer —
       the text types out in its bubble while the beak moves (no sound). */
    document.querySelectorAll('[data-desk-qa]').forEach(desk => {
        const bubble = desk.querySelector('[data-desk-answer]');
        const rig = desk.querySelector('[data-owl-idle]')?.owlRig;
        const buttons = desk.querySelectorAll('[data-answer]');
        let raf = 0;
        const say = text => {
            cancelAnimationFrame(raf);
            rig?.line(text);
            const start = performance.now(), dur = Math.max(1200, text.length * 38);
            const step = now => {
                const t = Math.min(1, (now - start) / dur);
                const shown = Math.ceil(text.length * t);
                bubble.textContent = text.slice(0, shown);
                // beak opens and closes like speech while typing
                rig?.speak(shown, t < 1 ? 0.35 + 0.55 * Math.abs(Math.sin(now / 70)) : 0);
                if (t < 1) raf = requestAnimationFrame(step);
                else { bubble.textContent = text; setTimeout(() => rig?.quiet(), 250); }
            };
            raf = requestAnimationFrame(step);
        };
        buttons.forEach(btn => btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.toggle('is-active', b === btn));
            say(btn.dataset.answer);
            window.track?.('owl_question', { question: btn.textContent.trim() });
        }));
        // Say the greeting the first time the desk comes into view
        if ('IntersectionObserver' in window) {
            const greeting = bubble.textContent.trim();
            const seen = new IntersectionObserver(entries => {
                if (!entries.some(e => e.isIntersecting)) return;
                seen.disconnect();
                if (!desk.querySelector('.is-active')) say(greeting);
            }, { threshold: .5 });
            seen.observe(desk);
        }
    });

    /* Talking owls: the homepage hero and the About page. The owl "chats" in the bubble when the visitor
       presses "Hear the intro" or clicks/taps the owl itself (click again to stop). */
    const talkers = [document.getElementById('owl-intro'), ...document.querySelectorAll('[data-owl-talk]')].filter(Boolean);
    talkers.forEach(root => {
        const lines = cfg[root.dataset.owlTalk || 'intro'];
        if (!lines?.length) return;
        const owl = createOwl(root, {
            lines,
            idleText: root.querySelector('[data-owl-text]').textContent,
            doneText: lines[lines.length - 1].text,
        });
        const body = root.querySelector('.owl-wrap');
        if (!body) return;
        body.setAttribute('role', 'button');
        body.setAttribute('tabindex', '0');
        body.setAttribute('aria-label', 'Play the owl\'s intro');
        const toggle = () => {
            if (!owl.playing) window.track?.('owl_play', { where: (root.id || 'owl') + '_click' });
            owl.playing ? owl.stop() : owl.start().catch(() => {});
        };
        body.addEventListener('click', toggle);
        body.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
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
        loadFormLibs();
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
    // The server turned the quote down (e.g. a field it didn't accept): show the popup again with the message
    if (modal.hasAttribute('data-reopen')) openModal('errors');
    // Click on the dark backdrop closes it
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // "Get a free quote" buttons open the popup (they still link to #contact without JavaScript)
    document.querySelectorAll('[data-quote]').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        links.classList.remove('is-open');
        openModal('button');
    }));

    // Open automatically after 30 seconds on the page, once the visitor has had a look at what Owlvix does.
    // Skip it right after a form was sent, or when the visitor came straight to the contact form. If another
    // popup is open or someone is typing at that moment, try again a few seconds later.
    // The landing and service pages have the form at the top, so they set data-auto-quote="off".
    const skip = document.body.dataset.noPopup === '1' || document.body.dataset.autoQuote === 'off' || location.hash === '#contact';
    if (!skip) {
        const autoOpen = () => {
            if (document.getElementById('exit-survey')?.dataset.shown) return; // one popup per visit is enough
            if (document.querySelector('dialog[open]') || document.activeElement?.matches?.('input, textarea, select, [contenteditable="true"]')) {
                setTimeout(autoOpen, 5000);
                return;
            }
            openModal('auto_time');
        };
        setTimeout(autoOpen, 30000);
    }

    /* ---------- "How we charge" popup (partials/pricing-modal) ---------- */
    // Opened by [data-pricing] links; without JavaScript they still go to the pricing section on the homepage.
    (() => {
        const dlg = document.getElementById('pricing-modal');
        if (!dlg) return;
        const open = e => {
            e.preventDefault();
            links.classList.remove('is-open');
            if (dlg.open) return;
            document.querySelector('dialog[open]')?.close();
            dlg.showModal();
            document.body.classList.add('modal-open');
            window.track?.('pricing_open', { from: location.pathname });
        };
        document.querySelectorAll('[data-pricing]').forEach(a => a.addEventListener('click', open));
        const close = () => dlg.open && dlg.close();
        dlg.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
        dlg.addEventListener('click', e => { if (e.target === dlg) close(); });
        dlg.addEventListener('close', () => { if (!document.querySelector('dialog[open]')) document.body.classList.remove('modal-open'); });
        // "Get a free quote": the page's own form if it has one, otherwise the quote popup
        dlg.querySelector('[data-pricing-quote]').addEventListener('click', e => {
            e.preventDefault();
            close();
            const form = document.getElementById('quote-form') || document.querySelector('#contact form');
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            else openModal('pricing_popup');
        });
    })();

    /* ---------- "Before you go" exit survey (partials/exit-survey) ---------- */
    // Opens by itself after 1 minute on the page. After that minute it also opens when the visitor is about
    // to leave: on a computer when the mouse leaves through the top of the window (towards the tabs / close
    // button), on a phone after a fast scroll back up. Nothing shows in the first minute.
    // Shown at most once per 30 days, never to someone who sent an enquiry, and never while someone is typing.
    // An untouched quote popup is swapped for it; a half-filled one is left alone.
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
        const minTime = 60000; // 1 minute
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
        // After 1 minute it opens by itself (retrying while someone is typing); leaving after that also opens it
        const timed = () => { if (done) return; if (typing()) { setTimeout(timed, 5000); return; } show('auto_time'); };
        setTimeout(timed, minTime + 50);

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
    /* ---------- Tooltips ---------- */
    // Buttons with no words (an icon or symbol like ↑ ✕ ⧉ 🖥) explain themselves with a small dark label on hover
    // or keyboard focus. The label comes from data-tip, then title, then aria-label. Buttons with words don't need
    // one (a data-tip still forces it). The title moves into data-tip so the browser's own tooltip doesn't show twice.
    (() => {
        const tip = document.createElement('div');
        tip.className = 'ow-tip'; tip.setAttribute('role', 'tooltip'); tip.hidden = true;
        let on = null;
        // words people can't see (screen-reader-only, or squeezed to 1px like the builders' "Undo") don't count
        const hidden = x => x.matches('.sr-only') || (x.offsetWidth <= 1 && x.offsetHeight <= 1);
        const wordless = el => { let t = el.textContent; el.querySelectorAll('*').forEach(x => { if (x.textContent && hidden(x)) t = t.replace(x.textContent, ''); }); return !t.replace(/[^\p{L}\p{N}]/gu, ''); };
        const hide = () => { tip.hidden = true; tip.classList.remove('is-on'); on = null; };
        const show = el => {
            if (!el.dataset.tip && !wordless(el)) return;
            if (el.title) {
                if (!el.dataset.tip) el.dataset.tip = el.title;
                if (!el.hasAttribute('aria-label')) el.setAttribute('aria-label', el.title);
                el.removeAttribute('title');
            }
            const text = el.dataset.tip || el.getAttribute('aria-label'); if (!text) return; // greyed-out buttons too: they say what they would do
            const host = document.fullscreenElement && document.fullscreenElement !== document.documentElement ? document.fullscreenElement : document.body;
            if (tip.parentNode !== host) host.append(tip);
            on = el; tip.textContent = text; tip.hidden = false; tip.classList.remove('is-on', 'is-below');
            const r = el.getBoundingClientRect(), w = tip.offsetWidth, h = tip.offsetHeight;
            let top = r.top - h - 8; if (top < 6) { top = r.bottom + 8; tip.classList.add('is-below'); }
            const left = Math.max(6, Math.min(innerWidth - w - 6, r.left + r.width / 2 - w / 2));
            tip.style.top = `${top}px`; tip.style.left = `${left}px`;
            tip.style.setProperty('--ow-tip-x', `${r.left + r.width / 2 - left}px`);
            requestAnimationFrame(() => tip.classList.add('is-on'));
        };
        const target = e => e.target.closest?.('button, a[data-tip], a[aria-label], label[title], label[data-tip], summary[data-tip]');
        if (matchMedia('(hover: hover)').matches) {
            document.addEventListener('mouseover', e => { const el = target(e); if (el && el !== on) show(el); });
            document.addEventListener('mouseout', e => { if (on && !on.contains(e.relatedTarget)) hide(); });
        }
        document.addEventListener('focusin', e => { const el = target(e); if (el && el.matches(':focus-visible')) show(el); });
        document.addEventListener('focusout', hide);
        document.addEventListener('click', () => { if (on) hide(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && on) hide(); });
        addEventListener('scroll', () => { if (on) hide(); }, true);
    })();
    /* ---------- Toasts ---------- */
    // window.owlToast(message, kind): a small message that slides in at the top right (top, full width, on phones).
    // kind: 'error' (red), 'success' (green) or 'info' (blue); guessed from the words when left out. It closes by
    // itself (errors stay longer), waits while the pointer is on it, and has a ✕. Screen readers hear it.
    (() => {
        let wrap = null;
        const svg = d => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${d}"/></svg>`;
        const ICON = { error: svg('M12 5.5v8M12 18.2v.1'), success: svg('M5 12.5l4.5 4.5L19 7.5'), info: svg('M12 10.5v8M12 5.8v.1') };
        const guess = m => /^(✓|✅|🎉|🔔)|\b(added|saved|copied|sent|deleted|disconnected|checked|downloaded|published|connected)\b/i.test(m) && !/couldn|can.?t|failed|error/i.test(m) ? 'success'
            : /^please\b|couldn|could not|can.?t|cannot|failed|error|blocked|too many|too big|too large|not allowed|invalid|wrong|missing|needs?\b|up to \d|^the pin|full\b|try again|isn.?t|doesn.?t|didn.?t|no longer/i.test(m) ? 'error' : 'info';
        window.owlToast = (message, kind) => {
            const text = String(message || '').trim(); if (!text) return;
            kind = ICON[kind] ? kind : guess(text);
            if (!wrap || !wrap.isConnected) { wrap = document.createElement('div'); wrap.className = 'ow-toasts'; document.body.append(wrap); }
            // the same message again: just restart its timer
            const same = [...wrap.children].find(t => t.dataset.text === text);
            if (same) { same.restart(); same.classList.remove('is-bump'); void same.offsetWidth; same.classList.add('is-bump'); return; }
            const t = document.createElement('div');
            t.className = `ow-toast ow-toast--${kind}`; t.dataset.text = text;
            t.setAttribute('role', kind === 'error' ? 'alert' : 'status');
            t.innerHTML = `<span class="ow-toast__icon" aria-hidden="true">${ICON[kind]}</span><p></p><button type="button" class="ow-toast__x" aria-label="Close message"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button><i class="ow-toast__bar" aria-hidden="true"></i>`;
            t.querySelector('p').textContent = text;
            const ms = kind === 'error' ? 9000 : 5500;
            let timer, left = ms, started;
            const close = () => { clearTimeout(timer); t.classList.add('is-out'); setTimeout(() => t.remove(), 260); };
            const run = () => { started = Date.now(); clearTimeout(timer); timer = setTimeout(close, left); t.style.setProperty('--ms', `${left}ms`); t.classList.remove('is-paused'); };
            t.restart = () => { left = ms; t.querySelector('.ow-toast__bar').style.animation = 'none'; void t.offsetWidth; t.querySelector('.ow-toast__bar').style.animation = ''; run(); };
            t.addEventListener('mouseenter', () => { clearTimeout(timer); left -= Date.now() - started; t.classList.add('is-paused'); });
            t.addEventListener('mouseleave', run);
            t.querySelector('.ow-toast__x').addEventListener('click', close);
            wrap.append(t);
            while (wrap.children.length > 4) wrap.firstElementChild.remove();
            run();
        };
    })();
    /* ---------- Page messages become toasts ---------- */
    // Tool errors, form alerts, "sent"/"copied" notes and the builders' notes all show as a toast at the top right
    // instead of a line on the page. Watched here so each page's own code stays as it is: when one of these
    // shows (or its words change) its text goes to owlToast and the line hides again. Messages inside a popup
    // (dialog) stay where they are: a toast would be behind the popup.
    (() => {
        const SEL = '.speed-error, .seo-email__msg, [data-copied], .fb-note, .lpb-toast, .alert--err, .alert--ok';
        const kindOf = el => el.matches('.speed-error, .alert--err') ? 'error' : el.matches('.alert--ok') || el.dataset.ok === '1' ? 'success' : undefined;
        const take = el => {
            if (!el.matches?.(SEL) || el.hidden || el.closest('dialog')) return;
            const text = el.textContent.trim(); if (!text) return;
            el.hidden = true;
            window.owlToast?.(text, kindOf(el));
        };
        document.querySelectorAll(SEL).forEach(take);
        new MutationObserver(list => {
            const seen = new Set();
            for (const m of list) {
                const el = m.type === 'characterData' ? m.target.parentElement : m.target;
                const hit = el?.closest?.(SEL); if (hit && !seen.has(hit)) { seen.add(hit); take(hit); }
                m.addedNodes?.forEach(n => { if (n.nodeType === 1) [n, ...n.querySelectorAll(SEL)].forEach(x => { if (!seen.has(x)) { seen.add(x); take(x); } }); });
            }
        }).observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['hidden'] });
    })();
})();
