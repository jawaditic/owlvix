{{-- Guide: how to start selling online for free, with the online store builder (/online-store-builder).
     Written for small business owners; marked up as an Article with HowTo steps and an FAQ for search engines. --}}
@extends('layouts.page')

@php
    $steps = [
        ['Decide what you’ll sell and what it costs', 'Start with a few products you already sell well. Work out each price so it covers what the product costs you, packing and your time — and decide whether delivery is extra or built into the price.'],
        ['Take clear product photos', 'Use daylight from a window, a plain background and the same angle for every product. Square photos look tidiest in a shop grid. Add a second or third photo for details, the back or the product in use.'],
        ['Build your shop', 'Open the free online store builder, pick a ready shop (clothing, bakery, beauty or handmade) and change the words, photos and prices. Add sizes or colours, sale prices, categories and how many you have in stock.'],
        ['Choose how customers pay', 'Switch on checkout, then choose cash on delivery, bank transfer, card payments through your own Stripe account — or all three. Card money goes straight to your Stripe account.'],
        ['Set delivery, pick-up and tax', 'Add a delivery fee, free delivery over an amount, or different prices by country. Offer pick-up if customers can collect. If you charge tax, add your rate and say whether your prices already include it.'],
        ['Publish your shop and share the link', 'Publish to get your shop’s web address. Put it in your Instagram and TikTok bio, your WhatsApp status and your Google Business profile, and print the QR code for your counter, flyers and parcels.'],
        ['Handle your first orders', 'Every order is emailed to you and listed in the Orders tab. Mark orders as paid and sent — add a tracking number and the customer gets an “on its way” email. Refunds are one click too.'],
        ['Bring customers back', 'Make a discount code for your first customers, sell gift cards, and let buyers review what they bought — stars show on your shop by themselves. The sales view shows what sells best and what’s running low.'],
    ];
    $faq = [
        ['Can I sell online without a website?', 'Yes. A shop made with the free online store builder is your website: it has your products, a cart and checkout. Publish it free on sites.owlvix.com, or download it and put it on your own domain.'],
        ['How much does it cost to sell online?', 'Building and publishing your shop is free. Cash on delivery and bank transfer cost nothing. For card payments, Stripe charges its normal fee on each payment; the money goes straight to your own Stripe account.'],
        ['How do I take card payments?', 'Switch on card payments in the shop’s settings and connect your Stripe account (or open a free one). Customers pay on Stripe’s secure page — you never handle card numbers.'],
        ['Do I need to register a business to sell online?', 'It depends on your country and how much you sell. Many places let you start small as a sole trader; check your local rules for registering, tax and selling to customers in other countries.'],
        ['Can I sell digital products like e-books or courses?', 'Yes. Set a product’s type to “Digital download” and upload the file. Buyers download it from their order page once they’ve paid. You can also sell gift cards.'],
        ['How will customers find my shop?', 'Mostly through you at first: share the link on social media, WhatsApp and your Google Business profile, and print the QR code. Reviews, discount codes for returning customers and good photos help people buy once they arrive.'],
    ];
    $url = route('guide.sell-online');
    $ld = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Article', 'headline' => 'How to start selling online for free', 'url' => $url, 'datePublished' => '2026-11-01', 'dateModified' => '2026-11-01',
            'author' => ['@id' => url('/').'#business'], 'publisher' => ['@id' => url('/').'#business'], 'mainEntityOfPage' => $url],
        ['@type' => 'HowTo', 'name' => 'How to start selling online for free', 'totalTime' => 'PT2H', 'estimatedCost' => ['@type' => 'MonetaryAmount', 'currency' => 'USD', 'value' => '0'],
            'step' => array_map(fn ($s, $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s[0], 'text' => $s[1], 'url' => $url.'#step-'.($i + 1)], $steps, array_keys($steps))],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
    ]];
@endphp

@section('title', 'How to Start Selling Online for Free — A Step-by-Step Guide')
@section('description', 'Start selling online for free in 8 simple steps: pricing, product photos, building your shop, card or cash payments, delivery, sharing your link and handling your first orders.')
@section('canonical', $url)

