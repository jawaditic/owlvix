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
        // Links in emails and to Stripe are built during public requests (shop checkouts): always from APP_URL, never
        // from the Host header someone sent
        if ($this->app->environment('production') && config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        // Portfolio, reviews and the rating summary are shown on the homepage: tell Bing when they change
        $changed = fn () => \App\Support\IndexNow::homepageChanged();
        foreach ([\App\Models\Project::class, \App\Models\Testimonial::class, \App\Models\Setting::class] as $model) {
            $model::saved($changed);
            $model::deleted($changed);
        }
    }
}
