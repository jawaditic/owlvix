<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Online store builder, part 2: stock counts (kept here, so orders lower them even on published pages), discount
// codes, and on each order the discount, tax, shipping country, tracking number and whether it took stock.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_stores', function (Blueprint $table) {
            $table->text('stock')->nullable()->after('settings'); // JSON: {"product" or "product:variant": count} for tracked items
        });

        Schema::table('builder_orders', function (Blueprint $table) {
            $table->string('discount_code', 40)->nullable()->after('subtotal');
            $table->unsignedBigInteger('discount')->default(0)->after('discount_code');
            $table->unsignedBigInteger('tax')->default(0)->after('delivery_fee');
            $table->boolean('tax_included')->default(false)->after('tax');
            $table->string('country', 2)->nullable()->after('address');
            $table->string('tracking', 500)->nullable()->after('status');
            $table->timestamp('shipped_at')->nullable()->after('tracking');
            $table->boolean('stock_taken')->default(false)->after('items'); // stock and the code's use are given back if it's cancelled
            $table->timestamp('hold_until')->nullable()->after('stock_taken'); // unpaid card orders keep their stock until then
        });

        Schema::create('builder_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('builder_store_id', 16);
            $table->foreign('builder_store_id')->references('id')->on('builder_stores')->cascadeOnDelete();
            $table->string('code', 40);                         // upper case, e.g. SAVE10
            $table->string('kind', 10);                         // percent, amount, shipping
            $table->unsignedBigInteger('value')->default(0);    // percent (1–100) or an amount in the smallest unit
            $table->unsignedBigInteger('min_total')->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['builder_store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_discounts');
        Schema::table('builder_orders', function (Blueprint $table) {
            $table->dropColumn(['discount_code', 'discount', 'tax', 'tax_included', 'country', 'tracking', 'shipped_at', 'stock_taken', 'hold_until']);
        });
        Schema::table('builder_stores', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
