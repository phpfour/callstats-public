<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CallLogs\StoreCallLogAction;
use App\Data\CallLogs\StoreCallLogData;
use App\Enums\UserRole;
use App\Http\Requests\Api\StoreCallLogRequest;
use App\Http\Resources\CallLogResource;
use App\Models\CallLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class CallLogController extends Controller
{
    /**
     * Store a newly created call log in storage.
     */
    public function store(StoreCallLogRequest $request, StoreCallLogAction $action): JsonResponse
    {
        $callLog = $action->execute($request->user(), StoreCallLogData::fromRequest($request));

        return (new CallLogResource($callLog))->response()->setStatusCode(201);
    }

    /**
     * Display a listing of call logs with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CallLog::query()->with('callbackReminder');

        if ($leadId = $request->query('lead_id')) {
            $query->where('lead_id', $leadId);
        }

        $user = $request->user();

        if ($user->hasRole(UserRole::AGENT->value)) {
            $query->where('user_id', $user->id);
        } elseif ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($outcome = $request->query('outcome')) {
            $query->where('outcome', $outcome);
        }

        $from = $request->query('from');
        $to = $request->query('to');
        if ($from && $to) {
            $query->whereBetween('called_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        }

        $callLogs = $query->orderByDesc('called_at')->get();

        return CallLogResource::collection($callLogs);
    }
}
