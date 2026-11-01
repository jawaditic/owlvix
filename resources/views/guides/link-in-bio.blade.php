{{-- Guide: how to make a link in bio page, with the link in bio builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.link-in-bio',
    'date' => '2026-11-01',
    'time' => 'PT30M',
    'title' => 'How to Make a Link in Bio Page for Instagram & TikTok (Free)',
    'description' => 'Make a free link in bio page in 6 steps: choose your links, add your photo and bio, style the buttons, publish it and add it to your Instagram, TikTok and YouTube profiles.',
    'crumb' => 'Link in bio builder',
    'alt' => 'Owlvix owl making a link in bio page',
    'h1' => 'How to make a link in bio page',
    'meta' => 'about 30 minutes, free',
    'intro' => '<p>Instagram and TikTok give you one link on your profile. A link in bio page turns that one link into many — your shop, latest video, booking page, WhatsApp and more — on a simple page that’s yours. This guide shows you how to make one for free with the <a href="'.e(route('link-in-bio-builder')).'">Owlvix link in bio builder</a>, with your own colours and no ads.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the builder in another tab — no sign-up to start.', 'Open the link in bio builder'],
        ['Ready to make yours?', 'Free to build and publish. Your design, no ads.', 'Make my link page'],
    ],
    'before' => [
        '<strong>A clear photo or logo</strong> — the same one as your profile picture works best.',
        '<strong>The links you want to share</strong>: shop, videos, booking, newsletter, WhatsApp.',
        '<strong>A one-line bio</strong> that says what you do.',
    ],
    'steps' => [
        ['Choose your 3–6 most important links', 'Pick the links you most want people to tap: your shop or booking page, your newest video, your WhatsApp or email. Fewer buttons get more taps.',
            'put the link you care about most at the top — most people only tap the first or second button.'],
        ['Add your photo and bio', 'Open the free builder, add your photo or logo and a short bio. Say who you are and what people get: “Cakes for every party · Order by WhatsApp”.'],
        ['Add your buttons', 'Click a button and in the Content tab choose where it goes — a web address, WhatsApp, a phone call or email. Give each button a short, clear label like “Shop new arrivals” instead of just “Shop”.',
            'buttons without a link are left off the published page, so you can keep spares ready.'],
        ['Add your social icons', 'Add small icons for Instagram, TikTok, YouTube and the rest, so people can follow you everywhere.'],
        ['Make it look like you', 'Use the Site tab to set your colours and fonts, or style any button on its own. Match your brand or your feed so the page feels familiar.'],
        ['Publish and add it to your profiles', 'Press Publish, copy your page’s web address and paste it into the website field on Instagram, TikTok, YouTube and anywhere else. You can also print its QR code for your shop counter or business cards.',
            'when you post something new, update the top button and publish again — the link in your bio stays the same.'],
    ],
    'mistakes' => [
        ['Too many links.', 'Twenty buttons means nobody taps any. Keep it to the few that matter now.'],
        ['Vague button labels.', '“Click here” tells people nothing. Say what they get.'],
        ['Dead links.', 'Check every button after you publish, and remove old offers.'],
        ['Hard-to-read colours.', 'Make sure button text stands out against its colour on a small screen.'],
    ],
    'more' => '<p>Ready for a full website when one page isn’t enough? Try the free <a href="'.e(route('website-builder')).'">website builder</a> or <a href="'.e(route('service.landing-page-design')).'" data-quote>talk to us about a custom site</a>.</p>',
    'faqTitle' => 'Link in bio pages, explained',
    'faq' => [
        ['What is a link in bio page?', 'It’s a simple page with all your important links. You put its address in your Instagram, TikTok or YouTube profile, so your one profile link can lead anywhere.'],
        ['Is it free?', 'Yes. Build it and publish it free on sites.owlvix.com, or download it and put it on your own domain. No payment and no ads on your page.'],
        ['How do I add the link to Instagram?', 'Go to your profile, tap “Edit profile”, then “Links” and “Add external link”, and paste your page’s web address.'],
        ['How do I add the link to TikTok?', 'Tap “Edit profile” and paste your address into the “Website” field. TikTok may only show the website field on some accounts, such as business accounts.'],
        ['Can I add a WhatsApp button?', 'Yes. Set a button to WhatsApp and enter your number — tapping it opens a chat with you.'],
        ['Can I change my links later?', 'Yes. Change any button and publish again. Your page address stays the same, so you don’t need to update your profiles.'],
    ],
]])
