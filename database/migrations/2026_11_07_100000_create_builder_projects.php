<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Projects saved to a builder account: the page builders (landing, funnel, website, portfolio, link in bio, coming
// soon, event, menu) and the email builder keep their whole editable project here, like the app builder's apps.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_projects', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->foreignId('builder_account_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);           // which builder: App\Models\BuilderProject::KINDS
            $table->string('name', 80);
            $table->longText('data');             // the builder's project, as JSON
            $table->timestamps();
            $table->index(['builder_account_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_projects');
    }
};
