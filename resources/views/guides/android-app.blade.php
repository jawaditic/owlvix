{{-- Guide: how to make an Android app without coding, with the mobile app builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.android-app',
    'date' => '2026-11-01',
    'time' => 'PT3H',
    'title' => 'How to Make an Android App Without Coding (Free) — Step by Step',
    'description' => 'Make a native Android app without coding in 7 steps: planning your screens, building them from blocks, your icon and colours, testing the APK on your phone and publishing on Google Play.',
    'crumb' => 'Mobile app builder',
    'alt' => 'Owlvix owl building a mobile app',
    'h1' => 'How to make an Android app without coding',
    'meta' => 'an afternoon, free',
    'intro' => '<p>An app puts your business on your customers’ home screens — your menu, prices, opening hours and a one-tap call or WhatsApp button. You don’t need to code: you build the screens from ready-made blocks, and the builder makes a real native Android app for you. This guide walks you through it with the free <a href="'.e(route('app-builder')).'">Owlvix mobile app builder</a>.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the app builder in another tab — free to start.', 'Open the app builder'],
        ['Ready to make your app?', 'Free Android builds — APK to test, AAB for Google Play.', 'Make my app'],
    ],
    'before' => [
        '<strong>What your app is for</strong>: showing your menu, booking, contact, news — one main job.',
        '<strong>Your logo</strong> as a square image for the app icon.',
        '<strong>Your content</strong>: prices, photos, opening hours, phone and WhatsApp number.',
        '<strong>An Android phone</strong> to test on. For Google Play, a developer account (a one-time $25 fee to Google).',
    ],
    'steps' => [
        ['Decide what your app does', 'Pick the few things customers will open it for — your menu and prices, opening hours, ordering by WhatsApp, booking. An app that does three things well gets used; one that copies your whole website doesn’t.'],
        ['Plan your tabs', 'Apps have tabs along the bottom. Plan up to five, such as Home, Menu, Gallery and Contact. A tab can also show a page of your own website, such as a shop or booking page.',
            'put the thing people open the app for most in the first tab.'],
        ['Build your screens from blocks', 'In the free app builder, add native blocks to each screen: banners, text, pictures, buttons, menu and price lists, galleries, contact buttons, opening hours and YouTube videos.'],
        ['Add your name, icon, splash screen and colours', 'Upload a square logo for the icon, set your app’s name (keep it short so it fits under the icon), pick your colours and the splash screen shown while it opens.'],
        ['Build and test on your phone', 'Press Build. In a few minutes you get an APK file to install on your own Android phone. Tap through every screen, button and link.'],
        ['Publish on Google Play', 'Create a Google Play developer account (a one-time $25 fee), create an app, fill in the store listing with screenshots and a description, then upload the AAB file the builder makes for you. Google reviews new apps before they appear.',
            'screenshots sell your app on Google Play — take them from your test phone with your best screens showing.'],
        ['Keep it up to date', 'When you change screens, tabs or colours and save, phones that already have your app show the changes within a minute — no new build needed. Build again only to change the name, icon or splash screen.'],
    ],
    'mistakes' => [
        ['Copying your whole website.', 'Pick the few things people need on their phone.'],
        ['Too many tabs.', 'Five at most. Fewer is easier.'],
        ['Skipping the phone test.', 'Always try the APK yourself before uploading to Google Play.'],
        ['A long app name.', 'Long names get cut off under the icon. Keep it to one or two words.'],
    ],
    'more' => '<p>Need an iPhone app too, or logins, payments and push notifications? <a href="'.e(route('service.mobile-app-development')).'" data-quote>Talk to us about a custom app</a>.</p>',
    'faqTitle' => 'Making an app, explained',
    'faq' => [
        ['Can I really make an app for free?', 'Yes. Design your app and build it for Android free — up to 3 apps and 5 builds a day. The only cost is Google’s one-time $25 fee if you publish on Google Play.'],
        ['Is it a real app or just a website?', 'A real native app: every screen is built from native Android parts. You can add a tab that shows a page of your website if you want to.'],
        ['What’s the difference between APK and AAB?', 'The APK is for installing on your own phone to test. The AAB is the file Google Play asks for when you publish.'],
        ['Can I put my app on the iPhone App Store?', 'Apple only accepts apps sent from the owner’s own Apple developer account ($99 a year), built on a Mac. We can do this for you as a paid service.'],
        ['Does the app work offline?', 'Yes. The app keeps a copy of your screens, so it opens and works without internet. Tabs that show a web page need a connection.'],
        ['How long does Google Play take to approve an app?', 'Often a few days for a new app, sometimes longer. Updates are usually quicker. New personal developer accounts may need to test with a small group first, as Google’s rules require.'],
    ],
]])
