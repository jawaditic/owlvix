<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Formatted text from the project editor. Visitors can send HTML, so it is always cleaned
 * down to a short list of safe tags before it is saved or shown.
 */
class RichText
{
    private static ?HTMLPurifier $purifier = null;

    /** Keep only basic formatting: paragraphs, headings, bold/italic/underline/strike, lists, quotes, code and links. */
    public static function clean(?string $html): string
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,h2,h3,strong,b,em,i,u,s,ol,ul,li,blockquote,pre,code,a[href]');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('HTML.TargetBlank', true);
            $config->set('HTML.Nofollow', true);
            $config->set('AutoFormat.RemoveEmpty', true);
            $cache = storage_path('app/purifier');
            if (! is_dir($cache)) {
                @mkdir($cache, 0755, true);
            }
            $config->set('Cache.SerializerPath', $cache);
            self::$purifier = new HTMLPurifier($config);
        }

        return trim(self::$purifier->purify((string) $html));
    }

    /** Plain text version, for previews, search, CSV and length checks. */
    public static function plain(?string $html): string
    {
        $html = preg_replace('#<(br|/p|/h[23]|/li|/blockquote|/pre)\b[^>]*>#i', "$0\n", (string) $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Safe HTML for display. Older messages were plain text, so those keep their line breaks. */
    public static function html(?string $value): string
    {
        $value = (string) $value;
        // The editor always sends block tags (<p>, <ul>, ...); anything else is old plain text
        if (! preg_match('#^\s*<(p|h[23]|ul|ol|blockquote|pre)\b#i', $value)) {
            return nl2br(e($value));
        }

        return self::clean($value);
    }
}
