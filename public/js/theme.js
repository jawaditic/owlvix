/* Sun/moon switch: flips <html data-theme> between dark and light and remembers it.
   Every [data-theme-toggle] button on the page works; the initial theme is set in <head>. */
(() => {
    const root = document.documentElement;
    const sync = () => {
        const light = root.dataset.theme === 'light';
        document.querySelectorAll('[data-theme-toggle]').forEach(b => {
            const next = light ? 'dark' : 'light';
            b.setAttribute('aria-label', `Switch to ${next} mode`);
            b.title = `Switch to ${next} mode`;
            const label = b.querySelector('[data-theme-label]');
            if (label) label.textContent = light ? 'Dark mode' : 'Light mode';
        });
        // Charts and anything else drawn by script can redraw in the new colours
        window.dispatchEvent(new CustomEvent('themechange', { detail: root.dataset.theme }));
    };
    document.querySelectorAll('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'light' ? 'dark' : 'light';
        try { localStorage.setItem('owlvix-theme', root.dataset.theme); } catch (e) {}
        sync();
    }));
    // Follow the device setting live, until the visitor picks a theme themselves
    matchMedia('(prefers-color-scheme: light)').addEventListener('change', e => {
        let saved; try { saved = localStorage.getItem('owlvix-theme'); } catch (err) {}
        if (saved) return;
        root.dataset.theme = e.matches ? 'light' : 'dark'; sync();
    });
    sync();
})();
