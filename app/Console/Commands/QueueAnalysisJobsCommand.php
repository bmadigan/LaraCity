<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Jobs\AnalyzeComplaintJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueAnalysisJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lacity:queue-analysis 
                          {--batch-size=25 : Number of jobs to queue in each batch}
                          {--limit=0 : Maximum number of complaints to queue (0 = no limit)}
                          {--force : Queue jobs even for complaints that already have analysis}
                          {--dry-run : Show what would be queued without actually queueing jobs}';

    /**
     * The console command description.
     */
    protected $description = 'Queue AI analysis + embedding generation jobs for complaints (recommended approach)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 LaraCity AI Analysis + Embedding Job Queue");
        $this->info("Batch Size: {$batchSize} | Limit: " . ($limit ?: 'No limit'));
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No jobs will be queued");
        }

        // Build query for complaints needing analysis
        $query = Complaint::query();
        
        if (!$force) {
            // Only queue complaints without analysis (embeddings will be generated too)
            $query->whereDoesntHave('analysis');
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Found {$total} complaints needing AI analysis + embedding generation");

        if ($dryRun) {
            $this->warn("DRY RUN: Would queue {$total} analysis jobs (each includes embedding generation)");
            return Command::SUCCESS;
        }

        if ($total === 0) {
            $this->info("No complaints need analysis. Use --force to regenerate existing analysis.");
            return Command::SUCCESS;
        }

        // Confirm before queueing large numbers
        if ($total > 100 && !$this->option('no-interaction')) {
            if (!$this->confirm("Queue {$total} jobs? This will use OpenAI API credits.")) {
                $this->info("Operation cancelled.");
                return Command::SUCCESS;
            }
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $queued = 0;

        $query->chunk($batchSize, function ($complaints) use (&$queued, $bar) {
            foreach ($complaints as $complaint) {
                AnalyzeComplaintJob::dispatch($complaint);
                $queued++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("✅ Successfully queued {$queued} AI analysis jobs");
        $this->info("📝 Each job includes: AI analysis + embedding generation");
        $this->newLine();
        $this->info("💡 Next Steps:");
        $this->info("   1. Start queue worker: php artisan queue:work --queue=ai-analysis,default");
        $this->info("   2. Monitor progress: php artisan queue:monitor");
        $this->info("   3. Check failures: php artisan queue:failed");

        Log::info('AI analysis jobs queued', [
            'total_queued' => $queued,
            'batch_size' => $batchSize,
            'force' => $force,
        ]);

        return Command::SUCCESS;
    }
}