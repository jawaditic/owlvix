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

    const { createOwl } = window.Owl;

    /* Hero: owl teacher at the chalkboard. Plays when the visitor presses the board's button. */
    const heroRoot = document.getElementById('owl-intro');
    const heroText = heroRoot.querySelector('[data-owl-text]').textContent;
    createOwl(heroRoot, {
        lines: cfg.intro,
        idleText: heroText,
        doneText: cfg.intro[cfg.intro.length - 1].text,
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
