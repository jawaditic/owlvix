<?php

namespace App\Http\Controllers;

use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Saves an answer to the exit survey (sent from site.js, answers in JSON). */
class SurveyController extends Controller
{
    public function store(Request $request)
    {
        // Hidden "website" field: real visitors leave it empty, simple bots fill it in.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(SurveyResponse::REASONS))],
            'comment' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('reason') === 'other')],
            'email' => ['nullable', 'email', 'max:150'],
            'page' => ['nullable', 'string', 'max:191'],
        ], [
            'comment.required' => 'Please tell us a few words.',
            'email.email' => 'Please enter a valid email, or leave it empty.',
        ]);

        $ua = (string) $request->userAgent();
        $data['device'] = preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : (preg_match('/Mobi|Android|iPhone/i', $ua) ? 'mobile' : 'desktop');
        $data['comment'] = isset($data['comment']) ? trim(strip_tags($data['comment'])) ?: null : null;

        SurveyResponse::create($data);

        return response()->json(['ok' => true], 201);
    }
}
