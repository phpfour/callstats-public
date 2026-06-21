<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\FollowUps\GetFollowUpLeadsAction;
use App\Data\FollowUps\FollowUpQueue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request, GetFollowUpLeadsAction $action): Response
    {
        $queue = FollowUpQueue::fromRequest($request->query('queue'));

        return Inertia::render('backoffice/follow-ups/index', [
            'leads' => $action->execute($queue),
            'activeQueue' => $queue->value,
            'queues' => FollowUpQueue::options(),
            'counts' => $action->counts(),
        ]);
    }
}
