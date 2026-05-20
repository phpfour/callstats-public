<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\StoreReminderAction;
use App\Data\Reminders\StoreReminderData;
use App\Http\Requests\Api\StoreReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReminderController extends Controller
{
    /**
     * Store a newly created reminder in storage.
     */
    public function store(StoreReminderRequest $request, StoreReminderAction $action): JsonResponse
    {
        $reminder = $action->execute($request->user(), StoreReminderData::fromRequest($request));

        return (new ReminderResource($reminder))->response()->setStatusCode(201);
    }

    /**
     * Display a listing of the reminders for a given lead.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $leadId = $request->query('lead_id');

        $reminders = Reminder::query()
            ->when($leadId, function ($query) use ($leadId) {
                return $query->where('lead_id', $leadId);
            })
            ->where('user_id', $request->user()->id)
            ->orderByDesc('remind_at')
            ->get();

        return ReminderResource::collection($reminders);
    }
}
