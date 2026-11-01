{{-- Guide: how to make a QR code menu, with the restaurant menu builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.qr-menu',
    'date' => '2026-11-01',
    'time' => 'PT1H',
    'title' => 'How to Make a QR Code Menu for Your Restaurant (Free) — Step by Step',
    'description' => 'Make a QR code menu for your restaurant, café or takeaway in 7 steps: write the menu, add prices and photos, publish it, print QR table cards and update prices without reprinting.',
    'crumb' => 'Restaurant menu & QR builder',
    'alt' => 'Owlvix owl making a QR code menu',
    'h1' => 'How to make a QR code menu for your restaurant',
    'meta' => 'about 1 hour, free',
    'intro' => '<p>A QR code menu lets guests scan a code on the table and read your menu on their own phone. You can change prices any time without reprinting, add today’s specials in a minute, and let people order by WhatsApp. This guide shows you how to make one for free with the <a href="'.e(route('menu-builder')).'">Owlvix restaurant menu builder</a> — no designer needed.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the menu builder in another tab — no sign-up to start.', 'Open the menu builder'],
        ['Ready to make your menu?', 'Free to build, publish and print. No watermark.', 'Make my QR menu'],
    ],
    'before' => [
        '<strong>Your current menu</strong> — a printed copy or a photo of it is fine.',
        '<strong>Your prices</strong>, and which dishes are vegetarian, vegan or spicy.',
        '<strong>Your opening hours, address</strong> and phone or WhatsApp number.',
        '<strong>Optional:</strong> a few good photos of your best dishes and your place.',
    ],
    'steps' => [
        ['Pick a menu design', 'Open the free menu builder and choose the one closest to you — restaurant, café, pizza takeaway, food truck, sushi, curry house, burger joint or bar. You can change every colour, font and photo later, so pick by layout, not colour.',
            'the quick setup asks for your name, type of place and colours, and fills the menu in for you to edit.'],
        ['Write your sections and dishes', 'Add sections the way guests think: starters, mains, pizzas, drinks, desserts. Under each, add the dish name, a short line about it and the price.',
            'keep descriptions to one line — what’s in it and how it’s cooked. “Slow-cooked lamb, rosemary, garlic mash” sells better than a paragraph.'],
        ['Mark diet labels and allergens', 'Add “V”, “VG”, “GF” or a chilli next to dishes, and a short line at the bottom about allergens — for example “Ask our staff about allergens”. Many countries require you to give allergen information on request.'],
        ['Add photos, specials, hours and map', 'Put a photo of your place at the top and a few of your best dishes. Add a “Today’s specials” box, your opening hours and a map so people walking past can find you.',
            'one great photo per section is enough. Too many photos make the menu slow on mobile data.'],
        ['Let guests order or call', 'Set the buttons to open WhatsApp with your number, call you, or open your own ordering or delivery link. Guests at home can order straight from the menu.'],
        ['Publish and print your QR code', 'Press Publish. Your QR code appears straight away — download it as PNG or SVG, or print ready-made table cards, four to an A4 page. Test it with two or three phones before printing many.',
            'print the code at least 3 cm wide, with a line like “Scan for our menu” under it, and keep a few paper menus for guests who prefer them.'],
        ['Change prices without reprinting', 'The QR code points to your menu’s web address, which stays the same. Change a price or a special, publish again, and every printed code shows the new menu straight away.'],
    ],
    'mistakes' => [
        ['A PDF of your paper menu.', 'It’s hard to read and zoom on a phone. A real menu page fits every screen.'],
        ['QR codes too small or glossy.', 'Small or shiny codes are hard to scan in a dim room — print them big and matt.'],
        ['Out-of-date prices.', 'Guests trust the menu on their phone. Update it the same day prices change.'],
        ['No way to order.', 'Add a WhatsApp or call button so a look turns into an order.'],
    ],
    'more' => '<p>Want online ordering with payments, delivery zones and a kitchen screen? <a href="'.e(route('service.web-app-development')).'" data-quote>Talk to us about a restaurant ordering app</a>.</p>',
    'faqTitle' => 'QR code menus, explained',
    'faq' => [
        ['Is a QR code menu free to make?', 'Yes. With the Owlvix menu builder you can build your menu, publish it on sites.owlvix.com and print as many QR codes and table cards as you like, free. There’s no watermark on your menu.'],
        ['Do QR codes expire?', 'The code itself never expires — it’s just your menu’s web address in a pattern. As long as your menu stays published at that address, the code keeps working.'],
        ['Can guests without a smartphone still see the menu?', 'Keep a few printed menus for them. You can print your published menu page straight from the browser.'],
        ['How do I add my menu to Google Maps?', 'In your Google Business profile, add your menu link under “Menu”. Use the same web address as your QR code so both always show the latest menu.'],
        ['Can I have the menu in two languages?', 'Yes. Add a second page in the other language and link to it with a button at the top, or write each dish name in both languages.'],
        ['How big should the QR code be?', 'At least 3 cm (about 1.2 inches) wide for a table card read from 30–50 cm away, and bigger for posters or windows read from further away.'],
    ],
]])
