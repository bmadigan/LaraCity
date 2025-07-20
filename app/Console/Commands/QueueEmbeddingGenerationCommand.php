<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Jobs\GenerateEmbeddingJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueEmbeddingGenerationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lacity:queue-embeddings 
                          {--batch-size=50 : Number of jobs to queue in each batch}
                          {--limit=0 : Maximum number of complaints to queue (0 = no limit)}
                          {--force : Queue jobs even for complaints that already have embeddings}
                          {--dry-run : Show what would be queued without actually queueing jobs}';

    /**
     * The console command description.
     */
    protected $description = 'Queue embedding generation jobs for complaints';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 LaraCity Embedding Job Queue Manager");
        $this->info("Batch Size: {$batchSize} | Limit: " . ($limit ?: 'No limit'));
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No jobs will be queued");
        }

        // Build query
        $query = Complaint::query();
        
        if (!$force) {
            // Only queue complaints without embeddings
            $query->whereDoesntHave('embeddings', function ($q) {
                $q->where('document_type', 'complaint');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Found {$total} complaints to queue for embedding generation");

        if ($dryRun) {
            $this->warn("DRY RUN: Would queue {$total} embedding jobs");
            return Command::SUCCESS;
        }

        if ($total === 0) {
            $this->info("No complaints need embedding generation. Use --force to regenerate existing embeddings.");
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $queued = 0;

        $query->chunk($batchSize, function ($complaints) use (&$queued, $bar) {
            foreach ($complaints as $complaint) {
                GenerateEmbeddingJob::dispatch($complaint);
                $queued++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("✅ Successfully queued {$queued} embedding generation jobs");
        $this->info("💡 Monitor progress with: php artisan queue:work");
        $this->info("📊 Check job status with: php artisan queue:failed");

        Log::info('Embedding generation jobs queued', [
            'total_queued' => $queued,
            'batch_size' => $batchSize,
            'force' => $force,
        ]);

        return Command::SUCCESS;
    }
}