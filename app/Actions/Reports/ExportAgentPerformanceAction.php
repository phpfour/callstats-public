<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Reports\ReportDateRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExportAgentPerformanceAction
{
    private const HEADER = [
        'Agent Name',
        'Not Received Calls',
        'Received Calls',
        'Total Calls',
        'Total Call Duration',
    ];

    public function __construct(
        private readonly AgentPerformanceReportAction $report,
    ) {}

    public function execute(ReportDateRange $range): Spreadsheet
    {
        $rows = $this->report->execute($range);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([self::HEADER]);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row->name,
                (int) $row->not_received,
                (int) $row->received,
                (int) $row->total,
                $row->duration !== null ? gmdate('H:i:s', (int) $row->duration) : null,
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        return $spreadsheet;
    }
}
