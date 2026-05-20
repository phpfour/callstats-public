<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @return array<int, array<string, mixed>> */
function readReportRows(StreamedResponse $response): array
{
    $tempPath = tempnam(sys_get_temp_dir(), 'report-').'.xlsx';

    ob_start();
    $response->sendContent();
    file_put_contents($tempPath, ob_get_clean());

    return IOFactory::load($tempPath)
        ->getActiveSheet()
        ->toArray(null, true, true, true);
}

it('renders the agent performance report for an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/reports/agent-performance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/reports/agent-performance')
            ->has('rows')
            ->has('range.from')
            ->has('range.to'));
});

it('aggregates received / not_received / total / duration per agent', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->agent()->create(['name' => 'Alice']);
    $bob = User::factory()->agent()->create(['name' => 'Bob']);

    $window = Carbon::parse('2026-05-06 10:00:00');

    // Alice: 2 received, 1 no-answer, 1 missed; durations 60+120+0+0 = 180
    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => $window, 'duration' => 60, 'outcome' => 'Successful Contact']);
    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => $window, 'duration' => 120, 'outcome' => 'Interested']);
    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => $window, 'duration' => 0, 'outcome' => 'No Answer']);
    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => $window, 'duration' => 0, 'outcome' => 'Missed']);

    // Bob: 1 received only; duration 30
    CallLog::factory()->create(['user_id' => $bob->id, 'called_at' => $window, 'duration' => 30, 'outcome' => 'Follow-up']);

    $response = $this->actingAs($admin)
        ->get('/backoffice/reports/agent-performance?from=2026-05-06&to=2026-05-06');

    $response->assertInertia(fn ($page) => $page
        ->where('rows.0.name', 'Alice')
        ->where('rows.0.received', 2)
        ->where('rows.0.not_received', 1)
        ->where('rows.0.total', 4)
        ->where('rows.0.duration', 180)
        ->where('rows.1.name', 'Bob')
        ->where('rows.1.received', 1)
        ->where('rows.1.not_received', 0)
        ->where('rows.1.total', 1)
        ->where('rows.1.duration', 30));
});

it('honors the date range — calls outside are excluded', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->agent()->create(['name' => 'Alice']);

    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => Carbon::parse('2026-05-06 10:00'), 'outcome' => 'Successful Contact']);
    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => Carbon::parse('2026-05-15 10:00'), 'outcome' => 'No Answer']);

    $this->actingAs($admin)
        ->get('/backoffice/reports/agent-performance?from=2026-05-06&to=2026-05-06')
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.total', 1));
});

it('excludes agents with no calls in the date range', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->agent()->create(['name' => 'Inactive']);
    $active = User::factory()->agent()->create(['name' => 'Active']);

    CallLog::factory()->create([
        'user_id' => $active->id,
        'called_at' => Carbon::parse('2026-05-06 10:00'),
    ]);

    $this->actingAs($admin)
        ->get('/backoffice/reports/agent-performance?from=2026-05-06&to=2026-05-06')
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Active'));
});

it('defaults the range to today when none provided', function () {
    Carbon::setTestNow('2026-05-06 14:30:00');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/reports/agent-performance')
        ->assertInertia(fn ($page) => $page
            ->where('range.from', '2026-05-06')
            ->where('range.to', '2026-05-06'));
});

it('rejects an agent attempting to view the report', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/reports/agent-performance')
        ->assertForbidden();
});

it('exports the report as an xlsx with matching aggregates', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->agent()->create(['name' => 'Alice']);

    $window = Carbon::parse('2026-05-06 10:00');

    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => $window, 'duration' => 60, 'outcome' => 'Successful Contact']);
    CallLog::factory()->create(['user_id' => $alice->id, 'called_at' => $window, 'duration' => 0, 'outcome' => 'No Answer']);

    $response = $this->actingAs($admin)
        ->get('/backoffice/reports/agent-performance/export?from=2026-05-06&to=2026-05-06');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('spreadsheetml');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('agent-performance-2026-05-06-to-2026-05-06.xlsx');

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readReportRows($streamed);

    expect($rows[1])->toEqual([
        'A' => 'Agent Name',
        'B' => 'Not Received Calls',
        'C' => 'Received Calls',
        'D' => 'Total Calls',
        'E' => 'Total Call Duration',
    ]);

    expect($rows[2]['A'])->toBe('Alice')
        ->and((int) $rows[2]['B'])->toBe(1)
        ->and((int) $rows[2]['C'])->toBe(1)
        ->and((int) $rows[2]['D'])->toBe(2)
        ->and($rows[2]['E'])->toBe('00:01:00');
});

it('rejects an agent attempting to export the report', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/reports/agent-performance/export')
        ->assertForbidden();
});
