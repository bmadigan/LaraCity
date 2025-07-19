<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\UserQuestion;
use App\Models\ComplaintAnalysis;
use App\Models\DocumentEmbedding;
use App\Services\VectorEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lacity:generate-embeddings 
                          {--type=all : Type of documents to process (all, complaints, questions, analyses)}
                          {--batch-size=50 : Number of documents to process in each batch}
                          {--limit=0 : Maximum number of documents to process (0 = no limit)}
                          {--force : Regenerate embeddings even if they already exist}
                          {--dry-run : Show what would be processed without actually generating embeddings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate vector embeddings for complaints, user questions, and analyses';

    public function __construct(
        private VectorEmbeddingService $embeddingService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $batchSize = (int) $this->option('batch-size');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 LaraCity Vector Embedding Generator");
        $this->info("Type: {$type} | Batch Size: {$batchSize} | Limit: " . ($limit ?: 'No limit'));
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No embeddings will be generated");
        }

        $stats = [
            'processed' => 0,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0
        ];

        // Process based on type
        match ($type) {
            'complaints' => $this->processComplaints($stats, $batchSize, $limit, $force, $dryRun),
            'questions' => $this->processUserQuestions($stats, $batchSize, $limit, $force, $dryRun),
            'analyses' => $this->processAnalyses($stats, $batchSize, $limit, $force, $dryRun),
            'all' => $this->processAll($stats, $batchSize, $limit, $force, $dryRun),
            default => $this->error("Invalid type: {$type}. Must be one of: all, complaints, questions, analyses")
        };

        // Display final statistics
        $this->newLine();
        $this->info("📊 Final Statistics:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $stats['processed']],
                ['Embeddings Generated', $stats['generated']],
                ['Skipped (Already Exist)', $stats['skipped']],
                ['Failed', $stats['failed']],
                ['Success Rate', $stats['processed'] > 0 ? round(($stats['generated'] / $stats['processed']) * 100, 2) . '%' : '0%']
            ]
        );

        return Command::SUCCESS;
    }

    private function processComplaints(array &$stats, int $batchSize, int $limit, bool $force, bool $dryRun): void
    {
        $this->info("\n📋 Processing Complaints...");
        
        $query = Complaint::query();
        
        if (!$force) {
            // Only process complaints without embeddings
            $query->whereDoesntHave('embeddings', function ($q) {
                $q->where('document_type', DocumentEmbedding::TYPE_COMPLAINT);
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Found {$total} complaints to process");

        if ($dryRun) {
            $this->warn("DRY RUN: Would process {$total} complaints");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($complaints) use (&$stats, $bar) {
            foreach ($complaints as $complaint) {
                $stats['processed']++;
                
                try {
                    $embedding = $this->embeddingService->generateEmbedding($complaint);
                    
                    if ($embedding) {
                        $stats['generated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    Log::error("Failed to generate embedding for complaint", [
                        'complaint_id' => $complaint->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
    }

    private function processUserQuestions(array &$stats, int $batchSize, int $limit, bool $force, bool $dryRun): void
    {
        $this->info("\n❓ Processing User Questions...");
        
        $query = UserQuestion::query();
        
        if (!$force) {
            $query->whereDoesntHave('embeddings', function ($q) {
                $q->where('document_type', DocumentEmbedding::TYPE_USER_QUESTION);
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Found {$total} user questions to process");

        if ($dryRun) {
            $this->warn("DRY RUN: Would process {$total} user questions");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($questions) use (&$stats, $bar) {
            foreach ($questions as $question) {
                $stats['processed']++;
                
                try {
                    $embedding = $this->embeddingService->generateEmbedding($question);
                    
                    if ($embedding) {
                        $stats['generated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    Log::error("Failed to generate embedding for user question", [
                        'question_id' => $question->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
    }

    private function processAnalyses(array &$stats, int $batchSize, int $limit, bool $force, bool $dryRun): void
    {
        $this->info("\n🔍 Processing Complaint Analyses...");
        
        $query = ComplaintAnalysis::query()->whereNotNull('summary');
        
        if (!$force) {
            $query->whereDoesntHave('embeddings', function ($q) {
                $q->where('document_type', DocumentEmbedding::TYPE_ANALYSIS);
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Found {$total} analyses to process");

        if ($dryRun) {
            $this->warn("DRY RUN: Would process {$total} analyses");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($analyses) use (&$stats, $bar) {
            foreach ($analyses as $analysis) {
                $stats['processed']++;
                
                try {
                    $embedding = $this->embeddingService->generateEmbedding($analysis);
                    
                    if ($embedding) {
                        $stats['generated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    Log::error("Failed to generate embedding for analysis", [
                        'analysis_id' => $analysis->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
    }

    private function processAll(array &$stats, int $batchSize, int $limit, bool $force, bool $dryRun): void
    {
        $this->info("\n🌟 Processing All Document Types...");
        
        // Process in order: Complaints -> Analyses -> Questions
        $this->processComplaints($stats, $batchSize, $limit, $force, $dryRun);
        $this->processAnalyses($stats, $batchSize, $limit, $force, $dryRun);
        $this->processUserQuestions($stats, $batchSize, $limit, $force, $dryRun);
    }
}
