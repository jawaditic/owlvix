<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Stats\TrafficStats;
use Illuminate\Http\Request;

/** Admin → Traffic: visitors, countries, sources and devices from the built-in stats. */
class TrafficController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.traffic', TrafficStats::build(TrafficStats::days($request->input('days'))));
    }
}
