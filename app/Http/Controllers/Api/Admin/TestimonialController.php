<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Support\Reorder;
use Illuminate\Http\Request;

/** Reviews in the mobile app, plus the Fiverr rating summary. */
class TestimonialController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Testimonial::ordered()->get()->map->toApi(),
            'settings' => [
                'reviews_profile_url' => Setting::get('reviews_profile_url'),
                'reviews_rating' => Setting::get('reviews_rating'),
                'reviews_count' => Setting::get('reviews_count'),
                'reviews_five_star' => Setting::get('reviews_five_star'),
            ],
            'countries' => config('countries'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (Testimonial::max('sort_order') ?? 0) + 1;

        return response()->json(Testimonial::create($data)->toApi(), 201);
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validated($request));

        return response()->json($testimonial->toApi());
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return response()->json(['ok' => true]);
    }

    public function move(Testimonial $testimonial, string $direction)
    {
        Reorder::move(Testimonial::ordered()->get(), $testimonial, $direction);

        return $this->index();
    }

    public function toggle(Testimonial $testimonial, string $field)
    {
        abort_unless(in_array($field, ['is_published', 'in_email'], true), 404);
        $testimonial->forceFill([$field => ! $testimonial->$field])->save();

        return response()->json($testimonial->toApi());
    }

    public function settings(Request $request)
    {
        Setting::put($request->validate(Testimonial::SETTINGS_RULES, Testimonial::SETTINGS_MESSAGES));

        return $this->index();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(Testimonial::rules());
        $data['is_published'] = $request->boolean('is_published');
        $data['in_email'] = $request->boolean('in_email');

        return $data;
    }
}
