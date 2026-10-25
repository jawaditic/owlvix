<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Client reviews and site settings, managed from the admin dashboard.
// Starts with the reviews that were on the site before (from the Fiverr profile).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('country_code', 2)->nullable();
            $table->string('service', 60)->nullable();
            $table->text('text');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('in_email')->default(false);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        $reviews = [
            ['chichiemeson145', 'us', 'Web app', 'This is one of the best things that has been done for my business. The preciseness and level of detail is 2nd to none.', false],
            ['businessonli', 'gb', 'Cross-platform app', 'Very happy to work with Jawad, he listened to my concerns and always responded on time and made quite a few revisions when needed. 100% recommend!', true],
            ['sukhdev79', 'ca', 'Web app', 'Honestly, working with Anjtec has been one of the best experiences I\'ve had working with a Fiverr freelancer. He is clear, communicative, and delivers excellent results in a very timely manner. Looking forward to working with him again!', true],
            ['vasecaleb', 'us', 'Web app', 'Very good at what he does! He was professional, delivered high quality work.', false],
            ['jackmavel', 'za', 'Cross-platform app', 'His code expertise and impeccable attention to detail truly exceeded my expectations. Working with him was a breeze — always polite, timely, and consistently going above and beyond. I highly recommend him!', false],
            ['olegzhuravlov', 'ch', 'Web app', 'We were working together on the creation of my webapp. He was a very big help for me. The design solutions anjtec provided were awesome. Thank you very much :)', false],
            ['nealgee', 'id', 'Web app', 'Jawad is good to work with. He is patient, hard working, reliable and gives great value.', false],
            ['berrialaeddine', 'fr', 'Website', 'I have nothing to say but perfect. Perfect work, perfect communication, just pure professionalism. I\'m already planning to do another task with him.', false],
            ['vivekjha541', 'in', 'Web app', 'My project was already late but this guy delivered my project in 24 hours and as I described. Perfect work.', false],
        ];
        foreach ($reviews as $i => [$name, $code, $service, $text, $inEmail]) {
            DB::table('testimonials')->insert([
                'name' => $name, 'country_code' => $code, 'service' => $service, 'text' => $text,
                'rating' => 5, 'sort_order' => $i + 1, 'is_published' => true, 'in_email' => $inEmail,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            'reviews_profile_url' => 'https://www.fiverr.com/anjtec',
            'reviews_rating' => '4.5',
            'reviews_count' => '217',
            'reviews_five_star' => '207',
        ] as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('testimonials');
    }
};