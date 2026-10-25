<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Support\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin → Traffic: visitors, countries, sources and devices from the built-in stats. */
class TrafficController extends Controller
{
    public function index(Request $request)
    {
        $days = in_array((int) $request->input('days'), [7, 30, 90], true) ? (int) $request->input('days') : 30;
        $from = now()->subDays($days - 1)->startOfDay();
        $range = PageView::where('created_at', '>=', $from);

        // A "visitor" is one browser on one day (the id changes daily by design, for privacy)
        $visitors = (clone $range)->distinct()->count('visitor');
        $views = (clone $range)->count();
        $newVisitors = (clone $range)->where('is_new', true)->distinct()->count('visitor');
        // Only compare enquiries from the time tracking was running, or the rate would be inflated
        $trackingStart = PageView::min('created_at');
        $enquiries = $trackingStart
            ? ContactMessage::where('created_at', '>=', max($from, \Illuminate\Support\Carbon::parse($trackingStart)))->count()
            : 0;

        $countBy = fn (string $col, int $limit) => (clone $range)
            ->select($col, DB::raw('count(distinct visitor) as n'))
            ->groupBy($col)->orderByDesc('n')->limit($limit)->get()
            ->map(fn ($r) => ['key' => $r->$col, 'n' => (int) $r->n]);

        $countries = $countBy('country', 12)->map(fn ($r) => $r + ['name' => Country::name($r['key']), 'flag' => Country::flag($r['key'])]);

        // Visitors per day for the last 90 days, split new / returning (for the chart)
        $chartFrom = now()->subDays(89)->startOfDay();
        $perDay = PageView::where('created_at', '>=', $chartFrom)
            ->selectRaw('date(created_at) as d, visitor, max(is_new) as is_new')
            ->groupBy('d', 'visitor')->get()->groupBy('d');
        $daily = [];
        for ($d = $chartFrom->copy(); $d <= now(); $d->addDay()) {
            $rows = $perDay[$d->toDateString()] ?? collect();
            $new = $rows->where('is_new', 1)->count();
            $daily[] = ['date' => $d->toDateString(), 'new' => $new, 'returning' => $rows->count() - $new];
        }

        return view('admin.traffic', [
            'days' => $days,
            'stats' => [
                'visitors' => $visitors,
                'views' => $views,
                'perVisitor' => $visitors ? round($views / $visitors, 1) : 0,
                'newShare' => $visitors ? round($newVisitors / $visitors * 100) : 0,
                'enquiries' => $enquiries,
                'conversion' => $visitors ? min(100, round($enquiries / $visitors * 100, 1)) : null,
                'today' => PageView::where('created_at', '>=', now()->startOfDay())->distinct()->count('visitor'),
                'live' => PageView::where('created_at', '>=', now()->subMinutes(5))->distinct()->count('visitor'),
            ],
            'daily' => $daily,
            'countries' => $countries,
            'sources' => $countBy('source', 10),
            'devices' => $countBy('device', 3),
            'browsers' => $countBy('browser', 6),
            'pages' => (clone $range)->select('path', DB::raw('count(*) as n'))->groupBy('path')->orderByDesc('n')->limit(8)->get(),
            'recent' => PageView::latest('created_at')->limit(12)->get(),
        ]);
    }
}
