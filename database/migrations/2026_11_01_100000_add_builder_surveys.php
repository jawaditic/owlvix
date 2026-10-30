<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Form builder (/form-builder): a form of kind "survey" carries its own fields (schema), is shown at /form/{id},
// and every answer is stored for the owner (builder_responses).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_forms', function (Blueprint $table) {
            $table->text('schema')->nullable(); // JSON: title, intro, fields, button, thanks, colour
        });

        Schema::create('builder_responses', function (Blueprint $table) {
            $table->id();
            $table->string('builder_form_id', 16);
            $table->foreign('builder_form_id')->references('id')->on('builder_forms')->cascadeOnDelete();
            $table->text('answers'); // JSON: field id => answer
            $table->timestamp('created_at')->nullable();
            $table->index(['builder_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_responses');
        Schema::table('builder_forms', function (Blueprint $table) {
            $table->dropColumn('schema');
        });
    }
};
