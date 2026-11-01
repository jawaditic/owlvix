{{-- Guide: how to make a wedding website with RSVPs, with the event website builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.wedding-website',
    'date' => '2026-11-01',
    'time' => 'PT1H',
    'title' => 'How to Make a Wedding Website with RSVPs (Free) — Step by Step',
    'description' => 'Make a free wedding website in 7 steps: what to put on it, the day’s schedule, venue and travel, an RSVP form with meal choices, sharing the link and keeping your guest list.',
    'crumb' => 'Wedding & event website builder',
    'alt' => 'Owlvix owl making a wedding website',
    'h1' => 'How to make a wedding website with RSVPs',
    'meta' => 'about 1 hour, free',
    'intro' => '<p>A wedding website gives guests one place for everything — the date, the plan for the day, how to get there, where to stay — and lets them RSVP in a minute. You get a tidy guest list instead of chasing replies by text. This guide shows you how to make one for free with the <a href="'.e(route('event-website-builder')).'">Owlvix wedding & event website builder</a>. It works just as well for birthdays, baby showers and conferences.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the builder in another tab — no sign-up to start.', 'Open the wedding builder'],
        ['Ready to make yours?', 'Free to build and publish. RSVPs emailed to you.', 'Make my wedding website'],
    ],
    'before' => [
        '<strong>Your date, times and venue</strong> — ceremony and reception if they’re in different places.',
        '<strong>Two or three photos</strong> of you both.',
        '<strong>Your RSVP deadline</strong> and whether you’re offering meal choices or plus-ones.',
        '<strong>An email address</strong> to receive replies.',
    ],
    'steps' => [
        ['Pick a design', 'Open the free builder and choose the wedding design (there are also birthday, conference and baby shower ones). Change the colours and fonts to match your invitations.',
            'use the same two colours as your printed invitation — guests will recognise it straight away.'],
        ['Add your names, date and countdown', 'Put your names and date at the top with a favourite photo. Set the countdown to the start of the ceremony — guests love watching it tick down.'],
        ['Write the plan for the day', 'Add a simple timeline: ceremony, drinks, dinner, first dance, carriages. Add the dress code and whether children are invited, so nobody has to ask.',
            'say what time to arrive, not just when it starts — “please be seated by 1:45” saves a late entrance.'],
        ['Add the venue, travel and where to stay', 'Add the venue address with a map, parking or public transport tips, and a few nearby hotels. If guests travel from abroad, add the nearest airport.'],
        ['Switch on RSVPs', 'Select the RSVP form and press “Switch on RSVPs” (free, sign in with your email). Ask how many are coming, offer meal choices and leave room for a message or dietary needs.',
            'put the RSVP deadline in big letters next to the form — at least four weeks before the day, so you can confirm numbers with the venue.'],
        ['Publish and share the link', 'Press Publish to get your web address. Send it by WhatsApp or email, and print the QR code on your invitations or save-the-date cards so guests can scan and reply.'],
        ['Keep your guest list up to date', 'Every reply is emailed to you and saved in a list. Download your guest list as a CSV file for your seating plan and caterer. Change anything on the page and publish again — the link stays the same.'],
    ],
    'mistakes' => [
        ['No RSVP deadline.', 'Without a date, replies trickle in for months. Set one and remind people a week before.'],
        ['Too much on one page.', 'Guests mostly want the date, place, plan and RSVP. Put the love story lower down.'],
        ['Forgetting older guests.', 'Big text, a clear map and a phone number to call help everyone.'],
        ['Changing the link.', 'Publish updates to the same page, so the QR code on your invitations keeps working.'],
    ],
    'more' => '<p>Planning something bigger — ticket sales, seating plans or a photo gallery for guests? <a href="'.e(route('service.landing-page-design')).'" data-quote>Talk to us about a custom event site</a>.</p>',
    'faqTitle' => 'Wedding websites, explained',
    'faq' => [
        ['Is the wedding website free?', 'Yes. Build it and publish it free on sites.owlvix.com, or download it and put it on your own domain. There’s no watermark on your content and RSVPs are free.'],
        ['What should I put on my wedding website?', 'Your names and date, the plan for the day, the venue with a map, dress code, where to stay, gift or registry details, and an RSVP form with your deadline.'],
        ['When should I send the wedding website?', 'Many couples share it with the save-the-dates, 6–12 months before, and add the RSVP form when invitations go out, about 2–3 months before.'],
        ['How do guests RSVP?', 'They open your page, fill in the RSVP form and press send. Each reply is emailed to you and saved in a list you can download as a CSV file.'],
        ['Can guests choose their meal?', 'Yes. Add meal choices to the RSVP form, and ask about allergies or dietary needs in the message box.'],
        ['Can I keep it private?', 'Share the link only with your guests, and leave out your home address, gift details you’d rather keep private and anything you wouldn’t want strangers to see. Treat the page as something anyone with the link can read.'],
    ],
]])
