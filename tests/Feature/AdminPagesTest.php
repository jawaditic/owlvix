<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The website admin pages load (they share their logic with the mobile API). */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_load(): void
    {
        $m = ContactMessage::create(['name' => 'App User', 'email' => 'a@example.com', 'message' => '<p>Hello there friend</p>', 'source' => 'app']);
        $this->withSession(['is_admin' => true]);

        foreach (['/admin', '/admin/traffic', '/admin/messages', '/admin/messages?source=app', '/admin/portfolio', '/admin/reviews'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/admin/messages/{$m->id}")->assertOk()->assertSee('Mobile app');
    }
}
