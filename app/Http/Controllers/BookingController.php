<?php

namespace App\Http\Controllers;

use App\Mail\BuilderBookingCancelled;
use App\Models\BuilderBooking;
use App\Models\BuilderBookingPage;
use App\Support\BookingSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** The booking page builder's owner side (signed in, X-Builder-Token): save pages, see and cancel bookings. */
class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'pages' => $this->user($request)->bookingPages()->latest('updated_at')->get()->map->toApi()->values()]);
    }

    public function save(Request $request): JsonResponse
    {
        $cfg = BookingSchema::clean($request->input('cfg'));
        if (is_string($cfg)) {
            return $this->fail($cfg, 422);
        }
        $user = $this->user($request);
        $id = (string) $request->input('id', '');
        if ($id !== '') {
            if (! preg_match('/^[a-z0-9]{16}$/', $id) || ! ($page = $user->bookingPages()->whereKey($id)->first())) {
                return BuilderBookingPage::whereKey($id)->exists()
                    ? $this->fail('This booking page belongs to another account. Sign in with that email to change it.', 403)
                    : $this->fail('That booking page was deleted. Publish again to make a new one.', 404);
            }
            $page->update(['settings' => $cfg]);
        } else {
            if ($user->bookingPages()->count() >= BuilderBookingPage::MAX_PER_ACCOUNT) {
                return $this->fail('You have '.BuilderBookingPage::MAX_PER_ACCOUNT.' booking pages already — open one of them under “My booking pages” and change it, or delete one.', 422);
            }
            if (RateLimiter::tooManyAttempts('booking-page-new:'.$user->id, 10)) {
                return $this->fail('Too many new booking pages today. Please try again tomorrow.', 429);
            }
            RateLimiter::hit('booking-page-new:'.$user->id, 86400);
            $page = $user->bookingPages()->create(['id' => BuilderBookingPage::newId(), 'settings' => $cfg]);
        }

        return response()->json(['ok' => true, 'page' => $page->fresh()->toApi()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->own($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Upcoming bookings (soonest first), then up to 200 past ones (latest first). ?format=csv for all of them. */
    public function bookings(Request $request, string $id)
    {
        $page = $this->own($request, $id);
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($page) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Date', 'Start', 'End', 'Service', 'Price', 'Name', 'Email', 'Phone', 'Message', 'Status', 'Booked at'], ',', '"', '');
                foreach ($page->bookingsList()->orderBy('starts_at')->lazy(500) as $b) {
                    $b->setRelation('page', $page);
                    $a = $b->toApi();
                    fputcsv($out, array_map([BuilderController::class, 'csvCell'], [$a['date'], $a['time'], $a['end'], $a['service'], $a['price'], $a['name'], $a['email'], $a['phone'], $a['note'],
                        $a['status'].($a['cancelled_by'] ? ' by '.$a['cancelled_by'] : ''), $b->created_at?->setTimezone($page->tz())->format('Y-m-d H:i')]), ',', '"', '');
                }
                fclose($out);
            }, 'bookings-'.$page->id.'.csv', ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'no-store']);
        }
        $now = now();
        $map = fn ($b) => $b->setRelation('page', $page)->toApi();
        $upcoming = $page->bookingsList()->where('ends_at', '>=', $now)->orderBy('starts_at')->limit(500)->get()->map($map);
        $past = $page->bookingsList()->where('ends_at', '<', $now)->orderByDesc('starts_at')->limit(200)->get()->map($map);

        return response()->json(['ok' => true, 'bookings' => $upcoming->concat($past)->values(), 'total' => $page->bookingsList()->count()]);
    }

    public function cancel(Request $request, string $id, int $booking): JsonResponse
    {
        $page = $this->own($request, $id);
        $b = $page->bookingsList()->whereKey($booking)->firstOrFail();
        if (! $b->isConfirmed()) {
            return response()->json(['ok' => true]);
        }
        BuilderBooking::whereKey($b->id)->where('status', 'confirmed')->update(['status' => 'cancelled', 'cancelled_by' => 'owner']);
        $b->refresh()->setRelation('page', $page);
        if ($b->starts_at->isFuture() && BookingPublicController::mayEmailClient($page)) {
            BookingPublicController::countClientEmail($page);
            $owner = $this->user($request)->email;
            \Illuminate\Support\defer(function () use ($b, $owner) {
                try {
                    Mail::to($b->email)->send(new BuilderBookingCancelled($b, 'client', $owner));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json(['ok' => true]);
    }

    private function own(Request $request, string $id): BuilderBookingPage
    {
        abort_unless(preg_match('/^[a-z0-9]{16}$/', $id), 404);

        return $this->user($request)->bookingPages()->whereKey($id)->firstOrFail();
    }

    private function user(Request $request)
    {
        return $request->attributes->get('builder_account');
    }

    private function fail(string $error, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error], $status);
    }
}
