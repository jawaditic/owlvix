<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Funnel builder: lead forms (leads are stored for the funnel owner), email-tool integrations on a form,
// and per-day view/lead counts for each funnel step and A/B variant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_forms', function (Blueprint $table) {
            $table->string('kind', 10)->default('contact');   // contact (emailed only), leads (stored too)
            $table->text('integrations')->nullable();          // encrypted JSON: mailchimp, brevo, webhook
            $table->string('ref', 20)->nullable();             // the builder project it belongs to (two projects with the same name stay apart)
        });

        Schema::create('builder_leads', function (Blueprint $table) {
            $table->id();
            $table->string('builder_form_id', 16);
            $table->foreign('builder_form_id')->references('id')->on('builder_forms')->cascadeOnDelete();
            $table->foreignId('builder_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('step', 40)->nullable();
            $table->char('variant', 1)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['builder_form_id', 'created_at']);
        });

        Schema::create('builder_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_site_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40);
            $table->char('variant', 1)->default('a');
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->unique(['builder_site_id', 'step', 'variant', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_stats');
        Schema::dropIfExists('builder_leads');
        Schema::table('builder_forms', function (Blueprint $table) {
            $table->dropColumn(['kind', 'integrations', 'ref']);
        });
    }
};
