<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Online store builder, part 3: product reviews from buyers, gift cards, files sold as digital downloads, and on each
// order the gift card spent, the Stripe coupon (reused when "Pay now" is pressed again), downloads and the reminder.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('builder_store_id', 16);
            $table->foreign('builder_store_id')->references('id')->on('builder_stores')->cascadeOnDelete();
            $table->foreignId('builder_order_id')->constrained('builder_orders')->cascadeOnDelete(); // only buyers review
            $table->string('product_id', 24);
            $table->string('name', 60);
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('text')->nullable();
            $table->boolean('hidden')->default(false); // hidden by the owner
            $table->timestamps();
            $table->unique(['builder_order_id', 'product_id']);
            $table->index(['builder_store_id', 'hidden']);
        });

        Schema::create('builder_gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('builder_store_id', 16);
            $table->foreign('builder_store_id')->references('id')->on('builder_stores')->cascadeOnDelete();
            $table->string('code', 24);                       // GIFT-XXXX-XXXX
            $table->string('currency', 3);
            $table->unsignedBigInteger('initial');
            $table->unsignedBigInteger('balance');
            $table->foreignId('builder_order_id')->nullable()->constrained('builder_orders')->nullOnDelete(); // bought with
            $table->string('email', 150)->nullable();
            $table->timestamps();
            $table->unique(['builder_store_id', 'code']);
        });

        Schema::create('builder_shop_files', function (Blueprint $table) {
            $table->string('id', 24)->primary();
            $table->string('builder_store_id', 16);
            $table->foreign('builder_store_id')->references('id')->on('builder_stores')->cascadeOnDelete();
            $table->string('name', 150);  // as the customer will download it
            $table->unsignedBigInteger('size');
            $table->string('mime', 100);
            $table->timestamps();
        });

        Schema::table('builder_orders', function (Blueprint $table) {
            $table->string('gift_code', 24)->nullable()->after('discount');
            $table->unsignedBigInteger('gift_amount')->default(0)->after('gift_code');
            $table->string('stripe_coupon', 100)->nullable()->after('stripe_session');
            $table->unsignedInteger('downloads')->default(0)->after('items');
            $table->timestamp('reminded_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('builder_orders', function (Blueprint $table) {
            $table->dropColumn(['gift_code', 'gift_amount', 'stripe_coupon', 'downloads', 'reminded_at']);
        });
        Schema::dropIfExists('builder_shop_files');
        Schema::dropIfExists('builder_gift_cards');
        Schema::dropIfExists('builder_reviews');
    }
};
