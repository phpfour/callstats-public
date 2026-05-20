<?php

declare(strict_types=1);

namespace App\Actions\Migration;

use App\Data\Migration\ImportReport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportFromSmecallsAction
{
    /**
     * Domain tables copied from the smecalls source. Order matters when
     * FK checks are on; with checks disabled (which we do during bulk
     * import) the order is informational only. Keep parents-first for
     * readability and so the parity report reads top-down.
     *
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'leads',
        'call_logs',
        'reminders',
    ];

    /**
     * Tables that have an auto-incrementing primary key. After bulk
     * insert with explicit IDs we bump AUTO_INCREMENT past the largest
     * imported ID so subsequent INSERTs don't collide.
     *
     * @var list<string>
     */
    private const SEQUENCED_TABLES = [
        'users',
        'roles',
        'permissions',
        'leads',
        'call_logs',
        'reminders',
    ];

    private const CHUNK = 500;

    public function execute(
        string $sourceConnectionName,
        bool $dryRun,
        bool $force,
        bool $resyncAutoIncrement = true,
    ): ImportReport {
        $source = DB::connection($sourceConnectionName);
        $destination = DB::connection();

        $report = new ImportReport;

        $this->ensureDestinationIsEmpty($destination, $force);

        if (! $dryRun && $force) {
            $this->truncateDestination($destination);
        }

        $disableFkChecks = $destination->getDriverName() === 'mysql';

        if (! $dryRun && $disableFkChecks) {
            $destination->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach (self::TABLES as $table) {
                $sourceCount = (int) $source->table($table)->count();

                if ($dryRun) {
                    $report->record($table, $sourceCount, 0);

                    continue;
                }

                $imported = $this->copyTable($source, $destination, $table);
                $report->record($table, $sourceCount, $imported);
            }
        } finally {
            if (! $dryRun && $disableFkChecks) {
                $destination->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        if (! $dryRun && $resyncAutoIncrement) {
            $this->resyncAutoIncrement($destination);
        }

        return $report;
    }

    private function copyTable(
        ConnectionInterface $source,
        ConnectionInterface $destination,
        string $table,
    ): int {
        $imported = 0;

        $source->table($table)
            ->orderBy(
                in_array($table, self::SEQUENCED_TABLES, true)
                    ? 'id'
                    : $this->compositeKeyColumn($table),
            )
            ->chunk(self::CHUNK, function ($rows) use ($destination, $table, &$imported): void {
                if ($rows->isEmpty()) {
                    return;
                }

                $payload = $rows->map(static fn ($row): array => (array) $row)->all();
                $destination->table($table)->insert($payload);
                $imported += count($payload);
            });

        return $imported;
    }

    /**
     * Spatie's pivot tables don't have an `id`; pick a stable column to
     * order by so chunking is deterministic.
     */
    private function compositeKeyColumn(string $table): string
    {
        return match ($table) {
            'role_has_permissions' => 'role_id',
            'model_has_roles', 'model_has_permissions' => 'model_id',
            default => throw new RuntimeException("No order column defined for table [{$table}]."),
        };
    }

    private function ensureDestinationIsEmpty(ConnectionInterface $destination, bool $force): void
    {
        if ($force) {
            return;
        }

        foreach (self::TABLES as $table) {
            if ($destination->table($table)->exists()) {
                throw new RuntimeException(sprintf(
                    'Destination table [%s] already has rows. Re-run with --force to truncate first.',
                    $table,
                ));
            }
        }
    }

    private function truncateDestination(ConnectionInterface $destination): void
    {
        $disableFkChecks = $destination->getDriverName() === 'mysql';

        if ($disableFkChecks) {
            $destination->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            // Truncate in reverse so child rows go before parents — matters
            // when FK checks are on and harmless when they're off.
            foreach (array_reverse(self::TABLES) as $table) {
                $destination->table($table)->truncate();
            }
        } finally {
            if ($disableFkChecks) {
                $destination->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * After inserting with explicit IDs, MySQL doesn't always advance the
     * AUTO_INCREMENT counter. Bump each sequenced table's counter past
     * the largest imported id so subsequent INSERTs don't collide.
     */
    private function resyncAutoIncrement(ConnectionInterface $destination): void
    {
        if ($destination->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::SEQUENCED_TABLES as $table) {
            $maxId = (int) $destination->table($table)->max('id');

            if ($maxId === 0) {
                continue;
            }

            $destination->statement(sprintf(
                'ALTER TABLE %s AUTO_INCREMENT = %d',
                $table,
                $maxId + 1,
            ));
        }
    }
}
