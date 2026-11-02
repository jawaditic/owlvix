<?php

namespace App\Http\Controllers;

use App\Mail\BuilderBookingCancelled;
use App\Mail\BuilderBookingConfirmed;
use App\Mail\BuilderBookingNew;
use App\Models\BuilderBooking;
use App\Models\BuilderBookingPage;
use App\Support\Asset;
use App\Support\BookingSchema;
use App\Support\BookingSlots;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Booking pages made with the booking page builder: the page clients book on (/book/{id}, can sit in an iframe on
 * the owner's site), its free times, making a booking, and the client's own page to see or cancel it. Like builder
 * forms these run without a session or cookies (routes/web.php $stateless).
 */
class BookingPublicController extends Controller
{
    public const PER_IP_HOUR = 5;      // bookings per visitor per page

    public const PER_PAGE_DAY = 100;

    // emails to clients: anyone can type any address, so they're capped per page, per account and for everyone
    public const CLIENT_MAILS = ['page' => 60, 'account' => 100, 'all' => 1500];

    public function show(Request $request, string $id)
    {
        $page = $this->page($id);

        return response()->view('builder.booking', [
            'page' => $page, 'cfg' => self::publicCfg($page), 'embed' => $request->boolean('embed'),
            'jsUrl' => Asset::url('js/booking-page.js'), 'slotsUrl' => url('/book/'.$page->id.'/slots'), 'bookUrl' => url('/book/'.$page->id),
            'reportUrl' => 'mailto:'.config('mail.senders.abuse').'?subject='.rawurlencode('Report booking page '.$page->id),
        ])->withHeaders(self::headers());
    }

    public function slots(Request $request, string $id): JsonResponse
    {
        $page = $this->page($id);
        $service = BookingSchema::service($page->settings, (string) $request->query('service'));
        if (! $service) {
            return response()->json(['ok' => false, 'error' => 'Please choose a service.'], 422);
        }
        $today = CarbonImmutable::now($page->tz())->format('Y-m-d');
        $from = (string) $request->query('from');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! strtotime($from) || $from < $today) {
            $from = $today;
        }
        $last = CarbonImmutable::now($page->tz())->addDays((int) $page->settings['horizon'])->format('Y-m-d');
        if ($from > $last) {
            $from = $last;
        }

        return response()->json(['ok' => true, 'tz' => $page->tz(), 'days' => BookingSlots::days($page, $service, $from), 'more' => BookingSlots::moreAfter($page, $from)])
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public function book(Request $request, string $id): JsonResponse
    {
        $page = $this->page($id);
        $cfg = $page->settings;
        if (filled($request->input('_hp'))) { // honeypot: a bot filled the hidden box — act as if it worked
            return response()->json(['ok' => true, 'booking' => ['service' => '', 'when' => '', 'tz' => $page->tz(), 'manage' => '', 'note' => '']]);
        }
        $str = fn (string $k, int $max) => trim(mb_substr((string) $request->input($k, ''), 0, $max));
        $service = BookingSchema::service($cfg, $str('service', 24));
        $date = $str('date', 10);
        $time = $str('time', 5);
        $name = $str('name', 100);
        $email = $str('email', 150);
        $phone = $str('phone', 40);
        $note = $str('note', 1000);
        $err = fn (string $msg, ?string $field = null, int $code = 422) => response()->json(array_filter(['ok' => false, 'error' => $msg, 'field' => $field]), $code);
        if (! $service) {
            return $err('Please choose a service.', 'service');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $err('Please choose a day and a time.', 'time');
        }
        if ($name === '') {
            return $err('Please type your name.', 'name');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $err('Please check your email address.', 'email');
        }
        if (! empty($cfg['askPhone']) && strlen(preg_replace('/\D/', '', $phone)) < 6) {
            return $err('Please type your phone number.', 'phone');
        }
        if (preg_match_all('~https?://|www\.~i', $note.' '.$name) > 2) {
            return $err('Please remove the links from your message.', 'note');
        }
        $ipKey = 'booking:'.$page->id.':'.sha1($request->ip().'|'.config('app.key'));
        if (RateLimiter::tooManyAttempts($ipKey, self::PER_IP_HOUR) || RateLimiter::tooManyAttempts('booking-day:'.$page->id, self::PER_PAGE_DAY)) {
            return $err('Too many bookings from here just now. Please try again later or contact the business.', null, 429);
        }

        $tz = $page->tz();
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, $tz);
        if (! $start || $start->format('Y-m-d H:i') !== $date.' '.$time) { // not a real date, or skipped by a clock change
            return $err('Please choose a day and a time.', 'time');
        }
        // one booking at a time per page, so two people can't take the same time
        $booking = DB::transaction(function () use ($page, $service, $date, $time, $start, $name, $email, $phone, $note) {
            BuilderBookingPage::whereKey($page->id)->lockForUpdate()->first();
            if (! BookingSlots::isFree($page, $service, $date, $time)) {
                return null;
            }
            $b = $page->bookingsList()->create([
                'token' => Str::random(40), 'service_key' => $service['key'], 'service' => $service['name'], 'mins' => $service['mins'], 'price' => $service['price'] ?: null,
                'starts_at' => $start->utc(), 'ends_at' => $start->addMinutes($service['mins'])->utc(),
                'name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'note' => $note ?: null,
            ]);
            BuilderBookingPage::whereKey($page->id)->increment('bookings');

            return $b;
        });
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Sorry — someone just booked that time. Please pick another.', 'taken' => true], 409);
        }
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit('booking-day:'.$page->id, 86400);
        $booking->setRelation('page', $page);
        $owner = $page->account->email;
        \Illuminate\Support\defer(function () use ($booking, $owner, $page) {
            try {
                if (self::mayEmailClient($page)) {
                    self::countClientEmail($page);
                    Mail::to($booking->email)->send(new BuilderBookingConfirmed($booking, $owner));
                }
                if (self::mayEmailOwner($page->builder_account_id)) {
                    Mail::to($owner)->send(new BuilderBookingNew($booking));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json(['ok' => true, 'booking' => [
            'service' => $booking->service, 'when' => $booking->when(), 'tz' => $tz, 'manage' => $booking->manageUrl(), 'note' => (string) ($page->settings['note'] ?? ''),
        ]]);
    }

    /** The client's page for their booking: details, add to calendar, cancel. */
    public function manage(Request $request, string $token)
    {
        $booking = $this->booking($token);

        return response()->view('builder.booking-manage', ['b' => $booking, 'page' => $booking->page, 'done' => $request->query('done'), 'ask' => $request->query('ask') === 'cancel'])
            ->withHeaders(self::headers(false));
    }

    public function ics(string $token)
    {
        $booking = $this->booking($token);

        return response($booking->ics(), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="booking.ics"', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store']);
    }

    public function cancel(string $token)
    {
        $booking = $this->booking($token);
        if ($booking->cancellable() && BuilderBooking::whereKey($booking->id)->where('status', 'confirmed')->update(['status' => 'cancelled', 'cancelled_by' => 'client'])) {
            $booking->refresh()->setRelation('page', $booking->page);
            $owner = $booking->page->account->email;
            \Illuminate\Support\defer(function () use ($booking, $owner) {
                try {
                    if (self::mayEmailOwner($booking->page->builder_account_id)) {
                        Mail::to($owner)->send(new BuilderBookingCancelled($booking, 'owner'));
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return redirect('/book/manage/'.$token.'?done=cancelled', 303);
    }

    /* ------------------------------------------------ helpers */

    /** Settings the public page needs (everything in the settings is meant for the public). */
    public static function publicCfg(BuilderBookingPage $page): array
    {
        return ['id' => $page->id] + $page->settings;
    }

    public static function headers(bool $frame = true): array
    {
        return [
            'X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; script-src 'self'; connect-src 'self'; style-src 'unsafe-inline'; img-src data:; "
                ."form-action 'self'; base-uri 'none'; frame-ancestors ".($frame ? '*' : "'none'"),
        ];
    }

    /** Owner alerts share the form builder's caps: 200 a day per account, 400 an hour for everyone. */
    public static function mayEmailOwner(int $accountId): bool
    {
        $key = 'builder-owner-mail:'.$accountId;
        if (RateLimiter::tooManyAttempts($key, 200) || RateLimiter::tooManyAttempts('builder-owner-mail-all', 400)) {
            return false;
        }
        RateLimiter::hit($key, 86400);
        RateLimiter::hit('builder-owner-mail-all', 3600);

        return true;
    }

    private static function clientLimits(BuilderBookingPage $page): array
    {
        $l = self::CLIENT_MAILS;

        return ['booking-mail:'.$page->id => $l['page'], 'booking-mail-acct:'.$page->builder_account_id => $l['account'], 'booking-mail-all' => $l['all']];
    }

    public static function mayEmailClient(BuilderBookingPage $page): bool
    {
        foreach (self::clientLimits($page) as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }

        return true;
    }

    public static function countClientEmail(BuilderBookingPage $page): void
    {
        foreach (array_keys(self::clientLimits($page)) as $key) {
            RateLimiter::hit($key, 86400);
        }
    }

    private function page(string $id): BuilderBookingPage
    {
        return BuilderBookingPage::whereKey($id)->whereNull('disabled_at')->firstOrFail();
    }

    private function booking(string $token): BuilderBooking
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);

        return BuilderBooking::where('token', $token)->with('page.account')->firstOrFail();
    }
}
