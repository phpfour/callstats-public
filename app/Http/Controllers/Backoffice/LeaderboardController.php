<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $service): Response
    {
        $from = $request->query('from', Carbon::today()->subDays(30)->toDateString());
        $to = $request->query('to', Carbon::today()->toDateString());

        return Inertia::render('backoffice/leaderboard', [
            'rows' => $service->build((string) $from, (string) $to),
            'featured' => $service->featuredAgent(),
            'generatedAt' => Carbon::now()->toIso8601String(),
        ]);
    }
}
