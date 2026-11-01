<?php

namespace App\Support;

/**
 * Step-by-step guides that send search visitors to a free builder. Each guide is its own view in resources/views/guides
 * (text only; the page itself is guides/partials/guide). Routes, the sitemap, the "Read our guide" link on each
 * builder and the "More guides" list all come from this list.
 */
class Guides
{
    /** route name => [path, view, short title, builder route, question shown above the link on the builder, one-line summary] */
    public const ALL = [
        'guide.sell-online' => ['/how-to-sell-online', 'sell-online', 'How to start selling online for free', 'store-builder', 'New to selling online?', 'Pricing, product photos, payments, delivery and your first orders.'],
        'guide.business-website' => ['/how-to-make-a-website-for-your-business', 'business-website', 'How to make a website for your small business', 'website-builder', 'First business website?', 'The pages you need, what to write, photos, a contact form and getting found on Google.'],
        'guide.qr-menu' => ['/how-to-make-a-qr-code-menu', 'qr-menu', 'How to make a QR code menu for your restaurant', 'menu-builder', 'First digital menu?', 'Dishes and prices, allergens, WhatsApp orders and printing QR table cards.'],
        'guide.wedding-website' => ['/how-to-make-a-wedding-website', 'wedding-website', 'How to make a wedding website with RSVPs', 'event-website-builder', 'Never made a wedding website?', 'The plan for the day, venue and travel, RSVPs with meal choices and your guest list.'],
        'guide.portfolio' => ['/how-to-make-a-portfolio-website', 'portfolio', 'How to make a portfolio website that wins clients', 'portfolio-builder', 'Building your first portfolio?', 'Choosing your best work, writing about yourself, prices, reviews and enquiries.'],
        'guide.cv' => ['/how-to-write-a-cv', 'cv', 'How to write a CV that gets interviews', 'resume-builder', 'Not sure what to write?', 'Layout, a short profile, results-based bullet points, skills for ATS scans and PDF.'],
        'guide.link-in-bio' => ['/how-to-make-a-link-in-bio-page', 'link-in-bio', 'How to make a link in bio page', 'link-in-bio-builder', 'New to link in bio pages?', 'One link for Instagram, TikTok and YouTube that leads everywhere.'],
        'guide.coming-soon' => ['/how-to-make-a-coming-soon-page', 'coming-soon', 'How to make a coming soon page and build a waitlist', 'coming-soon-builder', 'Launching something new?', 'A countdown, a sign-up form and getting your first fans before launch day.'],
        'guide.sales-funnel' => ['/how-to-build-a-sales-funnel', 'sales-funnel', 'How to build a sales funnel', 'funnel-builder', 'New to sales funnels?', 'A free offer, an opt-in page, a sales page, payments and A/B tests.'],
        'guide.newsletter' => ['/how-to-make-an-email-newsletter', 'newsletter', 'How to make an email newsletter', 'email-builder', 'First newsletter?', 'What to send, a simple design, images, Mailchimp or Brevo, and testing before you send.'],
        'guide.android-app' => ['/how-to-make-an-app-without-coding', 'android-app', 'How to make an Android app without coding', 'app-builder', 'Never made an app?', 'Planning your screens, building them from blocks, testing on your phone and Google Play.'],
    ];

    /** The guide for a builder page, as [route name, question], or null. */
    public static function forBuilder(?string $builderRoute): ?array
    {
        foreach (self::ALL as $route => $g) {
            if ($g[3] === $builderRoute) {
                return [$route, $g[4]];
            }
        }

        return null;
    }

    /** route name => view path under resources/views, for the sitemap. */
    public static function views(): array
    {
        return ['guides' => 'guides/index'] + array_map(fn ($g) => 'guides/'.$g[1], self::ALL);
    }
}
