<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Portfolio, reviews and the rating summary are shown on the homepage: tell Bing when they change
        $changed = fn () => \App\Support\IndexNow::homepageChanged();
        foreach ([\App\Models\Project::class, \App\Models\Testimonial::class, \App\Models\Setting::class] as $model) {
            $model::saved($changed);
            $model::deleted($changed);
        }
    }
}