@section('head')
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="section">
    <div class="container container--narrow prose guide">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('store-builder') }}">Online store builder</a><span aria-hidden="true">›</span><span aria-current="page">How to sell online</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl setting up an online shop'])</div>
        <p class="eyebrow">Guide</p>
        <h1>How to start selling online for free</h1>
        <p class="prose__updated">8 steps · about 2 hours from idea to first order</p>

        <p>You don’t need a big budget, a developer or a monthly plan to start selling online. If you have something to sell and a phone to take photos, you can have a working shop — with a cart, checkout and order emails — this afternoon. This guide walks you through it step by step, using the free <a href="{{ route('store-builder') }}">Owlvix online store builder</a>.</p>

        <div class="guide__cta card">
            <div><b>Want to follow along?</b><span>Open the builder in another tab — no sign-up to start.</span></div>
            <a class="btn btn--glow" href="{{ route('store-builder') }}">Open the store builder</a>
        </div>

        <h2>Before you start</h2>
        <ul>
            <li><strong>A few products</strong> you can describe and photograph — five to ten is plenty to begin.</li>
            <li><strong>Your prices</strong>, including what delivery costs you.</li>
            <li><strong>An email address</strong> to sign in and receive orders.</li>
            <li><strong>Optional:</strong> a free Stripe account if you want card payments.</li>
        </ul>

        <ol class="guide__steps">
            @foreach ($steps as $i => [$title, $text])
                <li id="step-{{ $i + 1 }}">
                    <h2><span class="guide__n">{{ $i + 1 }}</span>{{ $title }}</h2>
                    <p>{{ $text }}</p>
                    @switch($i)
                        @case(0)
                            <p><strong>Tip:</strong> round prices (say 24 instead of 23.70) feel easier, and a “free delivery over 50” offer nudges people to add one more item.</p>
                            @break
                        @case(1)
                            <p><strong>Tip:</strong> shoot a little wider than you need and crop to a square. The builder shrinks photos for you, so your shop stays fast on phones.</p>
                            @break
                        @case(2)
                            <p><strong>Tip:</strong> write one short line on each card (what it is, what makes it good) and put the longer details on the product’s own page — sizes, care, what’s in the box.</p>
                            @break
                        @case(3)
                            <p><strong>Tip:</strong> offering two ways to pay — card and cash on delivery, for example — usually gets more orders than one. Test card payments in Stripe’s test mode before going live.</p>
                            @break
                        @case(5)
                            <p><strong>Tip:</strong> send the link to ten friends or regular customers first and ask for honest feedback. Fix anything confusing before you share it widely.</p>
                            @break
                        @case(6)
                            <p><strong>Tip:</strong> reply to every order the same day, even just to say thank you. Fast, friendly replies are why small shops win against big ones.</p>
                            @break
                    @endswitch
                </li>
            @endforeach
        </ol>

        <h2>Common mistakes to avoid</h2>
        <ul>
            <li><strong>Too many products at once.</strong> Start with your best sellers; add more when orders come in.</li>
            <li><strong>Hidden costs at checkout.</strong> Show your delivery price early (in your announcement or FAQ) so nobody is surprised.</li>
            <li><strong>Dark or busy photos.</strong> One clear photo beats five unclear ones.</li>
            <li><strong>No way to ask a question.</strong> Add your WhatsApp number or email so customers can ask before they buy.</li>
        </ul>

        <div class="guide__cta card">
            <div><b>Ready to open your shop?</b><span>Free to build and publish. Card, cash or bank transfer.</span></div>
            <a class="btn btn--glow" href="{{ route('store-builder') }}">Start my free shop</a>
        </div>
        <p>Want a bigger store built for you — with your own domain, stock syncing or a mobile app? <a href="{{ route('service.shopify-development') }}" data-quote>Talk to us about a custom online store</a>.</p>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Selling online, explained'])
@endsection
