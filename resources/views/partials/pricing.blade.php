{{-- Pricing: one offer — start free, pay per milestone. Shared by the homepage and /services. --}}
<section class="section" id="pricing">
    <div class="container">
        <p class="eyebrow">Pricing</p>
        <h2>Start free. Pay as you approve.</h2>
        <p class="pricing__lead">No deposit and no upfront payment. We plan your project and design the key screens for free, then you pay milestone by milestone — only for work you have seen and approved.</p>
        <div class="pricing pricing--single">
            <article class="card plan">
                <span class="badge badge--blue">Start free</span>
                <h3>Milestone project</h3>
                <p class="price"><strong>$0</strong><span> to start</span></p>
                <p>Best when you know what you want built — a new app, a website or an AI feature. We start for free, then you pay milestone by milestone.</p>
                <ul class="checks">
                    <li>Free start: project plan + key screen designs</li>
                    <li>No upfront payment or deposit</li>
                    <li>Pay per milestone, only after you approve it</li>
                    <li>Fixed total price agreed up front</li>
                    <li>Full source code handed over on final payment</li>
                </ul>
                <a href="#contact" class="btn btn--ghost btn--block" data-service="Milestone project">Start for free</a>
            </article>

        {{-- How payment works on a milestone project --}}
        <div class="pay-steps">
            <h3 class="pay-steps__title">How payment works</h3>
            <ol class="pay-steps__list">
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
        </div>
        </div>
    </div>
</section>
