<?php

namespace Tests\Feature;

use App\Support\Phone;
use Tests\TestCase;

/** The flag + calling code picker beside phone boxes: one list of codes everywhere, and numbers saved in full. */
class PhoneFieldTest extends TestCase
{
    public function test_the_calling_code_lists_match(): void
    {
        $engine = file_get_contents(public_path('js/builder-engine.js'));
        $field = file_get_contents(public_path('js/phone-field.js'));
        $this->assertStringContainsString("const DIAL = Object.fromEntries('".Phone::DIAL_LIST."'", $engine);
        $this->assertStringContainsString("const DIAL = Object.fromEntries('".Phone::DIAL_LIST."'", $field);
        $this->assertSame('+44', Phone::dial()['GB']);
        $this->assertSame('+92', Phone::dial()['PK']);
        // every country a shipping zone can name has a calling code
        preg_match("/const COUNTRIES = '([^']*)'/", $engine, $m);
        $this->assertSame([], array_values(array_diff(explode(' ', $m[1]), array_keys(Phone::dial()))));
    }

    public function test_numbers_are_saved_with_their_code(): void
    {
        $this->assertSame('+44 7911 123456', Phone::combine(' 07911 123456 ', '+44'));
        $this->assertSame('+1 555 0100', Phone::combine('+1 555 0100', '+44')); // typed with its own code
        $this->assertSame('555 0100', Phone::combine('555 0100', 'evil')); // no valid code: as typed
        $this->assertNull(Phone::combine('  ', '+44'));
    }

    public function test_owlvix_pages_load_the_picker(): void
    {
        $this->get(route('email-signature'))->assertOk()->assertSee('js/phone-field', false);
    }
}
