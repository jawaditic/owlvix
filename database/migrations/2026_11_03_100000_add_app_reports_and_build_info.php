<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mobile app builder: apps can be reported and taken down (like builder sites), and each build remembers what went
// into it, so the builder can tell the owner when phones have an older version (App\Support\AppBuilder::buildInfo).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_apps', function (Blueprint $table) {
            $table->string('status', 16)->default('live');      // live, removed (by the admin)
            $table->timestamp('reported_at')->nullable();
            $table->unsignedInteger('reports')->default(0);
        });
        Schema::table('builder_app_builds', function (Blueprint $table) {
            $table->text('info')->nullable(); // JSON: what the build was made from (name, icon, splash, intro, app version)
        });
    }

    public function down(): void
    {
        Schema::table('builder_app_builds', fn (Blueprint $table) => $table->dropColumn('info'));
        Schema::table('builder_apps', fn (Blueprint $table) => $table->dropColumn(['status', 'reported_at', 'reports']));
    }
};
