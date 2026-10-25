<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Built-in visitor stats (Admin → Traffic). No IP addresses or cookies are stored:
// a visitor is a one-way hash that changes every day.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor', 16);              // daily hash, can't be turned back into an IP
            $table->string('path', 191);
            $table->string('country', 2)->nullable();    // ISO code, e.g. us
            $table->string('source', 40);                // Google, Direct, WhatsApp, ...
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 10);                // mobile, tablet, desktop
            $table->string('browser', 20)->nullable();
            $table->boolean('is_new')->default(true);    // first visit from this browser
            $table->timestamp('created_at')->index();
            $table->index(['visitor', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
