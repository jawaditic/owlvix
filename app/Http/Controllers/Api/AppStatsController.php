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
        foreach ($days as $d) {
            $day = is_array($d) ? (string) ($d['day'] ?? '') : '';
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
            DB::transaction(function () use ($app, $day, $opens, $pages, &$first) {
                $row = DB::table('builder_app_stats')->where('builder_app_id', $app->id)->where('day', $day)->lockForUpdate()->first();
                $merged = $row ? (json_decode((string) $row->pages, true) ?: []) : [];
                foreach ($pages as $i => $n) {
                    $merged[$i] = ($merged[$i] ?? 0) + $n;
                }
                $values = ['active' => ($row->active ?? 0) + 1, 'opens' => ($row->opens ?? 0) + $opens, 'installs' => ($row->installs ?? 0) + ($first ? 1 : 0), 'pages' => json_encode((object) $merged)];
                $row ? DB::table('builder_app_stats')->where('id', $row->id)->update($values)
                    : DB::table('builder_app_stats')->insert(['builder_app_id' => $app->id, 'day' => $day] + $values);
                $first = false; // an install counts once, on the oldest day sent
            });
        }

        return response()->json(['ok' => true]);
    }
}
