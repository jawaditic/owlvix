<?php

namespace Tests\Feature;

use App\Mail\BuilderBookingCancelled;
use App\Mail\BuilderBookingConfirmed;
use App\Mail\BuilderBookingNew;
use App\Mail\BuilderBookingReminder;
use App\Models\BuilderAccount;
use App\Models\BuilderBooking;
use App\Models\BuilderBookingPage;
use App\Support\BookingSchema;
use App\Support\BookingReminders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Booking page builder: settings, free times, booking, emails, cancelling, reminders, owner endpoints. */
class BookingBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 2 Nov 2026, 08:00 in London
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00', 'Europe/London'));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::parse('2026-11-02 08:00', 'Europe/London'));
    }

    private function cfg(array $over = []): array
    {
        return $over + [
            'name' => 'Glow Hair Studio', 'intro' => 'Cuts and colour', 'location' => '12 Market St, Leeds', 'color' => '#7c3aed', 'tz' => 'Europe/London',
            'services' => [['key' => 'cut', 'name' => 'Haircut', 'mins' => 45, 'price' => '£35', 'desc' => 'Wash, cut & style'], ['key' => 'beard', 'name' => 'Beard trim', 'mins' => 20, 'price' => '', 'desc' => '']],
            'hours' => [[['09:00', '12:00'], ['13:00', '17:00']], [['09:00', '17:00']], [], [], [], [['10:00', '14:00']], []],
            'off' => ['2026-11-03'], 'notice' => 2, 'horizon' => 30, 'buffer' => 15, 'step' => 0, 'askPhone' => true, 'note' => 'Please arrive 5 minutes early.',
        ];
    }

    private function owner(string $email = 'owner@example.com'): array
    {
        $acct = BuilderAccount::firstOrCreate(['email' => $email]);

        return [$acct, ['X-Builder-Token' => $acct->issueToken()]];
    }

    private function page(array $over = []): BuilderBookingPage
    {
        [$acct] = $this->owner();

        return $acct->bookingPages()->create(['id' => BuilderBookingPage::newId(), 'settings' => BookingSchema::clean($this->cfg($over))]);
    }

    private function bookIt(BuilderBookingPage $page, array $over = [])
    {
        return $this->postJson('/book/'.$page->id, $over + ['service' => 'cut', 'date' => '2026-11-02', 'time' => '13:00', 'name' => 'Priya Shah', 'email' => 'priya@example.com', 'phone' => '+44 7700 900123', 'note' => 'First visit', '_hp' => '']);
    }

    public function test_settings_are_cleaned_and_bad_ones_refused(): void
    {
        $c = BookingSchema::clean($this->cfg(['color' => 'red', 'step' => 7, 'notice' => 999, 'hours' => [[['17:00', '09:00'], ['09:00', '12:00']], [], [], [], [], [], []]]));
        $this->assertSame('#1ec773', $c['color']);
        $this->assertSame(0, $c['step']);
        $this->assertSame(168, $c['notice']);
        $this->assertSame([['09:00', '12:00']], $c['hours'][0]); // the backwards range is dropped
        $this->assertIsString(BookingSchema::clean($this->cfg(['name' => ''])));
        $this->assertIsString(BookingSchema::clean($this->cfg(['tz' => 'Mars/Base'])));
        $this->assertIsString(BookingSchema::clean($this->cfg(['services' => []])));
        $this->assertIsString(BookingSchema::clean($this->cfg(['hours' => [[], [], [], [], [], [], []]])));
    }

    public function test_free_times_follow_the_rules(): void
    {
        $page = $this->page();
        $days = $this->getJson('/book/'.$page->id.'/slots?service=cut&from=2026-11-02')->assertOk()->json('days');
        $this->assertCount(7, $days);
        // Monday: notice of 2 hours from 08:00 → nothing before 10:00; 45-minute slots; lunch gap
        $this->assertSame(['10:30', '11:15', '13:00', '13:45', '14:30', '15:15', '16:00'], $days[0]['slots']);
        $this->assertSame([], $days[1]['slots']);                // Tuesday is a day off
        $this->assertSame([], $days[2]['slots']);                // Wednesday closed
        $this->assertSame(['10:00', '10:45', '11:30', '12:15', '13:00'], $days[5]['slots']); // Saturday 10–14
        // a past "from" starts today; a service that doesn't exist is refused
        $this->assertSame('2026-11-02', $this->getJson('/book/'.$page->id.'/slots?service=cut&from=2020-01-01')->json('days.0.date'));
        $this->getJson('/book/'.$page->id.'/slots?service=nope')->assertStatus(422);
        // nothing beyond the horizon
        $far = $this->getJson('/book/'.$page->id.'/slots?service=cut&from=2026-12-07')->json('days');
        $this->assertSame([], array_merge(...array_column($far, 'slots')));
    }

    public function test_a_booking_is_made_emailed_and_its_time_is_taken(): void
    {
        Mail::fake();
        $page = $this->page();
        $r = $this->bookIt($page)->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('Mon 2 Nov 2026, 13:00–13:45', $r->json('booking.when'));
        $b = BuilderBooking::sole();
        $this->assertSame('2026-11-02 13:00:00', $b->starts_at->utc()->format('Y-m-d H:i:s')); // London = UTC in November
        $this->assertStringContainsString('/book/manage/'.$b->token, $r->json('booking.manage'));
        Mail::assertSent(BuilderBookingConfirmed::class, fn ($m) => $m->hasTo('priya@example.com') && $m->hasReplyTo('owner@example.com') && $m->hasFrom(config('mail.senders.orders')));
        Mail::assertSent(BuilderBookingNew::class, fn ($m) => $m->hasTo('owner@example.com') && $m->hasReplyTo('priya@example.com'));

        // the same time (and times within the 15-minute gap) are gone; another person can't take it
        $slots = $this->getJson('/book/'.$page->id.'/slots?service=cut&from=2026-11-02')->json('days.0.slots');
        $this->assertNotContains('13:00', $slots);
        $this->assertNotContains('13:45', $slots); // the 15-minute gap after the booking
        $this->assertContains('14:30', $slots);
        $this->bookIt($page, ['email' => 'other@example.com'])->assertStatus(409)->assertJsonPath('taken', true);
        $this->assertSame(1, BuilderBooking::count());
    }

    public function test_bookings_are_checked(): void
    {
        $page = $this->page();
        $this->bookIt($page, ['time' => '09:00'])->assertStatus(409);            // inside the notice time
        $this->bookIt($page, ['time' => '13:10'])->assertStatus(409);            // not a start time
        $this->bookIt($page, ['date' => '2026-11-03', 'time' => '10:00'])->assertStatus(409); // day off
        $this->bookIt($page, ['email' => 'nope'])->assertStatus(422)->assertJsonPath('field', 'email');
        $this->bookIt($page, ['phone' => ''])->assertStatus(422)->assertJsonPath('field', 'phone');
        $this->bookIt($page, ['name' => ''])->assertStatus(422)->assertJsonPath('field', 'name');
        $this->bookIt($page, ['service' => 'x'])->assertStatus(422);
        $this->bookIt($page, ['date' => '2026-02-30'])->assertStatus(422);
        // the honeypot: a bot gets a quiet "ok" and nothing is stored
        $this->bookIt($page, ['_hp' => 'spam'])->assertOk();
        $this->assertSame(0, BuilderBooking::count());
    }

    public function test_one_visitor_can_only_book_a_few_times_an_hour(): void
    {
        Mail::fake();
        $page = $this->page();
        foreach ([['2026-11-02', '10:30'], ['2026-11-02', '13:00'], ['2026-11-02', '14:30'], ['2026-11-02', '16:00'], ['2026-11-07', '10:00']] as [$d, $t]) {
            $this->bookIt($page, ['date' => $d, 'time' => $t])->assertOk();
        }
        $this->bookIt($page, ['date' => '2026-11-09', 'time' => '10:30'])->assertStatus(429);
    }

    public function test_the_client_sees_and_cancels_their_booking(): void
    {
        Mail::fake();
        $page = $this->page();
        $this->bookIt($page)->assertOk();
        $b = BuilderBooking::sole();
        $this->get('/book/manage/'.$b->token)->assertOk()->assertSee('You’re booked')->assertSee('Haircut')->assertSee('Cancel my booking')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/book/manage/'.$b->token.'?ask=cancel')->assertSee('Yes, cancel it');
        $this->get('/book/manage/'.$b->token.'/calendar.ics')->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->assertSee('DTSTART:20261102T130000Z', false);
        $this->post('/book/manage/'.$b->token.'/cancel')->assertRedirect('/book/manage/'.$b->token.'?done=cancelled');
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame('client', $b->fresh()->cancelled_by);
        Mail::assertSent(BuilderBookingCancelled::class, fn ($m) => $m->hasTo('owner@example.com') && $m->recipient === 'owner');
        $this->get('/book/manage/'.$b->token.'?done=cancelled')->assertSee('Your booking is cancelled');
        // the time is free again
        $this->assertContains('13:00', $this->getJson('/book/'.$page->id.'/slots?service=cut&from=2026-11-02')->json('days.0.slots'));
        $this->get('/book/manage/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_the_owner_saves_pages_lists_and_cancels_bookings(): void
    {
        Mail::fake();
        [, $h] = $this->owner();
        $r = $this->withHeaders($h)->postJson('/builder/bookings', ['cfg' => $this->cfg()])->assertOk();
        $id = $r->json('page.id');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $id);
        $this->withHeaders($h)->postJson('/builder/bookings', ['cfg' => $this->cfg(['name' => ''])])->assertStatus(422);
        $this->withHeaders($h)->postJson('/builder/bookings', ['id' => $id, 'cfg' => $this->cfg(['name' => 'Glow Studio'])])->assertOk()->assertJsonPath('page.cfg.name', 'Glow Studio');
        $this->withHeaders($h)->getJson('/builder/bookings')->assertOk()->assertJsonPath('pages.0.id', $id);

        $page = BuilderBookingPage::find($id);
        $this->bookIt($page)->assertOk();
        $list = $this->withHeaders($h)->getJson('/builder/bookings/'.$id.'/list')->assertOk();
        $list->assertJsonPath('bookings.0.name', 'Priya Shah')->assertJsonPath('bookings.0.time', '13:00')->assertJsonPath('bookings.0.end', '13:45');
        $csv = $this->withHeaders($h)->get('/builder/bookings/'.$id.'/list?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Priya Shah', $csv);
        $this->assertStringContainsString('2026-11-02,13:00,13:45,Haircut', $csv);

        $bid = $list->json('bookings.0.id');
        $this->withHeaders($h)->postJson('/builder/bookings/'.$id.'/cancel/'.$bid)->assertOk();
        $this->assertSame('owner', BuilderBooking::find($bid)->cancelled_by);
        Mail::assertSent(BuilderBookingCancelled::class, fn ($m) => $m->hasTo('priya@example.com') && $m->recipient === 'client');

        // someone else can't see, change or delete it
        [, $other] = $this->owner('someone@example.com');
        $this->withHeaders($other)->getJson('/builder/bookings/'.$id.'/list')->assertNotFound();
        $this->withHeaders($other)->postJson('/builder/bookings', ['id' => $id, 'cfg' => $this->cfg()])->assertStatus(403);
        $this->withHeaders($other)->postJson('/builder/bookings/'.$id.'/delete')->assertNotFound();
        $this->flushHeaders()->postJson('/builder/bookings', ['cfg' => $this->cfg()])->assertStatus(401);

        $this->withHeaders($h)->postJson('/builder/bookings/'.$id.'/delete')->assertOk();
        $this->assertSame(0, BuilderBooking::count());
    }

    public function test_an_account_has_at_most_five_pages(): void
    {
        [, $h] = $this->owner();
        for ($i = 0; $i < BuilderBookingPage::MAX_PER_ACCOUNT; $i++) {
            $this->withHeaders($h)->postJson('/builder/bookings', ['cfg' => $this->cfg()])->assertOk();
        }
        $this->withHeaders($h)->postJson('/builder/bookings', ['cfg' => $this->cfg()])->assertStatus(422);
    }

    public function test_reminders_go_once_about_a_day_before(): void
    {
        Mail::fake();
        $page = $this->page();
        $b = $page->bookingsList()->create(['token' => str_repeat('r', 40), 'service_key' => 'cut', 'service' => 'Haircut', 'mins' => 45,
            'starts_at' => now()->addHours(20), 'ends_at' => now()->addHours(20)->addMinutes(45), 'name' => 'Sam', 'email' => 'sam@example.com']);
        $b->forceFill(['created_at' => now()->subDays(3)])->save();
        $late = $page->bookingsList()->create(['token' => str_repeat('s', 40), 'service_key' => 'cut', 'service' => 'Haircut', 'mins' => 45,
            'starts_at' => now()->addHours(5), 'ends_at' => now()->addHours(5)->addMinutes(45), 'name' => 'Ana', 'email' => 'ana@example.com']); // booked just now
        foreach ([$b, $late, $b] as $x) {
            BookingReminders::send($x->fresh());
        }
        Mail::assertSent(BuilderBookingReminder::class, 1);
        Mail::assertSent(BuilderBookingReminder::class, fn ($m) => $m->hasTo('sam@example.com'));
        $this->assertNotNull($b->fresh()->reminded_at);
    }

    public function test_the_public_page_can_sit_in_an_iframe_and_disabled_pages_are_gone(): void
    {
        $page = $this->page();
        $html = $this->get('/book/'.$page->id)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Glow Hair Studio')->assertSee('booking-cfg', false);
        $this->assertStringContainsString('frame-ancestors *', $html->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'none'", $html->headers->get('Content-Security-Policy'));
        $page->forceFill(['disabled_at' => now()])->save();
        $this->get('/book/'.$page->id)->assertNotFound();
        $this->getJson('/book/'.$page->id.'/slots?service=cut')->assertNotFound();
    }

    public function test_clock_changes_keep_wall_clock_times(): void
    {
        // the last Sunday of March: clocks go forward in London. A 09:00 booking is 08:00 UTC that day.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-03-25 08:00', 'Europe/London'));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::parse('2027-03-25 08:00', 'Europe/London'));
        $page = $this->page(['hours' => [[], [], [], [], [], [], [['09:00', '12:00']]], 'off' => []]);
        Mail::fake();
        $this->bookIt($page, ['date' => '2027-03-28', 'time' => '09:00'])->assertOk();
        $this->assertSame('2027-03-28 08:00', BuilderBooking::sole()->starts_at->utc()->format('Y-m-d H:i'));
    }

    public function test_the_builder_is_in_the_menu_sitemap_and_llms_file(): void
    {
        $this->get('/free-tools')->assertOk()->assertSee(route('booking-page-builder'), false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('booking-page-builder'), false)->assertSee(route('guide.booking'), false);
        $llms = file_get_contents(public_path('llms.txt'));
        $this->assertStringContainsString('https://owlvix.com/booking-page-builder', $llms);
        $this->assertStringContainsString('https://owlvix.com/how-to-take-bookings-online', $llms);
    }

    public function test_the_emails_render(): void
    {
        $page = $this->page();
        $b = $page->bookingsList()->create(['token' => str_repeat('e', 40), 'service_key' => 'cut', 'service' => 'Haircut', 'mins' => 45, 'price' => '£35',
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45), 'name' => 'Priya <b>Shah</b>', 'email' => 'priya@example.com', 'phone' => '+44 7700 900123', 'note' => "Line one\nLine two"]);
        $b->load('page');
        foreach ([new BuilderBookingConfirmed($b, 'owner@example.com'), new BuilderBookingReminder($b, 'owner@example.com'), new BuilderBookingNew($b),
            new BuilderBookingCancelled($b, 'client', 'owner@example.com'), new BuilderBookingCancelled($b, 'owner')] as $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Haircut', $html);
            $this->assertStringContainsString('Priya &lt;b&gt;Shah&lt;/b&gt;', $html); // escaped
        }
        $this->assertStringContainsString('/book/manage/'.$b->token, (new BuilderBookingConfirmed($b, 'owner@example.com'))->render());
        $this->assertCount(1, (new BuilderBookingConfirmed($b, 'owner@example.com'))->attachments());
    }
}
