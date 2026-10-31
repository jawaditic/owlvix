<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mobile app builder (/mobile-app-builder): a native app made of screens and blocks (and optionally pages of the
// owner's website); each build makes an Android APK and AAB on GitHub Actions (.github/workflows/build-android-app.yml, app-shell/).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_apps', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('package', 100)->unique(); // Android application id: one app per id across all accounts
            $table->string('url', 500)->nullable();   // the owner's website, needed only for "web page" tabs
            $table->text('settings');                 // JSON: colours, version, icon design… (App\Support\AppBuilder::clean)
            $table->mediumText('content')->nullable(); // JSON: tabs with their blocks (App\Support\AppContent), published as a static file
            $table->string('verify_code', 32);        // for the <meta name="owlvix-app"> check on other websites
            $table->string('verified_host')->nullable();
            $table->unsignedInteger('version_code')->default(0); // last build's number; Google Play needs it to go up
            $table->text('keystore')->nullable();      // encrypted upload key, made by the first build
            $table->text('keystore_password');         // encrypted
            $table->timestamps();
        });

        Schema::create('builder_app_builds', function (Blueprint $table) {
            $table->id();
            $table->string('builder_app_id', 16);
            $table->foreign('builder_app_id')->references('id')->on('builder_apps')->cascadeOnDelete();
            $table->string('status', 16)->default('queued'); // queued, building, done, failed
            $table->unsignedInteger('version_code');
            $table->string('version_name', 20);
            $table->string('token_hash', 64)->nullable();     // the build server's pass for this one build
            $table->text('job')->nullable();                   // JSON settings sent to the build server
            $table->text('files')->nullable();                 // JSON: apk/aab => [path (in the app's public folder), bytes]
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['builder_app_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_app_builds');
        Schema::dropIfExists('builder_apps');
    }
};
