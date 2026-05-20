<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Data\Leads\ImportLeadsResult;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportLeadsAction
{
    /**
     * Column map matches smecalls's import format so existing customer
     * spreadsheets keep working without re-templating:
     *   A name, B phone_number, C email, D study_destination,
     *   E source, F ielts_score, G agent_code (looked up to user.id).
     */
    public function execute(UploadedFile $file): ImportLeadsResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $imported = 0;
        $skipped = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $reason = $this->validateRow($row);
            if ($reason !== null) {
                $skipped[] = ['row' => $rowNumber, 'reason' => $reason];

                continue;
            }

            $assignedToId = null;
            if (! empty($row['G'])) {
                $assignedToId = User::query()->where('code', trim((string) $row['G']))->value('id');
                if ($assignedToId === null) {
                    $skipped[] = [
                        'row' => $rowNumber,
                        'reason' => sprintf('Unknown agent code "%s"', $row['G']),
                    ];

                    continue;
                }
            }

            Lead::create([
                'name' => trim((string) $row['A']),
                'phone_number' => trim((string) $row['B']),
                'email' => $this->trimToNull($row['C'] ?? null),
                'study_destination' => $this->trimToNull($row['D'] ?? null),
                'source' => $this->trimToNull($row['E'] ?? null),
                'ielts_score' => $this->trimToNull($row['F'] ?? null),
                'assigned_to_id' => $assignedToId,
            ]);

            $imported++;
        }

        return new ImportLeadsResult(imported: $imported, skipped: $skipped);
    }

    /** @param  array<string, mixed>  $row */
    private function validateRow(array $row): ?string
    {
        $name = trim((string) ($row['A'] ?? ''));
        $phone = trim((string) ($row['B'] ?? ''));

        if ($name === '') {
            return 'Missing name';
        }

        if ($phone === '') {
            return 'Missing phone number';
        }

        if (Lead::query()->where('phone_number', $phone)->exists()) {
            return 'Duplicate phone number';
        }

        return null;
    }

    private function trimToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
