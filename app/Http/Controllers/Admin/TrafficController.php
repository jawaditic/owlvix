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

    /** Admin → Growth: which guides and gallery designs bring people into the free builders, and how far they get. */
    public function growth(Request $request)
    {
        return view('admin.growth', \App\Support\Stats\BuilderStats::build(\App\Support\Stats\BuilderStats::days($request->input('days'))));
    }
}
