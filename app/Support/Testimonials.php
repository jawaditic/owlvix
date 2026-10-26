<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Schema;

/** Reviews + Fiverr rating for the homepage, quote popup and confirmation email. */
class Testimonials
{
    private static ?array $data = null;

    /** Forget the copy kept for this request (tests start each case fresh). */
    public static function flush(): void
    {
        self::$data = null;
    }

    public static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        // Before the migration has run, show nothing rather than an error
        if (! Schema::hasTable('testimonials')) {
            return self::$data = ['profile_url' => '', 'rating' => '', 'count' => 0, 'five_star' => 0, 'reviews' => [], 'email' => []];
        }

        $reviews = Testimonial::published()->ordered()->get()->map(fn (Testimonial $t) => [
            'name' => $t->name,
            'code' => $t->country_code,
            'country' => $t->countryName(),
            'flag' => $t->flag(),
            'service' => $t->service,
            'text' => $t->text,
            'rating' => $t->rating,
            'in_email' => $t->in_email,
        ])->all();

        return self::$data = [
            'profile_url' => Setting::get('reviews_profile_url', 'https://www.fiverr.com/anjtec'),
            'rating' => Setting::get('reviews_rating', ''),
            'count' => (int) Setting::get('reviews_count', '0'),
            'five_star' => (int) Setting::get('reviews_five_star', '0'),
            'reviews' => $reviews,
            'email' => collect($reviews)->where('in_email', true)->take(3)->pluck('name')->all(),
        ];
    }
}
