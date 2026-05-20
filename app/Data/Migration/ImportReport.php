<?php

declare(strict_types=1);

namespace App\Data\Migration;

final class ImportReport
{
    /** @var array<string, array{source: int, imported: int}> */
    private array $tables = [];

    public function record(string $table, int $source, int $imported): void
    {
        $this->tables[$table] = ['source' => $source, 'imported' => $imported];
    }

    /** @return array<int, array{table: string, source: int, imported: int, parity: string}> */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->tables as $table => $counts) {
            $rows[] = [
                'table' => $table,
                'source' => $counts['source'],
                'imported' => $counts['imported'],
                'parity' => $counts['source'] === $counts['imported'] ? 'OK' : 'MISMATCH',
            ];
        }

        return $rows;
    }

    public function allMatched(): bool
    {
        foreach ($this->tables as $counts) {
            if ($counts['source'] !== $counts['imported']) {
                return false;
            }
        }

        return true;
    }
}
