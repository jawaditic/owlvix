{{-- Guide: how to make a coming soon page and build a waitlist, with the coming soon builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.coming-soon',
    'date' => '2026-11-01',
    'time' => 'PT30M',
    'title' => 'How to Make a Coming Soon Page and Build a Waitlist (Free)',
    'description' => 'Make a coming soon page in 6 steps: a clear promise, a countdown, a waitlist form, social links, publishing it on your domain and turning sign-ups into launch-day customers.',
    'crumb' => 'Coming soon page builder',
    'alt' => 'Owlvix owl making a coming soon page',
    'h1' => 'How to make a coming soon page and build a waitlist',
    'meta' => 'about 30 minutes, free',
    'intro' => '<p>The best time to find your first customers is before you launch. A coming soon page tells people what’s on the way, counts down to the day and collects emails, so you open with a list of people who already want what you’re making. This guide shows you how to make one for free with the <a href="'.e(route('coming-soon-builder')).'">Owlvix coming soon page builder</a>.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the builder in another tab — no sign-up to start.', 'Open the coming soon builder'],
        ['Ready to start your waitlist?', 'Free to build and publish. Sign-ups emailed to you.', 'Make my coming soon page'],
    ],
    'before' => [
        '<strong>One sentence</strong> on what you’re launching and who it’s for.',
        '<strong>A launch date</strong>, or at least a month.',
        '<strong>Your logo</strong> or one strong image.',
        '<strong>An email address</strong> to receive sign-ups.',
    ],
    'steps' => [
        ['Write one clear promise', 'Say what’s coming and why it matters in one line: “Fresh sourdough delivered in Leeds — opening March.” People should know in three seconds whether it’s for them.'],
        ['Pick a design and add your brand', 'Open the free builder, choose a coming soon, under construction or opening soon design, and add your logo, colours and one great image.'],
        ['Set the countdown', 'Click the countdown and set your launch date and time in the Content tab, and the text shown when it ends. If your date might move, choose one you’re sure about.',
            'a date a few weeks away creates excitement; months away and people forget.'],
        ['Switch on the waitlist form', 'Switch on the sign-up form with your email (free). Every sign-up is emailed to you. Tell people what they get for joining — early access, a launch discount or a free sample.',
            'an offer like “10% off on launch day for the waitlist” can get many more sign-ups than “Join our newsletter”.'],
        ['Add social links and publish', 'Add your Instagram, TikTok or other social links, then publish free on sites.owlvix.com — or download the page as index.html and upload it to your own domain while your full site is built.'],
        ['Share it and keep people warm', 'Share the link everywhere you can. Email your list once or twice before launch with a sneak peek, then on launch day with your offer. When you launch, untick “Simple page” in the Site tab to turn it into a full website.'],
    ],
    'mistakes' => [
        ['A vague promise.', '“Something big is coming” gets ignored. Say what it is.'],
        ['No reason to sign up.', 'Give people something for joining your list.'],
        ['Collecting emails and going quiet.', 'Write to your list before launch so they remember you.'],
        ['A countdown that runs out.', 'If the date moves, update it — a timer stuck at zero looks abandoned.'],
    ],
    'more' => '<p>Need the full website or app ready for launch day? <a href="'.e(route('service.mvp-development')).'" data-quote>Talk to us about building it</a>.</p>',
    'faqTitle' => 'Coming soon pages, explained',
    'faq' => [
        ['What is a coming soon page?', 'A one-page site you put up before you launch. It says what’s coming, often with a countdown, and collects emails from people who want to hear when it’s ready.'],
        ['Is the coming soon page builder free?', 'Yes. Build it, publish it free on sites.owlvix.com or download it for your own domain. Sign-ups are free too.'],
        ['Where do the sign-ups go?', 'Every sign-up is emailed to you as it comes in, wherever your page is hosted. You can copy them into Mailchimp, Brevo or any email tool.'],
        ['Can I put it on my own domain?', 'Yes. Download the page as index.html and upload it to your hosting. The waitlist form still works there.'],
        ['Does a coming soon page help with Google?', 'Yes, a little. It lets Google find your domain early, and people searching your brand name can find you before launch.'],
        ['Can I turn it into a full website later?', 'Yes. Untick “Simple page” in the Site tab to show a header and menu, then add pages — it becomes a full website.'],
    ],
]])
