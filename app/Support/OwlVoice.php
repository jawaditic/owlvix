<?php

namespace App\Support;

class OwlVoice
{
    /** Voice lines for the talking owl, ready for the browser: text, audio URL, lip-sync data. */
    public static function sets(array $names): array
    {
        return collect(config('owl'))
            ->only($names)
            ->map(fn (array $lines) => array_map(fn (array $l) => [
                'text' => $l['text'],
                'src' => asset($l['file']),
                'lipsync' => $l['lipsync'],
            ], $lines))
            ->all();
    }
}
