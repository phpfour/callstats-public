<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\Reports\AgentPerformanceReportAction;
use App\Actions\Reports\ExportAgentPerformanceAction;
use App\Data\Reports\ReportDateRange;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function agentPerformance(Request $request, AgentPerformanceReportAction $action): Response
    {
        $range = ReportDateRange::fromRequest($request);
        $rows = $action->execute($range);

        return Inertia::render('backoffice/reports/agent-performance', [
            'rows' => $rows,
            'range' => $range->toIsoArray(),
        ]);
    }

    public function exportAgentPerformance(Request $request, ExportAgentPerformanceAction $action): StreamedResponse
    {
        $range = ReportDateRange::fromRequest($request);
        $spreadsheet = $action->execute($range);
        $writer = new Xlsx($spreadsheet);

        $filename = sprintf(
            'agent-performance-%s-to-%s.xlsx',
            $range->from->toDateString(),
            $range->to->toDateString(),
        );

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
