/* Sign-in and saving to a builder account, for builders that don't have their own sign-in dialog (the email
   builder). Same account as every other builder: the email gets a 6-digit code (BuilderController), the token is
   kept in localStorage ("owlvix-builder-token") and sent as X-Builder-Token. Dialogs are the site's sweet dialogs.

     OwlAccount.signedIn()                 → bool
     OwlAccount.api(method, url, body)     → JSON (throws Error with .status)
     OwlAccount.signIn(why)                → Promise<bool>  (asks for the email, then the code)
     OwlAccount.projects                   → { list(kind), get(id), save({id, kind, name, data}), remove(id) } */
(() => {
    const KEY = 'owlvix-builder-token';
    let token = ''; try { token = localStorage.getItem(KEY) || ''; } catch {}
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const freshCsrf = async () => { try { const h = await (await fetch(location.pathname, { headers: { Accept: 'text/html' } })).text(); csrf = (h.match(/name="csrf-token" content="([^"]+)"/) || [])[1] || csrf; } catch {} };
    const api = async (method, url, body, retried) => {
        const res = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token }, body: body ? JSON.stringify(body) : undefined });
        if (res.status === 419 && !retried) { await freshCsrf(); return api(method, url, body, true); } // the page sat open past the session
        const json = await res.json().catch(() => ({}));
        if (res.status === 401) { token = ''; try { localStorage.removeItem(KEY); } catch {} }
        if (!res.ok || json.ok === false) {
            const first = json.errors && Object.values(json.errors)[0];
            const err = new Error(json.error || (Array.isArray(first) ? first[0] : '') || json.message || 'Something went wrong. Please try again.');
            err.status = res.status; throw err;
        }
        return json;
    };
    const say = (msg, kind) => window.owlToast?.(msg, kind);
    /** Email, then code — in two small dialogs. Resolves true once signed in. */
    const signIn = async (why = 'Save your work to your account and open it on any device.') => {
        let email = '';
        for (;;) {
            email = await window.sweet({ icon: 'info', title: 'Sign in with your email', text: `${why} We’ll email you a 6-digit code — no password needed.`, input: { placeholder: 'you@example.com', max: 150, value: email }, confirm: 'Send my code' });
            if (email === null || email === false) return false;
            email = String(email).trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { say('Please type your email address, e.g. you@example.com.', 'error'); continue; }
            try { await api('POST', '/builder/account/code', { email }); break; } catch (ex) { say(ex.message, 'error'); }
        }
        for (;;) {
            const code = await window.sweet({ icon: 'info', title: 'Check your email', text: `We sent a 6-digit code to ${email}. It’s valid for 15 minutes.`, input: { placeholder: '123456', max: 6 }, confirm: 'Sign in' });
            if (code === null || code === false) return false;
            try {
                const r = await api('POST', '/builder/account/verify', { email, code: String(code).trim() });
                token = r.token; try { localStorage.setItem(KEY, token); } catch {}
                say('Signed in as ' + email, 'success'); window.track?.('builder_sign_in');
                return true;
            } catch (ex) { say(ex.message, 'error'); }
        }
    };
    const projects = {
        list: async kind => (await api('GET', '/builder/projects?kind=' + encodeURIComponent(kind))).projects || [],
        get: async id => (await api('GET', '/builder/projects/' + id)).project,
        save: async p => (await api('POST', '/builder/projects', p)).project,
        remove: async id => api('POST', `/builder/projects/${id}/delete`),
    };
    window.OwlAccount = { signedIn: () => !!token, api, signIn, projects };
})();
