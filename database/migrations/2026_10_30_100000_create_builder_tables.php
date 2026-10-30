<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Free landing page builder: accounts (email sign-in), their sign-in tokens, published sites and contact forms.
// Page HTML is stored on disk (storage/app/builder-sites/{site id}/{page}.html), not here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // One per signed-in device; only a sha256 hash of the token is kept
        Schema::create('builder_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('builder_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40)->unique();
            $table->string('name', 100);
            $table->json('pages');                              // [{slug, title}], in menu order
            $table->string('status', 10)->default('live');      // live, removed
            $table->string('removed_by', 10)->nullable();       // owner, admin
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('reported_at')->nullable();
            $table->unsignedInteger('reports')->default(0);
            $table->timestamps();
        });

        // Contact forms on builder pages post to /f/{id}; messages are emailed, not stored
        Schema::create('builder_forms', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->string('site_name', 100);
            $table->unsignedInteger('submissions')->default(0);
            $table->timestamp('last_submission_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_forms');
        Schema::dropIfExists('builder_sites');
        Schema::dropIfExists('builder_tokens');
        Schema::dropIfExists('builder_accounts');
    }
};
