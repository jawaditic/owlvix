<?php

namespace App\Support;

/**
 * Duotone line icons (24×24) for the gradient icon tiles (.svc-icon in site.css).
 * Shapes with class "d" get a soft see-through fill; everything else is a white stroke.
 * Usage in Blade: {!! \App\Support\Icons::svg('chat') !!}
 */
class Icons
{
    private const ICONS = [
        'monitor' => '<rect class="d" x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'phone' => '<rect class="d" x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'chat' => '<path class="d" d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8M8 13h5"/>',
        'chat-dots' => '<path class="d" d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
        'sparkle' => '<path class="d" d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'link' => '<path d="M9 7H6a4 4 0 0 0 0 8h3M15 7h3a4 4 0 0 1 0 8h-3M8 11h8"/>',
        'server' => '<rect class="d" x="3" y="4" width="18" height="7" rx="2"/><rect class="d" x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6"/>',
        'cloud' => '<path class="d" d="M7 18a4.5 4.5 0 0 1-.6-9A6 6 0 0 1 18 8.5a4.8 4.8 0 0 1-.5 9.5z"/><path d="M12 11v5M9.5 13.5L12 11l2.5 2.5"/>',
        'database' => '<ellipse class="d" cx="12" cy="5.5" rx="7.5" ry="2.5"/><path d="M4.5 5.5v13c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-13M4.5 12c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5"/>',
        'shield' => '<path class="d" d="M12 3l7.5 3v5.5c0 4.6-3.2 8.3-7.5 9.5-4.3-1.2-7.5-4.9-7.5-9.5V6z"/><path d="M8.8 12.2l2.2 2.2 4.4-4.4"/>',
        'pulse' => '<rect class="d" x="3" y="4" width="18" height="16" rx="3"/><path d="M6 12h3l2-4 2.5 8 2-4H18"/>',
        'wrench' => '<path class="d" d="M14.7 6.3a4 4 0 0 0-5.4 5.2L3.5 17.3a1.8 1.8 0 0 0 2.5 2.5l5.8-5.8a4 4 0 0 0 5.2-5.4l-2.4 2.4-2.3-.5-.5-2.3z"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.6L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.6L20 16m0 4v-4h-4"/><circle class="d" cx="12" cy="12" r="3"/>',
        'key' => '<circle class="d" cx="8" cy="15" r="4.5"/><path d="M11.2 11.8L20 3M16.5 6.5l2.5 2.5M14 9l2 2"/>',
        'doc' => '<rect class="d" x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h3"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle class="d" cx="12" cy="12" r="3"/>',
        'lock' => '<rect class="d" x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v2"/>',
        'heart' => '<path class="d" d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z"/>',
        'globe' => '<circle class="d" cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
        'store' => '<path class="d" d="M4.5 10v10h15V10"/><path d="M3 10l1.6-5.5h14.8L21 10z"/><path d="M10 20v-5h4v5"/>',
        'cart' => '<path class="d" d="M6 6h15l-1.8 8.2a2 2 0 0 1-2 1.6H9.3a2 2 0 0 1-2-1.6z"/><path d="M3 3h2.5L6 6M9.5 20.5h.01M17.5 20.5h.01"/>',
        'card' => '<rect class="d" x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
        'mail' => '<rect class="d" x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6.5 8.5-6.5"/>',
        'inbox' => '<path class="d" d="M3 13l2.5-8h13L21 13v6a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19z"/><path d="M3 13h5l1.5 2.5h5L16 13h5"/>',
        'bell' => '<path class="d" d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
        'star' => '<path class="d" d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'users' => '<circle class="d" cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>',
        'calendar' => '<rect class="d" x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01"/>',
        'chart' => '<rect class="d" x="3" y="3" width="18" height="18" rx="3"/><path d="M7.5 16v-4M12 16V8M16.5 16v-6"/>',
        'rocket' => '<path class="d" d="M12 2.5c3.5 2 5.5 5.8 5 10.5l-2.5 3h-5L7 13c-.5-4.7 1.5-8.5 5-10.5z"/><path d="M9.5 16l-2.5 4.5 3-1M14.5 16l2.5 4.5-3-1M12 9h.01"/>',
        'book' => '<path class="d" d="M4 4.5A1.5 1.5 0 0 1 5.5 3H20v15H5.5A1.5 1.5 0 0 0 4 19.5z"/><path d="M4 19.5A1.5 1.5 0 0 0 5.5 21H20M8 7.5h8"/>',
        'clipboard' => '<rect class="d" x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M8.5 11.5l2 2 4-4M9 17h6"/>',
        'search' => '<circle class="d" cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/>',
        'pen' => '<path class="d" d="M4 20l1-4.5L15.5 5a2.1 2.1 0 0 1 3 3L8 18.5z"/><path d="M13.5 7l3 3M4 20h16"/>',
        'code' => '<rect class="d" x="3" y="4" width="18" height="16" rx="2.5"/><path d="M9.5 9.5L7 12l2.5 2.5M14.5 9.5L17 12l-2.5 2.5"/>',
        'tag' => '<path class="d" d="M3 12.2V4a1 1 0 0 1 1-1h8.2a1 1 0 0 1 .7.3l8 8a1 1 0 0 1 0 1.4l-8.2 8.2a1 1 0 0 1-1.4 0l-8-8a1 1 0 0 1-.3-.7z"/><path d="M7.5 7.5h.01"/>',
        'team' => '<circle class="d" cx="12" cy="7.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0M4.5 10.5a2.5 2.5 0 1 1 1-4.8M19.5 10.5a2.5 2.5 0 1 0-1-4.8"/>',
        'check' => '<circle class="d" cx="12" cy="12" r="9"/><path d="M8 12.5l2.8 2.8L16.5 9.5"/>',
    ];

    public static function svg(string $name): string
    {
        $paths = self::ICONS[$name] ?? self::ICONS['check'];

        return '<svg viewBox="0 0 24 24" aria-hidden="true">'.$paths.'</svg>';
    }

    /** All icon names (for the test that checks every page uses known icons). */
    public static function names(): array
    {
        return array_keys(self::ICONS);
    }
}
