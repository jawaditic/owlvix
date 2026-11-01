<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Online store builder: refunds from the Orders tab (card orders through Stripe; cash and bank refunds are recorded).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded')->default(0)->after('total'); // smallest unit, all refunds together
            $table->timestamp('refunded_at')->nullable()->after('refunded');
        });
    }

    public function down(): void
    {
        Schema::table('builder_orders', function (Blueprint $table) {
            $table->dropColumn(['refunded', 'refunded_at']);
        });
    }
};
