<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One booking on a booking page. Times are stored in UTC and shown in the page's time zone. */
class BuilderBooking extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'reminded_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(BuilderBookingPage::class, 'builder_booking_page_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function localStart(): CarbonImmutable
    {
        return $this->starts_at->setTimezone($this->page->tz());
    }

    public function localEnd(): CarbonImmutable
    {
        return $this->ends_at->setTimezone($this->page->tz());
    }

    /** "Tue 3 Nov 2026, 09:45–10:30" in the page's time zone. */
    public function when(): string
    {
        return $this->localStart()->format('D j M Y, H:i').'–'.$this->localEnd()->format('H:i');
    }

    public function manageUrl(): string
    {
        return url('/book/manage/'.$this->token);
    }

    /** Clients may cancel until the booking starts. */
    public function cancellable(): bool
    {
        return $this->isConfirmed() && $this->starts_at->isFuture();
    }

    /** A calendar file (.ics) for this booking. */
    public function ics(): string
    {
        $e = fn (string $s) => str_replace(["\\", "\r\n", "\n", ',', ';'], ['\\\\', '\\n', '\\n', '\\,', '\;'], $s);
        $f = fn ($t) => $t->utc()->format('Ymd\THis\Z');
        $loc = (string) ($this->page->settings['location'] ?? '');

        return implode("\r\n", array_filter([
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Owlvix//Booking//EN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:booking-'.$this->id.'-'.substr($this->token, 0, 8).'@owlvix.com', 'DTSTAMP:'.$f(now()), 'DTSTART:'.$f($this->starts_at), 'DTEND:'.$f($this->ends_at),
            'SUMMARY:'.$e($this->service.' — '.$this->page->name()), $loc !== '' ? 'LOCATION:'.$e($loc) : null,
            'DESCRIPTION:'.$e('Change or cancel: '.$this->manageUrl()), 'STATUS:'.($this->isConfirmed() ? 'CONFIRMED' : 'CANCELLED'),
            'END:VEVENT', 'END:VCALENDAR',
        ]))."\r\n";
    }

    /** What the editor gets back (times in the page's zone). */
    public function toApi(): array
    {
        return [
            'id' => $this->id, 'service' => $this->service, 'mins' => $this->mins, 'price' => $this->price,
            'date' => $this->localStart()->format('Y-m-d'), 'time' => $this->localStart()->format('H:i'), 'end' => $this->localEnd()->format('H:i'),
            'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'note' => $this->note,
            'status' => $this->status, 'cancelled_by' => $this->cancelled_by, 'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
