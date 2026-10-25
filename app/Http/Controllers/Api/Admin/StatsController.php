<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Stats\DashboardStats;
use App\Support\Stats\TrafficStats;
use Illuminate\Http\Request;

/** Dashboard and traffic numbers for the mobile app (same figures as the website admin). */
class StatsController extends Controller
{
    public function dashboard()
    {
        $data = DashboardStats::build();
        $data['recent'] = $data['recent']->map->toApi();

        return response()->json($data);
    }

    public function traffic(Request $request)
    {
        $data = TrafficStats::build(TrafficStats::days($request->input('days')));
        $data['countries'] = $data['countries']->values();
        $data['recent'] = $data['recent']->map(fn ($v) => [
            'path' => $v->path,
            'country' => $v->country,
            'source' => $v->source,
            'device' => $v->device,
            'browser' => $v->browser,
            'is_new' => $v->is_new,
            'created_at' => $v->created_at?->toIso8601String(),
        ]);

        return response()->json($data);
    }
}
