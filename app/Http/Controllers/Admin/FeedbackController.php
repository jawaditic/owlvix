<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;

/** Admin → Feedback: answers to the exit survey ("What stopped you from getting a quote today?"). */
class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $reason = array_key_exists($request->input('reason'), SurveyResponse::REASONS) ? $request->input('reason') : null;
        $responses = SurveyResponse::query()
            ->when($reason, fn ($q) => $q->where('reason', $reason))
            ->latest()->paginate(30)->withQueryString();

        $counts = SurveyResponse::selectRaw('reason, count(*) as n')->groupBy('reason')->pluck('n', 'reason');
        $breakdown = collect(SurveyResponse::REASONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'n' => (int) ($counts[$key] ?? 0)]);

        $view = view('admin.feedback', [
            'responses' => $responses,
            'breakdown' => $breakdown,
            'reason' => $reason,
            'stats' => [
                'total' => $counts->sum(),
                'week' => SurveyResponse::where('created_at', '>=', now()->subDays(7))->count(),
                'withEmail' => SurveyResponse::whereNotNull('email')->count(),
                'new' => SurveyResponse::whereNull('read_at')->count(),
            ],
        ]);

        // Seen now: clears the "new" badge in the menu (the page above still shows what was new)
        SurveyResponse::whereNull('read_at')->update(['read_at' => now()]);

        return $view;
    }

    public function destroy(SurveyResponse $response)
    {
        $response->delete();

        return back()->with('status', 'Answer deleted.');
    }
}
