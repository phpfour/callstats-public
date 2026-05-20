<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AnalyticsService $analytics): Response
    {
        return Inertia::render('backoffice/dashboard', [
            'counters' => [
                'totalCalls' => $analytics->getTotalCalls(),
                'availableLeads' => $analytics->getAvailableLeads(),
                'availableAgents' => $analytics->getAvailableAgents(),
                'averageDuration' => $analytics->getAverageCallDuration(),
                'callsToday' => $analytics->getCallsToday(),
            ],
            'charts' => [
                'daily' => $analytics->getDailyCallVolume(),
                'weekly' => $analytics->getWeeklyCallVolume(),
            ],
        ]);
    }
}
