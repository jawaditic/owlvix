<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Online store builder (/online-store-builder): each shop's products and checkout settings (copied from the editor
// when the owner publishes or downloads), and the orders customers place. Prices are kept here, so checkout never
// trusts the price a page sends. Money is stored in the currency's smallest unit (cents).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_stores', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->string('ref', 20)->nullable();      // the editor project it belongs to
            $table->string('name', 100);
            $table->string('currency', 3)->default('usd');
            $table->mediumText('catalog')->nullable();   // JSON: products [{id, name, price, options, sold}]
            $table->text('settings')->nullable();        // JSON: delivery, pickup, payment ways…
            $table->text('stripe_test_account')->nullable(); // encrypted acct_… (Stripe Connect)
            $table->text('stripe_live_account')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamp('disabled_at')->nullable(); // switched off by Owlvix
            $table->timestamps();
            $table->index(['builder_account_id', 'ref']);
        });

        Schema::create('builder_orders', function (Blueprint $table) {
            $table->id();
            $table->string('builder_store_id', 16);
            $table->foreign('builder_store_id')->references('id')->on('builder_stores')->cascadeOnDelete();
            $table->unsignedInteger('number');            // 1, 2, 3… per store (shown as #1001)
            $table->string('token', 40)->unique();         // the customer's private receipt link
            $table->string('status', 20)->default('new');  // unpaid (card, not paid yet), new, shipped, done, cancelled
            $table->string('pay', 10);                     // card, cod, bank
            $table->timestamp('paid_at')->nullable();
            $table->string('delivery', 10);                // delivery, pickup
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->text('items');                         // JSON: [{id, name, option, price, qty}]
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('stripe_mode', 4)->nullable();
            $table->string('stripe_session', 255)->nullable();
            $table->string('back', 2000)->nullable();      // the shop page the order came from
            $table->timestamps();
            $table->unique(['builder_store_id', 'number']);
            $table->index(['builder_store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_orders');
        Schema::dropIfExists('builder_stores');
    }
};
