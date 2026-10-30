{{-- Free email & DNS checker (App\Support\EmailCheck): SPF, DKIM, DMARC, MX and name servers. DNS lookups only. --}}
@extends('layouts.page')

@section('title', 'Email & DNS Checker — SPF, DKIM, DMARC Test')
@section('description', 'Why are your emails going to spam? Check any domain\'s SPF, DKIM, DMARC and MX records and get the exact fix. Free, instant, no sign-up.')
@section('canonical', route('email-check'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['Why do my emails go to spam?', 'Gmail, Outlook and Yahoo check three DNS records to decide if an email really comes from you: SPF (which servers may send), DKIM (a digital signature) and DMARC (what to do with fakes). If one is missing or wrong, your emails look suspicious.'],
        ['What are SPF, DKIM and DMARC?', 'SPF lists the servers allowed to send email for your domain. DKIM adds a signature that proves the email was not changed. DMARC tells inboxes what to do when SPF or DKIM fail — and sends you reports about who uses your domain.'],
        ['Why does it say DKIM was not found?', 'DKIM keys live under a "selector" name that your provider chooses, and it is not public. We check the names used by Google, Microsoft 365, Zoho, cPanel and others. If your provider uses a different one, DKIM may still be on — check in its admin panel.'],
        ['Does this tool send any email?', 'No. It only reads public DNS records — the same ones every mail server reads.'],
        ['Can it tell me if an email address exists?', 'No. It checks the domain\'s email setup (everything after the @). Most mail servers — Gmail, Microsoft 365 and many hosts — accept any address when asked, so checking a single mailbox is unreliable, and probing mail servers can get the checker blacklisted.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Email & DNS Checker', 'url' => route('email-check'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Email & DNS checker', 'h1' => 'Why are my emails <span class="accent">going to spam?</span>', 'lead' => 'Check your domain\'s SPF, DKIM, DMARC and mail records — and get the exact record to add for every issue.', 'action' => route('email-check.run'), 'icon' => 'mail', 'button' => 'Check my email setup', 'placeholder' => 'example.com or you@example.com', 'label' => 'Domain or email address'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card">
            <div class="speed-gauge" data-rating="good">
                <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="speed-gauge__track"/><circle cx="60" cy="60" r="52" class="speed-gauge__bar" pathLength="100"/></svg>
                <strong data-t="score">–</strong>
                <span>Email</span>
            </div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-t="headline">–</h2>
                <p data-t="summary"></p>
                <p class="speed-summary__meta"><span data-t="domain"></span></p>
                <p class="email-note" data-t="note" hidden></p>
                <div class="seo-tally">
                    <span class="seo-tally__i" data-k="fail"><b data-t="fail">0</b> to fix first</span>
                    <span class="seo-tally__i" data-k="warn"><b data-t="warn">0</b> to improve</span>
                    <span class="seo-tally__i" data-k="pass"><b data-t="pass">0</b> passed</span>
                </div>
            </div>
        </div>
        <div data-t="checks"></div>
        <h3 class="speed-h">Your DNS records</h3>
        <div class="dns-records card" data-t="records"></div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl fixing email settings'])</div>
            <div>
                <h2>Want us to fix your email setup?</h2>
                <p>We set up SPF, DKIM and DMARC correctly for Google Workspace, Microsoft 365, Zoho or your hosting — so your emails reach the inbox.</p>
            </div>
            <a href="{{ route('service.hosting-and-support') }}#contact" class="btn btn--glow">Get it fixed
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Email deliverability, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`), esc = OwlTool.esc;
    const heads = { good: 'Your email is set up well', average: 'Some email protection is missing', poor: 'Your emails are likely landing in spam' };
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Looking up your mail servers', 'Reading SPF and DMARC records', 'Searching for DKIM keys'],
        event: 'email_check',
        render: r => {
            const g = document.querySelector('.speed-gauge'); g.dataset.rating = r.rating; g.style.setProperty('--score', r.score);
            T('score').textContent = r.score; T('headline').textContent = heads[r.rating]; T('summary').textContent = r.summary;
            T('domain').textContent = r.domain + (r.provider ? ` · Email by ${r.provider}` : '');
            // An email address was typed: be clear that the domain's setup was checked, not the mailbox
            const typed = document.querySelector('[data-tool-form] [name=url]').value.trim();
            T('note').hidden = !typed.includes('@');
            T('note').textContent = `We checked the email setup of ${r.domain} — the domain, not the mailbox. Mail servers don't reliably say whether ${typed} itself exists, so no tool can confirm that safely.`;
            ['fail', 'warn', 'pass'].forEach(k => T(k).textContent = r.counts[k] || 0);
            T('checks').innerHTML = OwlTool.checks(r.checks);
            T('records').innerHTML = Object.entries(r.records).map(([k, v]) => `
                <div class="dns-row"><b>${k}</b><div>${v.length ? v.map(x => `<code>${esc(x)}</code>`).join('') : '<span class="dns-none">Not found</span>'}</div></div>`).join('')
                + (r.suggest_spf ? `<div class="dns-row dns-row--fix"><b>Add</b><div><code>${esc(r.suggest_spf)}</code><small>Suggested SPF record (TXT on ${esc(r.domain)})</small></div></div>` : '');
        },
    });
})();
</script>
@endsection
