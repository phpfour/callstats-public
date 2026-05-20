<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Build an in-memory XLSX matching smecalls's column format and return
 * an UploadedFile pointing at it. The first inner array is the header.
 *
 * @param  array<int, array<int, string|null>>  $rows
 */
function makeImportFile(array $rows, string $name = 'leads.xlsx'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $columnIndex => $value) {
            if ($value === null) {
                continue;
            }
            $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->getCell($column.($rowIndex + 1))
                ->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'leads-import-').'.xlsx';
    (new Xlsx($spreadsheet))->save($tempPath);

    return new UploadedFile($tempPath, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('imports valid rows from an xlsx upload', function () {
    $admin = User::factory()->admin()->create();

    $file = makeImportFile([
        ['name', 'phone', 'email', 'destination', 'source', 'ielts', 'agent_code'],
        ['Alice Wonder', '+8801711111111', 'alice@example.com', 'UK', 'Facebook', '7.0', null],
        ['Bob Builder', '+8801722222222', null, 'Canada', 'Referral', null, null],
    ]);

    $response = $this->actingAs($admin)
        ->post('/backoffice/leads/import', ['file' => $file]);

    $response->assertRedirect('/backoffice/leads')
        ->assertSessionHas('success', 'Imported 2 leads.');

    expect(Lead::count())->toBe(2)
        ->and(Lead::where('phone_number', '+8801711111111')->first())
        ->name->toBe('Alice Wonder')
        ->study_destination->toBe('UK')
        ->ielts_score->toBe('7.0');
});

it('reports rows missing a phone number as skipped', function () {
    $admin = User::factory()->admin()->create();

    $file = makeImportFile([
        ['name', 'phone'],
        ['Alice', '+8801700000001'],
        ['No Phone', null],
        ['Bob', '+8801700000002'],
    ]);

    $response = $this->actingAs($admin)
        ->post('/backoffice/leads/import', ['file' => $file]);

    $response->assertSessionHas('success', 'Imported 2 leads (1 skipped).');

    $summary = session('importSummary');
    expect($summary)->not->toBeNull()
        ->and($summary['imported'])->toBe(2)
        ->and($summary['skipped'])->toEqual([
            ['row' => 3, 'reason' => 'Missing phone number'],
        ]);
});

it('skips rows with duplicate phone numbers', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->create(['phone_number' => '+8801700000001']);

    $file = makeImportFile([
        ['name', 'phone'],
        ['Duplicate Donna', '+8801700000001'],
        ['Fresh Frank', '+8801700000002'],
    ]);

    $this->actingAs($admin)->post('/backoffice/leads/import', ['file' => $file]);

    expect(Lead::count())->toBe(2);
    $summary = session('importSummary');
    expect($summary['skipped'])->toEqual([
        ['row' => 2, 'reason' => 'Duplicate phone number'],
    ]);
});

it('looks up assigned_to_id from the agent code in column G', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['code' => 'A-007']);

    $file = makeImportFile([
        ['name', 'phone', 'email', 'destination', 'source', 'ielts', 'agent_code'],
        ['Agent Lead', '+8801700001000', null, null, null, null, 'A-007'],
    ]);

    $this->actingAs($admin)->post('/backoffice/leads/import', ['file' => $file]);

    expect(Lead::where('phone_number', '+8801700001000')->first()->assigned_to_id)
        ->toBe($agent->id);
});

it('reports unknown agent codes as skipped without creating the lead', function () {
    $admin = User::factory()->admin()->create();

    $file = makeImportFile([
        ['name', 'phone', 'email', 'destination', 'source', 'ielts', 'agent_code'],
        ['Mystery Agent', '+8801700002000', null, null, null, null, 'NOPE-999'],
    ]);

    $this->actingAs($admin)->post('/backoffice/leads/import', ['file' => $file]);

    expect(Lead::where('phone_number', '+8801700002000')->exists())->toBeFalse();
    $summary = session('importSummary');
    expect($summary['skipped'])->toEqual([
        ['row' => 2, 'reason' => 'Unknown agent code "NOPE-999"'],
    ]);
});

it('rejects an upload that is not an excel file', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/leads/import', [
            'file' => UploadedFile::fake()->create('leads.txt', 10),
        ])
        ->assertSessionHasErrors('file');
});

it('rejects requests without a file', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/leads/import', [])
        ->assertSessionHasErrors('file');
});

it('rejects an agent attempting to import', function () {
    $agent = User::factory()->agent()->create();

    $file = makeImportFile([['name', 'phone'], ['x', '+8801700099999']]);

    $this->actingAs($agent)
        ->post('/backoffice/leads/import', ['file' => $file])
        ->assertForbidden();
});
