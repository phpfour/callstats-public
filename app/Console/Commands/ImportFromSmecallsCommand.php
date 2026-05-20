<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Migration\ImportFromSmecallsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportFromSmecallsCommand extends Command
{
    protected $signature = 'callstats:import-from-smecalls
        {--source= : The configured DB connection name to read smecalls data from}
        {--dry-run : Read source counts without writing}
        {--force : Truncate destination domain tables before importing}';

    protected $description = 'Identity-preserving copy of smecalls domain tables into callstats. Personal access tokens are intentionally not copied — agents re-login on cutover.';

    public function handle(ImportFromSmecallsAction $action): int
    {
        $source = (string) $this->option('source');

        if ($source === '') {
            $this->error('Pass --source=<connection-name> (configured in config/database.php).');

            return self::FAILURE;
        }

        if (! array_key_exists($source, config('database.connections', []))) {
            $this->error("Unknown DB connection [{$source}]. Configure it in config/database.php first.");

            return self::FAILURE;
        }

        try {
            DB::connection($source)->getPdo();
        } catch (Throwable $exception) {
            $this->error("Cannot connect to source [{$source}]: ".$exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info(sprintf(
            '%s import from [%s] -> [%s]%s',
            $dryRun ? 'Dry-run' : 'Running',
            $source,
            DB::getDefaultConnection(),
            $force ? ' (truncating first)' : '',
        ));

        try {
            $report = $action->execute($source, $dryRun, $force);
        } catch (Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Source rows', 'Imported', 'Parity'],
            array_map(
                static fn (array $row): array => [
                    $row['table'],
                    (string) $row['source'],
                    (string) $row['imported'],
                    $row['parity'],
                ],
                $report->rows(),
            ),
        );

        if ($dryRun) {
            $this->info('Dry-run complete. No data written.');

            return self::SUCCESS;
        }

        if (! $report->allMatched()) {
            $this->error('Import finished with row-count mismatches. Investigate before going live.');

            return self::FAILURE;
        }

        $this->info('Import complete. Personal access tokens were not copied — agents must re-login.');

        return self::SUCCESS;
    }
}
