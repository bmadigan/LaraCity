<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Complaints\ImportCsvData;
use Illuminate\Console\Command;

class ImportCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laracity:import-csv
                           {--file= : Path to CSV file to import}
                           {--validate : Validate CSV headers before import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import NYC 311 complaint data from CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->option('file');
        $validate = $this->option('validate');

        if (!$filePath) {
            $this->error('Please specify a CSV file using --file option');
            $this->info('Example: php artisan laracity:import-csv --file=storage/311-data.csv --validate');
            return 1;
        }

        // Convert relative path to absolute if needed
        if (!str_starts_with($filePath, '/')) {
            $filePath = base_path($filePath);
        }

        if (!file_exists($filePath)) {
            $this->error("CSV file not found: {$filePath}");
            return 1;
        }

        $this->info("Starting CSV import from: {$filePath}");
        $this->info("Validation: " . ($validate ? 'Enabled' : 'Disabled'));
        $this->newLine();

        try {
            $startTime = microtime(true);

            $summary = ImportCsvData::run($filePath, $validate);

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->displayImportSummary($summary, $duration);

            if ($summary['error_count'] > 0) {
                $this->newLine();
                $this->error("Import completed with {$summary['error_count']} errors");

                if ($summary['error_count'] <= 10) {
                    $this->warn('Errors:');
                    foreach ($summary['errors'] as $error) {
                        $this->line("  - {$error}");
                    }
                } else {
                    $this->warn("First 10 errors (check logs for complete list):");
                    foreach (array_slice($summary['errors'], 0, 10) as $error) {
                        $this->line("  - {$error}");
                    }
                }

                return 1;
            }

            $this->newLine();
            $this->info('✅ CSV import completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Display import summary in a formatted table
     */
    private function displayImportSummary(array $summary, float $duration): void
    {
        $this->newLine();
        $this->info('📊 Import Summary');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Records Processed', number_format($summary['processed'])],
                ['New Records Imported', number_format($summary['imported'])],
                ['Existing Records Updated', number_format($summary['updated'])],
                ['Records Skipped', number_format($summary['skipped'])],
                ['Errors', number_format($summary['error_count'])],
                ['Success Rate', $summary['success_rate'] . '%'],
                ['Duration', $duration . ' seconds'],
            ]
        );
    }
}
