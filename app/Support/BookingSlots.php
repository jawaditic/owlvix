<?php

namespace App\Support;

use App\Models\BuilderBooking;
use App\Models\BuilderBookingPage;
use Carbon\CarbonImmutable;

/**
 * Free times on a booking page. The rules (same as the editor preview in public/js/booking-page.js localSlots):
 * a day has times only if it's within today … today + horizon and not a day off; inside each opening range a time
 * every `step` minutes (or the service's length) while the service still fits; none earlier than now + notice hours;
 * none overlapping a confirmed booking, keeping `buffer` minutes free around bookings.
 */
class BookingSlots
{
    /** Free start times ("HH:MM") for each of `days` days from `from` (Y-m-d, in the page's zone). */
    public static function days(BuilderBookingPage $page, array $service, string $from, int $days = 7): array
    {
        $cfg = $page->settings;
        $tz = $page->tz();
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, $tz);
        $end = $start->addDays($days);
        $busy = $page->bookingsList()->where('status', 'confirmed')
            ->where('starts_at', '<', $end->addDay()->utc())->where('ends_at', '>', $start->subDay()->utc())
            ->get(['starts_at', 'ends_at'])->map(fn ($b) => [$b->starts_at->getTimestamp(), $b->ends_at->getTimestamp()])->all();
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->addDays($i);
            $out[] = ['date' => $d->format('Y-m-d'), 'slots' => self::forDay($cfg, $service, $d, $busy)];
        }

        return $out;
    }

    /** @param  array<int, array{int, int}>  $busy  confirmed bookings as [start, end] Unix times */
    public static function forDay(array $cfg, array $service, CarbonImmutable $day, array $busy, ?CarbonImmutable $now = null): array
    {
        $tz = $cfg['tz'];
        $now = ($now ?? CarbonImmutable::now())->setTimezone($tz);
        $date = $day->format('Y-m-d');
        $today = $now->format('Y-m-d');
        if ($date < $today || $date > $now->addDays((int) $cfg['horizon'])->format('Y-m-d') || in_array($date, $cfg['off'] ?? [], true)) {
            return [];
        }
        $mins = (int) $service['mins'];
        $step = (int) ($cfg['step'] ?? 0) ?: $mins;
        $earliest = $now->getTimestamp() + (int) $cfg['notice'] * 3600;
        $buffer = (int) ($cfg['buffer'] ?? 0) * 60;
        $midnight = CarbonImmutable::createFromFormat('!Y-m-d', $date, $tz);
        $slots = [];
        foreach ($cfg['hours'][$day->dayOfWeekIso - 1] ?? [] as [$a, $b]) {
            $a = BookingSchema::minutes($a);
            $b = BookingSchema::minutes($b);
            for ($t = $a; $t + $mins <= $b; $t += $step) {
                // wall-clock time on that day (handles clock changes: a time that doesn't exist moves forward)
                $s = $midnight->setTime(intdiv($t, 60) % 24, $t % 60)->addDays(intdiv($t, 1440))->getTimestamp();
                $e = $s + $mins * 60;
                if ($s < $earliest) {
                    continue;
                }
                foreach ($busy as [$bs, $be]) {
                    if ($s < $be + $buffer && $bs < $e + $buffer) {
                        continue 2;
                    }
                }
                $slots[BookingSchema::hhmm($t)] = true;
            }
        }

        return array_keys($slots);
    }

    /** Is this exact start time free right now? (Checked again inside the booking transaction.) */
    public static function isFree(BuilderBookingPage $page, array $service, string $date, string $time): bool
    {
        return in_array($time, self::days($page, $service, $date, 1)[0]['slots'], true);
    }

    /** Are there bookable days after `from` + 7? (For the "next week" button.) */
    public static function moreAfter(BuilderBookingPage $page, string $from): bool
    {
        $now = CarbonImmutable::now($page->tz());

        return CarbonImmutable::createFromFormat('!Y-m-d', $from, $page->tz())->addDays(7)->format('Y-m-d') <= $now->addDays((int) $page->settings['horizon'])->format('Y-m-d');
    }
}
