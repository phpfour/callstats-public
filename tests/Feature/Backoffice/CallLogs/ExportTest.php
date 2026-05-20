<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @return array<int, array<string, mixed>> */
function readExportRows(StreamedResponse $response): array
{
    $tempPath = tempnam(sys_get_temp_dir(), 'export-').'.xlsx';

    ob_start();
    $response->sendContent();
    file_put_contents($tempPath, ob_get_clean());

    $sheet = IOFactory::load($tempPath)->getActiveSheet();

    return $sheet->toArray(null, true, true, true);
}

it('streams an xlsx with header row and one row per call log', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create(['name' => 'Aisha Khan']);
    $agent = User::factory()->agent()->create(['name' => 'Bob Builder']);

    CallLog::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'called_at' => Carbon::parse('2026-05-06 10:30:00'),
        'duration' => 125,
        'outcome' => 'Successful Contact',
        'notes' => 'Quick chat.',
    ]);

    $response = $this->actingAs($admin)->get('/backoffice/call-logs/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))
        ->toContain('spreadsheetml');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('call-logs-')
        ->toContain('.xlsx');

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readExportRows($streamed);

    expect($rows[1])->toEqual([
        'A' => 'ID',
        'B' => 'Lead',
        'C' => 'Agent',
        'D' => 'Called At',
        'E' => 'Duration',
        'F' => 'Outcome',
        'G' => 'Notes',
    ]);

    expect($rows[2]['B'])->toBe('Aisha Khan')
        ->and($rows[2]['C'])->toBe('Bob Builder')
        ->and($rows[2]['D'])->toBe('2026-05-06 10:30')
        ->and($rows[2]['E'])->toBe('00:02:05')
        ->and($rows[2]['F'])->toBe('Successful Contact')
        ->and($rows[2]['G'])->toBe('Quick chat.');
});

it('honors the outcome filter', function () {
    $admin = User::factory()->admin()->create();
    CallLog::factory()->count(2)->create(['outcome' => 'Successful Contact']);
    CallLog::factory()->create(['outcome' => 'No Answer']);

    $response = $this->actingAs($admin)
        ->get('/backoffice/call-logs/export?outcome='.urlencode('Successful Contact'));

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readExportRows($streamed);

    // 1 header + 2 data rows
    expect($rows)->toHaveCount(3);
    expect($rows[2]['F'])->toBe('Successful Contact')
        ->and($rows[3]['F'])->toBe('Successful Contact');
});

it('honors the date-range filter', function () {
    $admin = User::factory()->admin()->create();
    CallLog::factory()->create(['called_at' => Carbon::parse('2026-01-15 10:00')]);
    CallLog::factory()->create(['called_at' => Carbon::parse('2026-02-15 10:00')]);
    CallLog::factory()->create(['called_at' => Carbon::parse('2026-03-15 10:00')]);

    $response = $this->actingAs($admin)
        ->get('/backoffice/call-logs/export?called_from=2026-02-01&called_to=2026-02-28');

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readExportRows($streamed);

    // 1 header + 1 data row
    expect($rows)->toHaveCount(2);
    expect($rows[2]['D'])->toBe('2026-02-15 10:00');
});

it('produces an empty spreadsheet (header only) when no rows match', function () {
    $admin = User::factory()->admin()->create();
    CallLog::factory()->create(['outcome' => 'No Answer']);

    $response = $this->actingAs($admin)
        ->get('/backoffice/call-logs/export?outcome='.urlencode('Missed'));

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readExportRows($streamed);

    expect($rows)->toHaveCount(1)
        ->and($rows[1]['A'])->toBe('ID');
});

it('rejects an agent attempting to export', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/call-logs/export')
        ->assertForbidden();
});
