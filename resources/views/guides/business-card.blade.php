{{-- Guide: how to design a business card, with the business card maker. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.business-card',
    'date' => '2026-11-02',
    'time' => 'PT15M',
    'title' => 'How to Design a Business Card People Keep — Free Step-by-Step Guide',
    'description' => 'Design a business card in 7 steps: what to put on it, the right size, a clean layout, your logo and colours, a QR code, bleed and safe zones, and printing at a print shop or at home.',
    'crumb' => 'Business card maker',
    'alt' => 'Owlvix owl holding a business card',
    'h1' => 'How to design a business card people keep',
    'meta' => 'about 15 minutes, free',
    'intro' => '<p>A business card is tiny, so every line has to earn its place. A good one is easy to read at arm’s length, looks like your brand and tells people exactly how to reach you. This guide walks you through it step by step. Make yours as you read with the free <a href="'.e(route('business-card-maker')).'">Owlvix business card maker</a> — it gives you a print-ready PDF, and nothing is uploaded.</p>',
    'cta' => [
        ['Want to make it as you read?', 'Open the business card maker in another tab — your details stay on your device.', 'Open the business card maker'],
        ['Ready to print yours?', 'Print-ready PDF with bleed, or 10 per sheet for home printing.', 'Make my business card'],
    ],
    'before' => [
        '<strong>Your name and job title</strong>, written the way you want clients to use them.',
        '<strong>One or two ways to reach you</strong>: usually a mobile number and an email address.',
        '<strong>Your website</strong>, if you have one — it can go in a QR code too.',
        '<strong>Your logo</strong> (optional) as a PNG with a see-through background, at least 600 pixels wide.',
    ],
    'steps' => [
        ['Decide what goes on it', 'Name, job title and business name come first. Then the ways to reach you — phone, email and website are enough for most people. Add an address only if clients visit you. Fewer lines means bigger, easier-to-read text.'],
        ['Pick the right size', 'In the US and Canada a card is 3.5 × 2 inches. In the UK and most of Europe it’s 85 × 55 mm. Using the standard size means your card fits wallets and card holders.'],
        ['Choose a clean layout', 'Make your name the biggest thing on the card, put your title just under it, and group your contact details together. Leave empty space around everything — crowded cards look cheap and get thrown away.'],
        ['Use your logo and one brand colour', 'Put your logo on the front or fill the back with it. Use one strong colour plus black or white text. Check the text is dark on light or light on dark, so it’s readable in dim light.'],
        ['Add a QR code on the back', 'A QR code that opens your website, or one that saves your contact into the phone, turns a card into a quick action. Keep it at least 2 cm (0.8 in) wide.',
            'the Owlvix maker adds the QR code for you — static, so it never expires.'],
        ['Mind the bleed and the safe zone', 'Print shops print on bigger sheets and trim them, so colours that touch the edge need to run 3 mm past it (the bleed). Keep text and logos at least 4 mm inside the edge (the safe zone) so nothing gets cut off.'],
        ['Print it', 'For a professional finish, send the print-ready PDF to an online printer or a local print shop — 350 gsm card with a matt finish is a good choice. To print at home, use 10-per-sheet on thick card and cut along the marks with a trimmer.'],
    ],
    'mistakes' => [
        ['Too much text.', 'Small, crowded text is hard to read. Cut it down to what people actually use.'],
        ['Tiny type.', 'Keep contact details at least 7–8 pt so they can be read without squinting.'],
        ['No bleed.', 'Without it, a thin white line can appear at the edge after trimming.'],
        ['A blurry logo.', 'Use a large logo file so it prints sharp — not a screenshot.'],
    ],
    'faqTitle' => 'Business cards, explained',
    'faq' => [
        ['What size is a standard business card?', '3.5 × 2 inches (88.9 × 50.8 mm) in the US and Canada; 85 × 55 mm in the UK and most of Europe.'],
        ['What is bleed on a business card?', 'Bleed is extra background that runs 3 mm past the card edge. Printers trim it off, so the colour goes right to the edge with no white line.'],
        ['What paper should I print business cards on?', '300–350 gsm card is standard. Matt or silk finishes are easy to write on; gloss makes colours pop.'],
        ['Should I put a QR code on my business card?', 'Yes, if it does something useful — open your website or save your contact. Put it on the back, at least 2 cm wide.'],
        ['Can I print business cards at home?', 'Yes. Print 10 on an A4 or Letter sheet of thick card and cut along the marks. Test on plain paper first.'],
        ['Do I still need business cards?', 'Yes, at events, meetings and in shops a card is still the fastest way to swap details — and with a QR code it links straight to your website.'],
    ],
]])
