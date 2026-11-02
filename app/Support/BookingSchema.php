<?php

namespace App\Support;

/**
 * Settings of a booking page (booking page builder), checked before saving. clean() returns the cleaned array or a
 * plain-English error string. Shape: see the comment on each key below and public/js/booking-builder.js.
 */
class BookingSchema
{
    public const MAX_SERVICES = 12;

    public const STEPS = [0, 15, 30, 60];

    public static function clean(mixed $cfg): array|string
    {
        if (! is_array($cfg)) {
            return 'Something went wrong — please reload the page.';
        }
        $str = fn ($v, int $max) => is_scalar($v) ? trim(mb_substr(str_replace("\0", '', (string) $v), 0, $max)) : '';
        $int = fn ($v, int $min, int $max, int $d) => is_numeric($v) ? max($min, min($max, (int) $v)) : $d;

        $name = $str($cfg['name'] ?? '', 80);
        if ($name === '') {
            return 'Please type your business name.';
        }
        $tz = (string) ($cfg['tz'] ?? '');
        if (! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return 'Please choose your time zone.';
        }

        // services: name, length in minutes, price (free text), short description
        $services = [];
        foreach (array_slice(is_array($cfg['services'] ?? null) ? $cfg['services'] : [], 0, self::MAX_SERVICES) as $s) {
            if (! is_array($s) || ($n = $str($s['name'] ?? '', 80)) === '') {
                continue;
            }
            $key = preg_match('/^[a-z0-9]{1,24}$/', (string) ($s['key'] ?? '')) ? $s['key'] : substr(md5($n.count($services)), 0, 8);
            while (isset($services[$key])) {
                $key = substr(md5($key.'x'), 0, 8);
            }
            $mins = $int($s['mins'] ?? 30, 5, 480, 30);
            $services[$key] = ['key' => $key, 'name' => $n, 'mins' => (int) (round($mins / 5) * 5), 'price' => $str($s['price'] ?? '', 30), 'desc' => $str($s['desc'] ?? '', 200)];
        }
        if (! $services) {
            return 'Please add at least one service, with a name.';
        }

        // opening hours: 7 days (Monday first), each up to 3 ranges "HH:MM"–"HH:MM"
        $hours = [];
        $any = false;
        for ($d = 0; $d < 7; $d++) {
            $day = [];
            foreach (array_slice(is_array($cfg['hours'][$d] ?? null) ? $cfg['hours'][$d] : [], 0, 3) as $r) {
                $a = is_array($r) ? self::minutes($r[0] ?? '') : null;
                $b = is_array($r) ? self::minutes($r[1] ?? '') : null;
                if ($a === null || $b === null || $b <= $a) {
                    continue;
                }
                $day[] = [self::hhmm($a), self::hhmm($b)];
            }
            usort($day, fn ($x, $y) => strcmp($x[0], $y[0]));
            $any = $any || $day;
            $hours[] = $day;
        }
        if (! $any) {
            return 'Please set your opening hours for at least one day.';
        }

        $off = [];
        foreach (array_slice(is_array($cfg['off'] ?? null) ? $cfg['off'] : [], 0, 100) as $d) {
            if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
                $off[$d] = true;
            }
        }
        $off = array_keys($off);
        sort($off);

        $color = (string) ($cfg['color'] ?? '');

        return [
            'name' => $name, 'intro' => $str($cfg['intro'] ?? '', 500), 'location' => $str($cfg['location'] ?? '', 200),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '#1ec773', 'tz' => $tz,
            'services' => array_values($services), 'hours' => $hours, 'off' => $off,
            'notice' => $int($cfg['notice'] ?? 2, 0, 168, 2), 'horizon' => $int($cfg['horizon'] ?? 60, 1, 180, 60),
            'buffer' => $int($cfg['buffer'] ?? 0, 0, 120, 0),
            'step' => in_array((int) ($cfg['step'] ?? 0), self::STEPS, true) ? (int) $cfg['step'] : 0,
            'askPhone' => (bool) ($cfg['askPhone'] ?? false), 'note' => $str($cfg['note'] ?? '', 500),
        ];
    }

    public static function service(array $cfg, string $key): ?array
    {
        foreach ($cfg['services'] ?? [] as $s) {
            if (($s['key'] ?? null) === $key) {
                return $s;
            }
        }

        return null;
    }

    /** "09:30" → 570; "24:00" allowed as an end. */
    public static function minutes(mixed $v): ?int
    {
        if (! is_string($v) || ! preg_match('/^([01]\d|2[0-4]):([0-5]\d)$/', $v, $m)) {
            return null;
        }
        $t = (int) $m[1] * 60 + (int) $m[2];

        return $t <= 1440 ? $t : null;
    }

    public static function hhmm(int $mins): string
    {
        return sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
    }
}
