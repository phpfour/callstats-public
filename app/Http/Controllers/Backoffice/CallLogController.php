<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\CallLogs\ExportCallLogsAction;
use App\Actions\CallLogs\ListCallLogsAction;
use App\Actions\CallLogs\UpdateCallLogAction;
use App\Data\CallLogs\CallLogFilters;
use App\Data\CallLogs\UpdateCallLogData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\CallLogRequest;
use App\Models\CallLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallLogController extends Controller
{
    public function index(Request $request, ListCallLogsAction $action): Response
    {
        $filters = CallLogFilters::fromRequest($request);
        $sort = $request->string('sort')->toString() ?: null;
        $direction = $request->string('dir')->toString() ?: 'desc';

        $callLogs = $action->execute(
            filters: $filters,
            sort: $sort,
            direction: $direction,
        );

        return Inertia::render('backoffice/call-logs/index', [
            'callLogs' => $callLogs,
            'filters' => $filters->toArray(),
            'sort' => ['column' => $sort, 'direction' => $direction],
            'agents' => User::query()->orderBy('name')->get(['id', 'name']),
            'outcomes' => config('call.outcomes'),
        ]);
    }

    public function show(CallLog $callLog): Response
    {
        $callLog->load(['lead:id,name,phone_number', 'user:id,name']);

        return Inertia::render('backoffice/call-logs/show', [
            'callLog' => $callLog,
        ]);
    }

    public function edit(CallLog $callLog): Response
    {
        $callLog->load(['lead:id,name,phone_number', 'user:id,name']);

        return Inertia::render('backoffice/call-logs/edit', [
            'callLog' => $callLog,
            'outcomes' => config('call.outcomes'),
        ]);
    }

    public function update(CallLogRequest $request, CallLog $callLog, UpdateCallLogAction $action): RedirectResponse
    {
        $action->execute($callLog, UpdateCallLogData::fromRequest($request));

        return redirect()
            ->route('backoffice.call-logs.show', $callLog)
            ->with('success', 'Call log updated.');
    }

    public function export(Request $request, ExportCallLogsAction $action): StreamedResponse
    {
        $spreadsheet = $action->execute(CallLogFilters::fromRequest($request));
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'call-logs-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
