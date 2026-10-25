<?php

namespace App\Support;

/** Country names and flag emoji from a two-letter code (e.g. "us" → United States, 🇺🇸). */
class Country
{
    public static function name(?string $code): string
    {
        $code = strtolower((string) $code);
        if ($code === '') {
            return 'Unknown';
        }
        if ($name = config('countries.'.$code)) {
            return $name;
        }
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.strtoupper($code), 'en');
            if ($name && strtoupper($name) !== strtoupper($code)) {
                return $name;
            }
        }

        return strtoupper($code);
    }

    public static function flag(?string $code): string
    {
        $code = strtoupper((string) $code);
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '🌐';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
