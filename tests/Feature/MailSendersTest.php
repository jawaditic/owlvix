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
            $code = file_get_contents($file);
            if (preg_match('/extends (Builder\w+)/', $code, $m)) { // a variant of another builder email keeps its sender
                $code = file_get_contents(app_path('Mail/'.$m[1].'.php'));
            }
            $this->assertStringContainsString("config('mail.senders.", $code, basename($file));
        }
    }

    public function test_abuse_address_is_published(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('abuse@owlvix.com');
        $this->assertStringContainsString('mailto:abuse@owlvix.com', file_get_contents(public_path('.well-known/security.txt')));
        foreach (['builder/report.blade.php', 'builder/report-app.blade.php'] as $view) {
            $this->assertStringContainsString("mail.senders.abuse", file_get_contents(resource_path('views/'.$view)));
        }
    }
}
