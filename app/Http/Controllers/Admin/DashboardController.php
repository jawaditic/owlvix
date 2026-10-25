<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Stats\DashboardStats;

/** Admin home: key numbers and charts about enquiries, plus site content at a glance. */
class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', DashboardStats::build());
    }
}
