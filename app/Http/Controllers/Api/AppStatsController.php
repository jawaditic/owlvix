<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuilderApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Daily usage summaries from apps made with the mobile app builder (app-shell/stats.js). A phone sends at most one
 * per finished day: whether it's a new install, how often the app was opened and which pages were viewed. Only the
 * daily totals are kept; nothing identifies the phone.
 */
class AppStatsController extends Controller
{
    private const MAX_OPENS = 200; // per phone per day: more is a fault, not a person

    public function store(Request $request, string $id): JsonResponse
    {
        $app = BuilderApp::where('status', 'live')->find($id);
        $days = is_array($request->input('days')) ? array_slice($request->input('days'), 0, 7) : [];
        if (! $app || ! $days) {
            return response()->json(['ok' => false], $app ? 422 : 404);
        }
        $pageCount = count($app->content['tabs'] ?? []);
        $first = (bool) $request->boolean('new');
        $seen = [];
        foreach ($days as $d) {
            $day = is_array($d) && is_string($d['day'] ?? null) ? $d['day'] : '';
            if (isset($seen[$day])) {
                continue; // each day once per report
            }
            $seen[$day] = true;
            // Only the last week (phones can be offline for a while), never the future
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day > now()->addDay()->toDateString() || $day < now()->subDays(8)->toDateString()) {
                continue;
            }
            $opens = min(self::MAX_OPENS, max(0, (int) ($d['opens'] ?? 0)));
            if ($opens === 0) {
                continue;
            }
            $pages = [];
            foreach (is_array($d['pages'] ?? null) ? $d['pages'] : [] as $i => $n) {
                if (is_numeric($i) && (int) $i >= 0 && (int) $i < $pageCount) {
                    $pages[(int) $i] = min(self::MAX_OPENS * 5, max(0, (int) $n));
                }
            }
            // counters go up in one statement each (safe when many phones report at once); the row is made first
            DB::table('builder_app_stats')->insertOrIgnore(['builder_app_id' => $app->id, 'day' => $day, 'active' => 0, 'opens' => 0, 'installs' => 0, 'pages' => '{}']);
            $q = DB::table('builder_app_stats')->where('builder_app_id', $app->id)->where('day', $day);
            DB::transaction(function () use ($q, $opens, $pages, &$first) {
                (clone $q)->update(['active' => DB::raw('active + 1'), 'opens' => DB::raw('opens + '.(int) $opens), 'installs' => DB::raw('installs + '.($first ? 1 : 0))]);
                if ($pages) {
                    $merged = json_decode((string) (clone $q)->value('pages'), true) ?: [];
                    foreach ($pages as $i => $n) {
                        $merged[$i] = ($merged[$i] ?? 0) + $n;
                    }
                    (clone $q)->update(['pages' => json_encode((object) $merged)]);
                }
                $first = false; // an install counts once, on the oldest day sent
            });
        }

        return response()->json(['ok' => true]);
    }
}
