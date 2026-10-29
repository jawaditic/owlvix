<?php

namespace App\Support;

/**
 * Who Owlvix is, for structured data (schema.org) on every page. One place, so every page describes
 * the business the same way. Facts confirmed by the owner on 30 Oct 2026; the About page says the same.
 */
class Owlvix
{
    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#business',
            'name' => 'Owlvix',
            'alternateName' => 'Anjtec',
            'url' => url('/'),
            'logo' => asset('apple-touch-icon.png'),
            'image' => asset('images/og.png'),
            'description' => 'Owlvix designs, builds and launches web apps, mobile apps and AI tools for growing businesses — from the first sketch to the server it runs on. Formerly Anjtec on Fiverr.',
            'email' => config('mail.from.address'),
            'founder' => self::founder(),
            'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'PK'],
            'areaServed' => 'Worldwide',
            'knowsAbout' => ['Laravel', 'React', 'Next.js', 'Vue', 'React Native', 'Node.js', 'Python', 'Shopify', 'WordPress', 'OpenAI', 'Claude', 'Gemini'],
            'sameAs' => array_values(array_filter([Testimonials::data()['profile_url'] ?? null])),
        ];
    }

    public static function founder(): array
    {
        return [
            '@type' => 'Person',
            '@id' => url('/').'#founder',
            'name' => 'Jawad Ahmad',
            'jobTitle' => 'Founder and Lead Developer',
            'url' => route('about'),
            'worksFor' => ['@id' => url('/').'#business'],
            'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'PK'],
        ];
    }
}
