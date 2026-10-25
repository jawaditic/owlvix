<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Enquiries;
use App\Support\RichText;
use App\Support\Testimonials;
use Illuminate\Http\Request;

/** Public API for the customer side of the mobile app. */
class SiteController extends Controller
{
    /** Portfolio, reviews and the quote form options in one call. */
    public function show()
    {
        $t = Testimonials::data();

        return response()->json([
            'projects' => Project::published()->ordered()->get()->map->toApi(),
            'categories' => Project::CATEGORIES,
            'reviews' => [
                'profile_url' => $t['profile_url'],
                'rating' => $t['rating'],
                'count' => $t['count'],
                'five_star' => $t['five_star'],
                'items' => collect($t['reviews'])->map(fn ($r) => collect($r)->except('in_email'))->values(),
            ],
            'form' => [
                'services' => Enquiries::SERVICES,
                'budgets' => Enquiries::BUDGETS,
            ],
        ]);
    }

    /** The "Get a free quote" form in the app. Same rules, emails and alerts as the website. */
    public function contact(Request $request)
    {
        // Hidden field the app always leaves empty: bots that post here directly may fill it
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true, 'first_name' => '']);
        }

        // The app sends plain text; keep its line breaks when cleaning it like the website's editor
        $text = (string) $request->input('message');
        $html = collect(preg_split('/\R{2,}/', trim($text)))
            ->map(fn ($p) => '<p>'.nl2br(e($p), false).'</p>')->implode('');
        $request->merge(['message' => RichText::clean($html), 'source' => 'app']);

        $data = $request->validate(Enquiries::rules());
        $enquiry = Enquiries::create($data, $request->ip());

        return response()->json(['ok' => true, 'id' => $enquiry->id, 'first_name' => $enquiry->firstName()], 201);
    }
}
