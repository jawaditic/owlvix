{{-- Guide: how to take bookings online, with the booking page builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.booking',
    'date' => '2026-11-02',
    'time' => 'PT15M',
    'title' => 'How to Take Bookings Online — Free Appointment Booking Page Guide',
    'description' => 'Stop taking bookings by phone: set up your services, opening hours and booking rules, share one link, and let clients book themselves. A free step-by-step guide for salons, clinics, tutors and consultants.',
    'crumb' => 'Booking page builder',
    'alt' => 'Owlvix owl with a calendar',
    'h1' => 'How to take bookings online',
    'meta' => 'about 15 minutes, free',
    'intro' => '<p>Answering the phone to fix appointment times eats your day — and clients who can’t reach you book somewhere else. An online booking page lets them pick a free time themselves, day or night, and sends you both an email. This guide walks you through setting one up. Make yours as you read with the free <a href="'.e(route('booking-page-builder')).'">Owlvix booking page builder</a>.</p>',
    'cta' => [
        ['Want to build it as you read?', 'Open the booking page builder in another tab.', 'Open the booking page builder'],
        ['Ready to take bookings?', 'Free booking page, email confirmations and reminders.', 'Make my booking page'],
    ],
    'before' => [
        '<strong>Your services</strong>: what people can book, how long each takes and the price.',
        '<strong>Your opening hours</strong>, including breaks and days you’re closed.',
        '<strong>Your rules</strong>: how much notice you need and how far ahead people can book.',
        '<strong>Where you meet</strong>: your address, or “online / video call”.',
    ],
    'steps' => [
        ['List what people can book', 'Add each service with its length and price — for example “Haircut, 45 minutes, £35”. The length decides which times are free, so be realistic, and keep names short and clear.'],
        ['Set your opening hours', 'Pick the days you work and your hours. Add a lunch break as two ranges (9:00–12:00 and 13:00–17:00) so nobody books you while you eat.'],
        ['Add rules that protect your time', 'Choose how much notice you need (a few hours stops surprise bookings), how far ahead people can book, and a short gap after each appointment to tidy up or travel.',
            'a 10–15 minute gap after each booking keeps you from running late all day.'],
        ['Block your days off', 'Add holidays and days you’re away, so those dates simply show no free times.'],
        ['Write a short welcome and what to expect', 'One or two lines at the top of the page, and a message clients see after booking — like “Please arrive 5 minutes early” or the video-call link.'],
        ['Publish and share your link', 'Put your booking link in your Instagram bio, Google Business Profile, email signature and WhatsApp replies. Add it to your website with a “Book now” button, or embed the booking page on a page of your site.'],
        ['Keep an eye on your bookings', 'Each booking arrives by email, and clients get a confirmation with a link to cancel — plus a reminder the day before, which cuts no-shows.'],
    ],
    'mistakes' => [
        ['Too many choices.', 'Five clear services beat twenty. Group similar ones.'],
        ['No notice time.', 'Without one, someone can book a slot that starts in ten minutes.'],
        ['Forgetting breaks and holidays.', 'Block them first so you’re never double-booked with your own lunch.'],
        ['Hiding the link.', 'If clients can’t find it, they’ll still phone. Put it everywhere you’re seen.'],
    ],
    'faqTitle' => 'Online bookings, explained',
    'faq' => [
        ['Is an online booking page worth it for a small business?', 'Yes. Clients can book at any time — many book in the evening — and you stop spending your day on calls and messages arranging times.'],
        ['How do I stop no-shows?', 'Send a reminder the day before, ask for a phone number, and make cancelling easy so people free the time instead of not turning up.'],
        ['Can clients cancel their booking?', 'With Owlvix, yes: the confirmation email has a link to see or cancel the booking, and you get an email when they do.'],
        ['Can I put the booking page on my website?', 'Yes. Link to it from a “Book now” button, or paste the embed code to show it inside a page of your site.'],
        ['What if I work in a different time zone from my clients?', 'Times are shown in your time zone, and the page says which zone that is, so nobody turns up an hour early.'],
        ['Do I need to take payment when people book?', 'Not to start. Many businesses take payment at the appointment. You can show prices on the booking page so there are no surprises.'],
    ],
]])
