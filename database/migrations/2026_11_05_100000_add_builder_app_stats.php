<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mobile app builder: anonymous daily totals for each app (installs, phones that used it, opens, page views). Each
// phone sends one small summary a day (AppStatsController); no device IDs or personal data are stored.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_app_stats', function (Blueprint $table) {
            $table->id();
            $table->string('builder_app_id', 16);
            $table->foreign('builder_app_id')->references('id')->on('builder_apps')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('installs')->default(0); // first use on a phone
            $table->unsignedInteger('active')->default(0);   // phones that opened the app that day
            $table->unsignedInteger('opens')->default(0);
            $table->text('pages')->nullable();                 // JSON: page index => views
            $table->unique(['builder_app_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_app_stats');
    }
};
