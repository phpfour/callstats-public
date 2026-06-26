<?php

declare(strict_types=1);

namespace App\Actions\CallLogs;

use App\Data\CallLogs\CallLogFilters;
use App\Models\CallLog;
use App\Support\Spreadsheet\CsvSafe;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExportCallLogsAction
{
    private const HEADER = ['ID', 'Lead', 'Agent', 'Called At', 'Duration', 'Outcome', 'Notes'];

    /**
     * Build an in-memory spreadsheet of call logs that match the supplied
     * filters. Column shape matches smecalls's existing export so any
     * downstream report tooling keeps working without re-templating.
     */
    public function execute(CallLogFilters $filters): Spreadsheet
    {
        $callLogs = CallLog::query()
            ->with(['lead:id,name', 'user:id,name'])
            ->when($filters->leadId, fn ($query, int $id) => $query->where('lead_id', $id))
            ->when($filters->userId, fn ($query, int $id) => $query->where('user_id', $id))
            ->when($filters->outcome, fn ($query, string $outcome) => $query->where('outcome', $outcome))
            ->when($filters->calledFrom, fn ($query, string $from) => $query->whereDate('called_at', '>=', $from))
            ->when($filters->calledTo, fn ($query, string $to) => $query->whereDate('called_at', '<=', $to))
            ->orderByDesc('called_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([self::HEADER]);

        $row = 2;
        foreach ($callLogs as $log) {
            $sheet->fromArray([
                $log->id,
                CsvSafe::escape($log->lead?->name),
                CsvSafe::escape($log->user?->name),
                $log->called_at?->format('Y-m-d H:i'),
                $log->duration !== null ? gmdate('H:i:s', $log->duration) : null,
                CsvSafe::escape($log->outcome),
                CsvSafe::escape($log->notes),
            ], null, 'A'.$row);
            $row++;
        }

        return $spreadsheet;
    }
}
