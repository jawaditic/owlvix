<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Answers to the short "before you go" survey shown when a visitor is about to leave without
// asking for a quote (Admin → Feedback). No IP address is stored.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->string('reason', 20);                  // see SurveyResponse::REASONS
            $table->text('comment')->nullable();           // "Other" text or extra words
            $table->string('email', 150)->nullable();      // only if they want a follow-up
            $table->string('page', 191)->nullable();       // where they were, e.g. "/#pricing"
            $table->string('device', 10)->nullable();      // mobile, tablet, desktop
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
