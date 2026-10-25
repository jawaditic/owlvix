<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Portfolio projects, managed from the admin dashboard (Admin → Portfolio).
// Starts with the projects from anjtec.net/portfolio.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('category', 20);          // website, webapp, mobile, shopify, ai
            $table->string('summary', 300)->nullable();
            $table->string('image')->nullable();      // path under public/, e.g. images/portfolio/abyat.jpg
            $table->string('url')->nullable();        // live website
            $table->string('ios_url')->nullable();    // App Store
            $table->string('android_url')->nullable(); // Google Play
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        $projects = [
            ['The Dubai Mall', 'mobile', 'images/portfolio/the-dubai-mall.jpg', null, 'https://apps.apple.com/ae/app/the-dubai-mall/id430795858', 'https://play.google.com/store/apps/details?id=com.thedubaimall&hl=en&gl=US'],
            ['Spigen', 'shopify', 'images/portfolio/spigen.jpg', 'https://www.spigen.com/', null, null],
            ['Milly', 'shopify', 'images/portfolio/milly.jpg', 'https://www.milly.com/', null, null],
            ['Baritastic - Bariatric Tracker', 'mobile', 'images/portfolio/baritastic-bariatric-tracker.jpg', null, 'https://apps.apple.com/us/app/baritastic-bariatric-tracker/id899131093', 'https://play.google.com/store/apps/details?id=com.baritastic.view&hl=en&gl=US'],
            ['Cyrill', 'shopify', 'images/portfolio/cyrill.jpg', 'https://www.cyrill.com/', null, null],
            ['Abyat', 'website', 'images/portfolio/abyat.jpg', 'https://demo.anjtec.net/abyat', null, null],
            ['Doer Life', 'website', 'images/portfolio/doer-life.jpg', 'https://doerlife.com/', null, null],
            ['Nomads', 'website', 'images/portfolio/nomads.jpg', 'https://findnomads.com/', null, null],
            ['Followly', 'website', 'images/portfolio/followly.jpg', 'https://followly.me/', null, null],
            ['Bayan Dental', 'website', 'images/portfolio/bayan-dental.jpg', 'https://bayandental.com/', null, null],
            ['Clean Remedies', 'shopify', 'images/portfolio/clean-remedies.jpg', 'https://cleanremedies.com/', null, null],
            ['Ashako Classifieds', 'mobile', 'images/portfolio/ashako-classifieds.jpg', null, 'https://apps.apple.com/tt/app/ashako/id1545763973', 'https://play.google.com/store/apps/details?id=com.uf.ashako&hl=es_MX'],
            ['Consulting Centrale', 'mobile', 'images/portfolio/consulting-centrale.jpg', null, 'https://apps.apple.com/tt/app/consultingcentrale/id1503911574?ign-mpt=uo%3D2', 'https://play.google.com/store/apps/details?id=com.uf.consultingcentrale&hl=es_MX'],
            ['Snaprint', 'website', 'images/portfolio/snaprint.jpg', 'https://snaprint.net/', null, null],
            ['PKG', 'website', 'images/portfolio/pkg.jpg', 'https://pkgkw.com/', null, null],
            ['Tailored by Alia', 'website', 'images/portfolio/tailored-by-alia.jpg', 'https://tailoredbyalia.com/', null, null],
            ['Celeb Glory', 'website', 'images/portfolio/celeb-glory.jpg', 'http://celebglory.com/', null, null],
            ['House Living', 'website', 'images/portfolio/house-living.jpg', 'https://demo.anjtec.net/house.living/', null, null],
            ['Doughnation', 'website', 'images/portfolio/doughnation.jpg', 'https://demo.anjtec.net/doughnation/', null, null],
            ['Diamond To Nation', 'website', 'images/portfolio/diamond-to-nation.jpg', 'https://demo.anjtec.net/d2n/', null, null],
            ['Bell', 'website', 'images/portfolio/bell.jpg', 'https://demo.anjtec.net/bell/', null, null],
            ['Sit Amet', 'website', 'images/portfolio/sit-amet.jpg', 'https://demo.anjtec.net/sitamet-v2', null, null],
            ['Online Quiz App', 'website', 'images/portfolio/online-quiz-app.jpg', 'https://demo.anjtec.net/online-quiz-app/', null, null],
            ['PrimeSales', 'website', 'images/portfolio/primesales.jpg', null, null, null],
            ['Rebel Sport', 'website', 'images/portfolio/rebel-sport.jpg', null, null, null],
            ['Abieta', 'website', 'images/portfolio/abieta.jpg', null, null, null],
            ['Afro Core', 'website', 'images/portfolio/afro-core.jpg', null, null, null],
            ['Printbuddy', 'website', 'images/portfolio/printbuddy.jpg', null, null, null],
            ['Quran Educator', 'website', 'images/portfolio/quran-educator.jpg', null, null, null],
            ['Trustgated', 'website', 'images/portfolio/trustgated.jpg', null, null, null],
            ['Ayooka', 'website', 'images/portfolio/ayooka.jpg', null, null, null],
            ['Royal Exchange', 'website', 'images/portfolio/royal-exchange.jpg', null, null, null],
            ['Bill Buss', 'website', 'images/portfolio/bill-buss.jpg', null, null, null],
        ];
        foreach ($projects as $i => [$title, $category, $image, $url, $ios, $android]) {
            DB::table('projects')->insert([
                'title' => $title, 'category' => $category, 'image' => $image,
                'url' => $url, 'ios_url' => $ios, 'android_url' => $android,
                'sort_order' => $i + 1, 'is_published' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
