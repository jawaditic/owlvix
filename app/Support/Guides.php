<?php

namespace App\Support;

/**
 * Step-by-step guides that send search visitors to a free builder. Each guide is its own view in resources/views/guides
 * (text only; the page itself is guides/partials/guide). Routes, the sitemap, the "Read our guide" link on each
 * builder and the "More guides" list all come from this list.
 */
class Guides
{
    /** route name => [path, view, short title, builder route, question shown above the link on the builder] */
    public const ALL = [
        'guide.sell-online' => ['/how-to-sell-online', 'sell-online', 'How to start selling online for free', 'store-builder', 'New to selling online?'],
        'guide.qr-menu' => ['/how-to-make-a-qr-code-menu', 'qr-menu', 'How to make a QR code menu for your restaurant', 'menu-builder', 'First digital menu?'],
        'guide.wedding-website' => ['/how-to-make-a-wedding-website', 'wedding-website', 'How to make a wedding website with RSVPs', 'event-website-builder', 'Never made a wedding website?'],
        'guide.cv' => ['/how-to-write-a-cv', 'cv', 'How to write a CV that gets interviews', 'resume-builder', 'Not sure what to write?'],
        'guide.link-in-bio' => ['/how-to-make-a-link-in-bio-page', 'link-in-bio', 'How to make a link in bio page', 'link-in-bio-builder', 'New to link in bio pages?'],
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
        return array_map(fn ($g) => 'guides/'.$g[1], self::ALL);
    }
}
