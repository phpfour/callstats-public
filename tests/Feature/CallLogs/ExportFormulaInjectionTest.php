<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @return array<int, array<string, mixed>> */
function readInjectionRows(StreamedResponse $response): array
{
    $tempPath = tempnam(sys_get_temp_dir(), 'inject-').'.xlsx';

    ob_start();
    $response->sendContent();
    file_put_contents($tempPath, ob_get_clean());

    return IOFactory::load($tempPath)->getActiveSheet()->toArray(null, true, true, true);
}

it('neutralizes formula injection in exported notes and lead names', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create(['name' => '=HYPERLINK("http://evil.example")']);
    $agent = User::factory()->agent()->create(['name' => 'Bob Builder']);

    CallLog::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'called_at' => Carbon::parse('2026-05-06 10:30:00'),
        'outcome' => 'Successful Contact',
        'notes' => '=cmd|\'/c calc\'!A1',
    ]);

    $response = $this->actingAs($admin)->get('/backoffice/call-logs/export');
    $response->assertOk();

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readInjectionRows($streamed);

    // Lead name (column B) and notes (column G) are prefixed with a quote so
    // the spreadsheet treats them as literal text, not a formula.
    expect($rows[2]['B'])->toBe('\'=HYPERLINK("http://evil.example")')
        ->and($rows[2]['G'])->toBe('\'=cmd|\'/c calc\'!A1');
});

it('leaves benign values untouched', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create(['name' => 'Aisha Khan']);
    $agent = User::factory()->agent()->create(['name' => 'Bob Builder']);

    CallLog::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'called_at' => Carbon::parse('2026-05-06 10:30:00'),
        'outcome' => 'Successful Contact',
        'notes' => 'Quick chat.',
    ]);

    $response = $this->actingAs($admin)->get('/backoffice/call-logs/export');

    /** @var StreamedResponse $streamed */
    $streamed = $response->baseResponse;
    $rows = readInjectionRows($streamed);

    expect($rows[2]['B'])->toBe('Aisha Khan')
        ->and($rows[2]['G'])->toBe('Quick chat.');
});
