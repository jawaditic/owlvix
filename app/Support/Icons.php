<?php

namespace App\Support;

/**
 * Duotone line icons (24×24) for the gradient icon tiles (.svc-icon in site.css).
 * Shapes with class "d" get a soft see-through fill; everything else is a white stroke.
 * Usage in Blade: {!! \App\Support\Icons::svg('chat') !!} for a tile, {!! \App\Support\Icons::i('chat') !!} inline in text
 * (any colour, 1em — Owlvix's own icons instead of emoji). JavaScript has the same icons: window.OwlIcon (js/icons.js).
 */
class Icons
{
    /** name => SVG shapes, from resources/icons/icons.json (the same list public/js/icons.js is made from). */
    private static ?array $icons = null;

    private static function all(): array
    {
        return self::$icons ??= json_decode((string) file_get_contents(resource_path('icons/icons.json')), true) ?: [];
    }

    public static function svg(string $name): string
    {
        $paths = self::all()[$name] ?? self::all()['check'];

        return '<svg viewBox="0 0 24 24" aria-hidden="true">'.$paths.'</svg>';
    }

    /** All icon names (for the test that checks every page uses known icons). */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /** An inline icon that takes the text colour and size (like an emoji, but ours). */
    public static function i(string $name, string $class = '', string $label = ''): string
    {
        $paths = str_replace([' class="d"', ' class="f"'], [' fill="currentColor" fill-opacity=".16"', ' fill="currentColor"'], self::all()[$name] ?? self::all()['check']);
        $a11y = $label !== '' ? 'role="img" aria-label="'.e($label).'"' : 'aria-hidden="true"';

        return '<svg class="oi'.($class !== '' ? ' '.e($class) : '').'" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '.$a11y.' focusable="false">'.$paths.'</svg>';
    }
}
