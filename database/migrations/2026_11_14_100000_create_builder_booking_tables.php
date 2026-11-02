<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Booking page builder: an owner's booking pages (/book/{id}) and the bookings clients make on them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_booking_pages', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->text('settings');            // services, hours, rules — App\Support\BookingSchema
            $table->unsignedInteger('bookings')->default(0);
            $table->timestamp('disabled_at')->nullable(); // turned off by the admin
            $table->timestamps();
        });
        Schema::create('builder_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('builder_booking_page_id', 16);
            $table->foreign('builder_booking_page_id')->references('id')->on('builder_booking_pages')->cascadeOnDelete();
            $table->string('token', 40)->unique();   // the client's manage / cancel link
            $table->string('service_key', 24);
            $table->string('service', 80);
            $table->unsignedSmallInteger('mins');
            $table->string('price', 30)->nullable();
            $table->dateTime('starts_at');          // UTC
            $table->dateTime('ends_at');            // UTC
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 40)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 12)->default('confirmed'); // confirmed | cancelled
            $table->string('cancelled_by', 8)->nullable();      // client | owner
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['builder_booking_page_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_bookings');
        Schema::dropIfExists('builder_booking_pages');
    }
};
