{{-- Guide: how to make a QR code, with the QR code generator. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.qr',
    'date' => '2026-11-02',
    'time' => 'PT10M',
    'title' => 'How to Make a QR Code People Actually Scan — Free Step-by-Step Guide',
    'description' => 'Make a QR code in 7 steps: pick what it opens, keep it short, add your logo and colours safely, choose the right print size and test it before printing. Free, and it never expires.',
    'crumb' => 'QR code generator',
    'alt' => 'Owlvix owl holding a QR code',
    'h1' => 'How to make a QR code that people actually scan',
    'meta' => 'about 10 minutes, free',
    'intro' => '<p>A QR code is a quick way to take someone from paper to your website, menu, Wi-Fi or contact details. Making one takes a minute — making one that scans every time, on every phone, takes a few simple rules. This guide covers them all. Make yours as you read with the free <a href="'.e(route('qr-code-generator')).'">Owlvix QR code generator</a>: no sign-up, and the codes never expire.</p>',
    'cta' => [
        ['Want to make it as you read?', 'Open the QR code generator in another tab — nothing is uploaded.', 'Open the QR code generator'],
        ['Ready to make yours?', 'Free PNG and SVG. No sign-up, no watermark, no expiry.', 'Make my QR code'],
    ],
    'before' => [
        '<strong>What it should open</strong>: a web address, your Wi-Fi name and password, your contact details or a phone number.',
        '<strong>Your logo</strong> (optional) as a PNG or JPG — a square one works best.',
        '<strong>Your brand colour</strong>, if you want one. Dark colours work best.',
        '<strong>Where it will be printed</strong> — a business card, a table tent, a poster — so you know how big to make it.',
    ],
    'steps' => [
        ['Decide what the code opens', 'A web page is the most common, but a QR code can also join your Wi-Fi, save your contact card, start a WhatsApp chat, call you, write an email or open a map. Pick the one that saves people the most typing.'],
        ['Keep what’s inside it short', 'The more you put in a code, the smaller and denser its squares get — and the harder it is to scan. Use a short web address, and for long information (a full menu, a price list) put it on a web page and make the code for that page’s link.',
            'a link to your Owlvix link-in-bio page or digital menu keeps the code simple, and you can change the page any time.'],
        ['Choose static or dynamic', 'A static code holds your link or details inside the code itself: free, and it works forever. A dynamic code sends scans through another company’s server so the address can change later — but it usually stops working when you stop paying. If you might change the address, point a static code at a page you own.'],
        ['Pick colours with strong contrast', 'Phones read dark squares on a light background. Use black, navy or a dark brand colour on white or cream. Avoid light codes on dark backgrounds, low-contrast pairs like yellow on white, and placing the code over a busy photo.'],
        ['Add your logo — carefully', 'A logo in the middle makes people trust the code. It works because QR codes have error correction: up to 30% of a code can be covered and it still scans. Use High error correction, keep the logo small or medium, and leave the three corner squares alone.'],
        ['Add a label that says what happens', 'A short line like “Scan for our menu” or “Scan to join our Wi-Fi” tells people why to scan, and they scan far more often. Put it right under the code.'],
        ['Print it big enough and test it', 'Make it at least 2 cm (0.8 in) wide on a card, and about a tenth of the scanning distance on posters — 30 cm wide to scan from 3 metres. Download SVG for print so it stays sharp. Then test with an iPhone and an Android phone before printing hundreds.'],
    ],
    'mistakes' => [
        ['Too much inside the code.', 'Long text makes tiny squares. Link to a page instead.'],
        ['Light code on a dark background.', 'Many phone cameras can’t read inverted codes. Keep the code darker than its background.'],
        ['Too small, or no white border.', 'Leave the quiet zone — the empty margin around the code — and print it big enough.'],
        ['Not testing before printing.', 'Scan it with two different phones, from the real distance, before you order prints.'],
    ],
    'faqTitle' => 'QR codes, explained',
    'faq' => [
        ['Do QR codes expire?', 'Static QR codes never expire: the information is stored in the code itself. Dynamic codes from paid services can stop working when the subscription ends, because scans go through their server.'],
        ['Can I change a QR code after printing it?', 'Not a static one. If you might change the address, make the code point at a page you control and update that page instead.'],
        ['How small can a QR code be?', 'About 2 cm (0.8 in) wide is the practical minimum for a short link scanned up close. Codes with more information inside need to be bigger.'],
        ['Will a logo stop my QR code working?', 'Not if it’s small and the code uses High error correction, which lets about 30% of the code be covered. Always test before printing.'],
        ['Do I need an app to scan a QR code?', 'No. The camera on iPhone and Android phones scans QR codes on its own — point it at the code and tap the link that appears.'],
        ['What’s the best file type for printing?', 'SVG. It’s a vector file, so it stays perfectly sharp at any size. Use PNG for documents, slides and social media.'],
    ],
]])
