<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin → Growth: daily counts of how people reach each free builder (a guide, a gallery design, search…) and how far
// they get (opened, used a design, saved, published, downloaded). Counts only — nothing about who the visitor is.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_steps', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('builder', 40);  // route name, e.g. store-builder
            $table->string('event', 12);    // open, design, save, publish, export
            $table->string('source', 80);   // guide:guide.cv, design:bakery, examples, search, direct…
            $table->unsignedInteger('hits')->default(0);
            $table->unique(['day', 'builder', 'event', 'source']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_steps');
    }
};
