<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Logins from the Owlvix mobile app. Only a hash of each token is stored.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('device', 120)->nullable();      // e.g. "iPhone 15"
            $table->string('push_token', 255)->nullable();  // Expo push token for new-enquiry alerts
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_tokens');
    }
};
