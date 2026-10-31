<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Online payments for apps (mobile app builder): the owner's own Stripe account, connected through Stripe Connect,
// once for test payments and once for real ones. Only Stripe's account ids are kept (acct_…), never keys.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_apps', function (Blueprint $table) {
            $table->text('stripe_test_account')->nullable(); // encrypted (cast)
            $table->text('stripe_live_account')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('builder_apps', function (Blueprint $table) {
            $table->dropColumn(['stripe_test_account', 'stripe_live_account']);
        });
    }
};
