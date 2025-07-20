<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Services\VectorEmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries;
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Complaint $complaint
    ) {
        $this->tries = config('complaints.jobs.embedding_tries', 3);
        $this->timeout = config('complaints.jobs.embedding_timeout', 60);
        $this->onQueue(config('complaints.queues.embeddings', 'embeddings'));
    }

    /**
     * Execute the job.
     */
    public function handle(VectorEmbeddingService $embeddingService): void
    {
        Log::info('Starting embedding generation for complaint', [
            'complaint_id' => $this->complaint->id,
            'complaint_number' => $this->complaint->complaint_number,
        ]);

        try {
            // Check if embedding already exists
            if ($this->complaint->embeddings()->exists()) {
                Log::info('Complaint already has embedding, skipping', [
                    'complaint_id' => $this->complaint->id,
                ]);
                return;
            }

            // Generate vector embedding
            $embedding = $embeddingService->generateEmbedding($this->complaint);
            
            if ($embedding) {
                Log::info('Vector embedding generated successfully', [
                    'complaint_id' => $this->complaint->id,
                    'embedding_id' => $embedding->id,
                    'dimension' => $embedding->embedding_dimension,
                ]);
            } else {
                Log::warning('Vector embedding generation returned null', [
                    'complaint_id' => $this->complaint->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate embedding for complaint', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't fail the job for embedding issues - they can be retried later
            // throw $e; // Uncomment if you want job to fail and retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateEmbeddingJob failed', [
            'complaint_id' => $this->complaint->id,
            'error' => $exception->getMessage(),
        ]);
    }
}