<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mobile app builder: push notifications owners send to everyone with their app (App\Support\Firebase).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_app_pushes', function (Blueprint $table) {
            $table->id();
            $table->string('builder_app_id', 16);
            $table->foreign('builder_app_id')->references('id')->on('builder_apps')->cascadeOnDelete();
            $table->string('title', 65);
            $table->string('body', 240);
            $table->unsignedTinyInteger('tab')->nullable(); // page opened when the notification is tapped
            $table->string('status', 16);                   // sent, failed
            $table->string('error', 300)->nullable();
            $table->timestamps();
            $table->index(['builder_app_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_app_pushes');
    }
};
