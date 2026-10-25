<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\MessageReply;
use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Support\Carbon;

/** Admin home: key numbers and charts about enquiries, plus site content at a glance. */
class DashboardController extends Controller
{
    private const BUDGETS = ['Not sure yet', 'Under $500', '$500 – $2,000', '$2,000 – $5,000', '$5,000+'];

    public function index()
    {
        $now = now();
        $from = $now->copy()->subDays(89)->startOfDay();

        // Enquiries per day for the last 90 days, split by where they came from
        $rows = ContactMessage::where('created_at', '>=', $from)->get(['created_at', 'source']);
        $days = [];
        for ($d = $from->copy(); $d <= $now; $d->addDay()) {
            $days[$d->toDateString()] = ['date' => $d->toDateString(), 'popup' => 0, 'contact' => 0];
        }
        foreach ($rows as $r) {
            $key = $r->created_at->toDateString();
            if (isset($days[$key])) {
                $days[$key][$r->source === 'popup' ? 'popup' : 'contact']++;
            }
        }

        // This month vs last month (same number of days, so the comparison is fair)
        $monthStart = $now->copy()->startOfMonth();
        $thisMonth = ContactMessage::where('created_at', '>=', $monthStart)->count();
        $lastMonth = ContactMessage::whereBetween('created_at', [
            $monthStart->copy()->subMonthNoOverflow(),
            $now->copy()->subMonthNoOverflow(),
        ])->count();

        // Replies: share of enquiries answered and how long the first answer took
        $total = ContactMessage::count();
        $replied = ContactMessage::has('replies')->count();
        $firstReplies = MessageReply::selectRaw('contact_message_id, min(created_at) as first_at')
            ->groupBy('contact_message_id')->get()->keyBy('contact_message_id');
        $hours = ContactMessage::whereIn('id', $firstReplies->keys())->get(['id', 'created_at'])
            ->map(fn ($m) => $m->created_at->diffInMinutes(Carbon::parse($firstReplies[$m->id]->first_at)) / 60);

        $services = ContactMessage::selectRaw('service, count(*) as n')->whereNotNull('service')
            ->groupBy('service')->orderByDesc('n')->pluck('n', 'service');
        $budgetCounts = ContactMessage::selectRaw('budget, count(*) as n')->whereNotNull('budget')
            ->groupBy('budget')->pluck('n', 'budget');
        $budgets = collect(self::BUDGETS)->mapWithKeys(fn ($b) => [$b => (int) ($budgetCounts[$b] ?? 0)]);

        return view('admin.dashboard', [
            'stats' => [
                'total' => $total,
                'thisMonth' => $thisMonth,
                'lastMonth' => $lastMonth,
                'unread' => ContactMessage::unread()->count(),
                'replyRate' => $total ? round($replied / $total * 100) : null,
                'replied' => $replied,
                'avgReplyHours' => $hours->isNotEmpty() ? $hours->avg() : null,
                'withPhone' => ContactMessage::whereNotNull('phone')->count(),
            ],
            'daily' => array_values($days),
            'services' => $services,
            'budgets' => $budgets,
            'sources' => [
                'popup' => ContactMessage::where('source', 'popup')->count(),
                'contact' => ContactMessage::where('source', '!=', 'popup')->count(),
            ],
            'recent' => ContactMessage::withCount('replies')->latest()->take(6)->get(),
            'content' => [
                'projects' => Project::published()->count(),
                'projectsTotal' => Project::count(),
                'reviews' => Testimonial::published()->count(),
                'reviewsTotal' => Testimonial::count(),
            ],
        ]);
    }
}
