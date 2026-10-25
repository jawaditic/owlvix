<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Reviews: client reviews shown on the site, plus the Fiverr rating summary. */
class TestimonialController extends Controller
{
    public function index()
    {
        return view('admin.testimonials.index', [
            'testimonials' => Testimonial::ordered()->get(),
            'settings' => [
                'reviews_profile_url' => Setting::get('reviews_profile_url'),
                'reviews_rating' => Setting::get('reviews_rating'),
                'reviews_count' => Setting::get('reviews_count'),
                'reviews_five_star' => Setting::get('reviews_five_star'),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.testimonials.form', ['testimonial' => new Testimonial(['rating' => 5, 'is_published' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (Testimonial::max('sort_order') ?? 0) + 1;
        Testimonial::create($data);

        return redirect()->route('admin.testimonials.index')->with('status', 'Review added.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.form', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validated($request));

        return redirect()->route('admin.testimonials.index')->with('status', 'Review saved.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')->with('status', 'Review deleted.');
    }

    /** Move a review one place up or down in the list. */
    public function move(Testimonial $testimonial, string $direction)
    {
        $all = Testimonial::ordered()->get()->values();
        $i = $all->search(fn ($t) => $t->is($testimonial));
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $all->count()) {
            [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
            $all->each(fn ($t, $n) => $t->forceFill(['sort_order' => $n + 1])->save());
        }

        return back();
    }

    /** Quick on/off switches from the list: published, in_email */
    public function toggle(Testimonial $testimonial, string $field)
    {
        abort_unless(in_array($field, ['is_published', 'in_email'], true), 404);
        $testimonial->forceFill([$field => ! $testimonial->$field])->save();

        return back();
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'reviews_profile_url' => ['required', 'url', 'max:255'],
            'reviews_rating' => ['required', 'numeric', 'between:0,5'],
            'reviews_count' => ['required', 'integer', 'min:0'],
            'reviews_five_star' => ['required', 'integer', 'min:0', 'lte:reviews_count'],
        ], [
            'reviews_five_star.lte' => 'Five-star reviews can\'t be more than the total number of reviews.',
        ]);
        Setting::put($data);

        return back()->with('status', 'Rating summary saved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'country_code' => ['nullable', Rule::in(array_keys(config('countries')))],
            'service' => ['nullable', 'string', 'max:60'],
            'text' => ['required', 'string', 'min:5', 'max:1000'],
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['in_email'] = $request->boolean('in_email');

        return $data;
    }
}
