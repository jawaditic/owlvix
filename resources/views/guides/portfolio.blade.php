{{-- Guide: how to make a portfolio website, with the portfolio builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.portfolio',
    'date' => '2026-11-01',
    'time' => 'PT2H',
    'title' => 'How to Make a Portfolio Website That Wins Clients (Free) — Step by Step',
    'description' => 'Make a portfolio website in 7 steps: choosing your best work, showing it well, writing about yourself, prices and reviews, a contact form and sharing it with clients.',
    'crumb' => 'Portfolio builder',
    'alt' => 'Owlvix owl making a portfolio website',
    'h1' => 'How to make a portfolio website that wins clients',
    'meta' => 'about 2 hours, free',
    'intro' => '<p>A portfolio does one job: it makes the right client think “I want them”. That comes from showing a few great pieces, saying clearly what you do and making it easy to get in touch. This guide works for photographers, designers, developers, artists and any freelancer, using the free <a href="'.e(route('portfolio-builder')).'">Owlvix portfolio builder</a>.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the portfolio builder in another tab — no sign-up to start.', 'Open the portfolio builder'],
        ['Ready to show your work?', 'Free to build and publish. No watermark on your work.', 'Make my portfolio'],
    ],
    'before' => [
        '<strong>Your 8–15 best pieces</strong> of work, as photos or screenshots.',
        '<strong>A photo of you</strong> — clients like to see who they’ll work with.',
        '<strong>Two or three reviews</strong> from past clients, if you have them.',
        '<strong>Your prices or packages</strong>, even if it’s just “from” prices.',
    ],
    'steps' => [
        ['Choose who it’s for', 'Decide which clients you want more of — weddings, brands, start-ups, restaurants. Choose work and words for them. A portfolio that tries to please everyone convinces no one.'],
        ['Pick your best work', 'Choose 8–15 pieces you’re proud of and that match the work you want. Leave out anything old or off-topic, even if it took ages.',
            'put your strongest piece first and your second-strongest last — people remember the start and the end.'],
        ['Pick a design and add your photos', 'Open the free portfolio builder, choose the photographer, designer, developer or artist design, and click any photo in the gallery to swap in your work. Photos are made smaller in your browser so the page stays fast.'],
        ['Tell the story behind a few pieces', 'For your best two or three projects, add a page or short text: what the client needed, what you did and what changed. Results win more work than pretty pictures alone.'],
        ['Write a short “about me”', 'Who you are, what you do, who you do it for and where you work. Add a friendly photo. Keep it to a few lines — clients want to know you’re real and easy to work with.'],
        ['Add prices, packages and reviews', 'Show packages or “from” prices so the right clients get in touch and the wrong ones don’t. Add reviews from past clients next to them.',
            'a review that mentions a result (“bookings went up after the shoot”) is worth more than “great to work with”.'],
        ['Switch on enquiries and share it', 'Switch on the contact form with your email (free) so every enquiry is emailed to you. Publish, then put the link in your email signature, social profiles and proposals.'],
    ],
    'mistakes' => [
        ['Showing everything.', 'Twelve great pieces beat fifty average ones.'],
        ['No context.', 'A picture with no story leaves clients guessing what you actually did.'],
        ['Hard to contact.', 'Put a “Get in touch” button on every page, not just at the bottom.'],
        ['Slow, huge images.', 'Clients on phones leave before they load. Let the builder shrink them.'],
    ],
    'more' => '<p>Want a custom portfolio with booking, client galleries and SEO? <a href="'.e(route('service.ui-ux-design')).'" data-quote>Talk to us about a portfolio built for you</a>.</p>',
    'faqTitle' => 'Portfolio websites, explained',
    'faq' => [
        ['How many pieces should a portfolio have?', 'Usually 8–15 of your best. Enough to show your range, few enough that every piece is strong.'],
        ['Should I put prices on my portfolio?', 'Showing packages or “from” prices saves time for you and clients. If every job is different, say what affects the price and invite them to ask for a quote.'],
        ['Is the portfolio builder free?', 'Yes. Build your portfolio and publish it free on sites.owlvix.com, or download it for your own domain. No payment and no watermark on your work.'],
        ['Can I use work I did for an employer?', 'Usually yes, if it’s public and you say what your part was. If the work is under a non-disclosure agreement, ask first or describe it without showing it.'],
        ['Do I need my own domain?', 'Not to start. A domain with your name on it looks more professional and is easy to add later by downloading your site and uploading it to your hosting.'],
        ['How often should I update my portfolio?', 'Every few months, or after every project you’re proud of. Swap out older work so it always shows your best.'],
    ],
]])
