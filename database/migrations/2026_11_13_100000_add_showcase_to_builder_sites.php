<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Made with Owlvix": owners can ask for their published site to be featured on /examples; the admin approves it.
// Also kept on each publish: what kind of site it is and its main colour (for the showcase card).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_sites', function (Blueprint $table) {
            $table->string('kind', 12)->nullable()->after('name');      // store, menu, website, event…
            $table->string('color', 7)->nullable()->after('kind');      // #rrggbb, from the published page
            $table->string('showcase', 8)->nullable()->after('color');  // asked, yes, no
            $table->timestamp('showcase_at')->nullable()->after('showcase');
            $table->index('showcase');
        });
    }

    public function down(): void
    {
        Schema::table('builder_sites', function (Blueprint $table) {
            $table->dropIndex(['showcase']);
            $table->dropColumn(['kind', 'color', 'showcase', 'showcase_at']);
        });
    }
};
