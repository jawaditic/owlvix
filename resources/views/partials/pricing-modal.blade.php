{{-- "How we charge" popup: opened by any [data-pricing] link (service pages, menu "Pricing" on pages without a
     pricing section). The link's href still works without JavaScript. --}}
<dialog class="quote-modal pricing-modal" id="pricing-modal" aria-labelledby="pricing-modal-title">
    <button type="button" class="quote-modal__close" aria-label="Close" data-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div class="pricing-modal__body">
        <p class="eyebrow">How we charge</p>
        <h2 id="pricing-modal-title" tabindex="-1">Start free. Pay as you approve.</h2>
        <p class="pricing-modal__lead">No deposit and no upfront payment. We plan your project and design the key screens for free, then you pay milestone by milestone — only for work you have seen and approved.</p>
        <ol class="pay-steps__list pricing-modal__steps">
            <li>
                <span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('rocket') !!}</span>
                <strong>Free start</strong>
                <span class="pay-steps__amount">$0</span>
                <p>We plan your project and design the key screens. No payment, no obligation.</p>
            </li>
            <li>
                <span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('clipboard') !!}</span>
                <strong>Agree the milestones</strong>
                <span class="pay-steps__amount">fixed total</span>
                <p>The work is split into as many milestones as we agree, each with its own price.</p>
            </li>
            <li>
                <span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('check') !!}</span>
                <strong>Pay as you approve</strong>
                <span class="pay-steps__amount">per milestone</span>
                <p>See each milestone working on a preview link, approve it, then pay for it.</p>
            </li>
            <li>
                <span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('key') !!}</span>
                <strong>Launch &amp; handover</strong>
                <span class="pay-steps__amount">final milestone</span>
                <p>With the final payment you get the live product, full source code and all accounts.</p>
            </li>
        </ol>
        <div class="pricing-modal__cta">
            <a href="#contact" class="btn btn--glow" data-pricing-quote>Get a free quote
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
            <button type="button" class="btn btn--ghost" data-close>Close</button>
        </div>
    </div>
</dialog>
