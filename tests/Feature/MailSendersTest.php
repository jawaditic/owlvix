<?php

namespace Tests\Feature;

use App\Mail\BuilderShopSignIn;
use App\Mail\BuilderSignInCode;
use App\Models\BuilderStore;
use Tests\TestCase;

/** Builder emails go out from noreply@, shop customer emails from orders@ with the shop's name. */
class MailSendersTest extends TestCase
{
    public function test_builder_codes_come_from_noreply(): void
    {
        $mail = new BuilderSignInCode('123456');
        $this->assertTrue($mail->hasFrom('noreply@owlvix.com', 'Owlvix'));
    }

    public function test_shop_emails_come_from_orders_with_the_shop_name(): void
    {
        $mail = new BuilderShopSignIn(new BuilderStore(['name' => 'Rosa Bakery']), '654321');
        $this->assertTrue($mail->hasFrom('orders@owlvix.com', 'Rosa Bakery via Owlvix'));
    }

    public function test_every_builder_email_sets_its_own_sender(): void
    {
        foreach (glob(app_path('Mail/Builder*.php')) as $file) {
            $this->assertStringContainsString("config('mail.senders.", file_get_contents($file), basename($file));
        }
    }
}
