<?php

namespace Tests\Feature;

use App\Http\Controllers\BuilderController;
use App\Models\BuilderDiscount;
use App\Models\BuilderStore;
use App\Support\BuilderHtml;
use App\Support\Shop;
use App\Support\Stats\BuilderStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fixes from the builders audit (2026-11-02), so they stay fixed. */
class BuilderAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $body): string
    {
        return '<!DOCTYPE html><html><head><title>x</title></head><body>'.$body.'</body></html>';
    }

    public function test_published_html_checks_see_attributes_split_by_a_slash(): void
    {
        // browsers read <tag/attr=…> as an attribute; the checks must too
        $this->assertNotNull(BuilderHtml::problem($this->page('<meta/http-equiv="refresh" content="0;url=https://evil.example">'), []));
        $this->assertNotNull(BuilderHtml::problem($this->page('<img/onerror=alert(1) src=x>'), []));
        $this->assertNotNull(BuilderHtml::problem($this->page('<svg/onload=alert(1)>'), []));
        $this->assertNotNull(BuilderHtml::problem($this->page('<a/href="javascript:alert(1)">x</a>'), []));
        $this->assertNull(BuilderHtml::problem($this->page('<p>Visit https://x.com/online today</p><a href="https://x.com/a/b">ok</a>'), []));
    }

    public function test_rsvp_forms_can_be_published(): void
    {
        $id = str_repeat('a', 16);
        $form = '<form method="post" action="'.rtrim(config('app.url'), '/').'/f/'.$id.'"><input name="rname"><input name="remail" type="email">'
            .'<input name="rcome" type="radio" value="yes"><input name="rguests" type="number"><select name="rmeal"></select><textarea name="rmsg"></textarea>'
            .'<input name="_hp"><input type="hidden" name="_back"></form>';
        $this->assertNull(BuilderHtml::problem($this->page($form), [$id]));
    }

    public function test_gift_cards_are_sold_at_face_value(): void
    {
        $store = new BuilderStore(['currency' => 'usd']);
        $store->settings = ['fee' => 500, 'free_over' => 0, 'tax_rate' => 20, 'tax_incl' => false];
        $lines = [['price' => 10000, 'qty' => 1, 'kind' => 'gift'], ['price' => 2000, 'qty' => 1, 'kind' => 'item']];
        $half = new BuilderDiscount(['kind' => 'percent', 'value' => 50]);
        $t = Shop::totals($store, $lines, 'delivery', null, $half);
        $this->assertSame(1000, $t['discount']);            // 50% of the 20.00 of goods, not of the 100.00 card
        $this->assertSame(200, $t['tax']);                  // 20% of the goods after the discount (10.00); none on the card
        $this->assertSame(10000 + 1000 + 500 + 200, $t['total']); // card at face value + goods + delivery + tax
    }

    public function test_made_up_design_names_dont_add_growth_rows(): void
    {
        $this->assertSame('design:bakery', BuilderStats::source('design:bakery'));
        $this->assertSame('examples', BuilderStats::source('design:zzzmadeup'));
    }

    public function test_csv_keeps_phone_numbers_readable_but_blocks_formulas(): void
    {
        $this->assertSame('+44 7911 123456', BuilderController::csvCell('+44 7911 123456'));
        $this->assertSame("'=1+1", BuilderController::csvCell('=1+1'));
        $this->assertSame("'+SUM(A1)", BuilderController::csvCell('+SUM(A1)'));
    }
}
