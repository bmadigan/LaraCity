<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Services\PythonAiBridge;
use App\Services\VectorEmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job for AI-powered complaint analysis and vector embedding generation.
 *
 * This job demonstrates Laravel's queue system enabling expensive AI operations
 * to run asynchronously. The design prioritizes reliability through retry logic
 * and graceful degradation - if embedding generation fails, we don't fail the
 * entire analysis since the core AI insights are still valuable.
 *
 * The risk-based escalation logic shows how AI insights can trigger automated
 * workflows, moving high-risk complaints through different processing pipelines.
 */
class AnalyzeComplaintJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries;
    public int $timeout;

    /**
     * Configure the job for reliable AI processing.
     *
     * The timeout and retry settings reflect the unpredictable nature of AI
     * operations - network issues or service overload can cause failures that
     * resolve on retry. The dedicated queue allows scaling AI workers separately.
     */
    public function __construct(
        public Complaint $complaint
    ) {
        $this->tries = config('complaints.jobs.analyze_tries', 3);
        $this->timeout = config('complaints.jobs.analyze_timeout', 120);
        $this->onQueue(config('complaints.queues.ai_analysis', 'ai-analysis'));
    }

    /**
     * Execute the comprehensive AI analysis workflow.
     *
     * This method orchestrates multiple AI operations: analysis, embedding
     * generation, and risk-based escalation. The idempotency check prevents
     * duplicate work when jobs are retried or accidentally queued multiple times.
     */
    public function handle(PythonAiBridge $pythonBridge, VectorEmbeddingService $embeddingService): void
    {
        Log::info('Starting AI analysis for complaint', [
            'complaint_id' => $this->complaint->id,
            'complaint_number' => $this->complaint->complaint_number,
        ]);

        try {
            // Idempotency check: avoid duplicate analysis for performance and cost
            if ($this->complaint->analysis()->exists()) {
                Log::info('Complaint already analyzed, skipping', [
                    'complaint_id' => $this->complaint->id,
                ]);
                return;
            }

            // Transform Eloquent model into AI-friendly data structure
            $complaintData = [
                'id' => $this->complaint->id,
                'complaint_number' => $this->complaint->complaint_number,
                'type' => $this->complaint->complaint_type,
                'description' => $this->complaint->descriptor,
                'agency' => $this->complaint->agency,
                'borough' => $this->complaint->borough,
                'address' => $this->complaint->incident_address,
                'status' => $this->complaint->status,
                'submitted_at' => $this->complaint->submitted_at?->toISOString(),
            ];

            // Delegate the heavy AI analysis to our Python bridge
            $analysisResult = $pythonBridge->analyzeComplaint($complaintData);

            // Persist AI insights for future use and audit trails
            $analysis = ComplaintAnalysis::create([
                'complaint_id' => $this->complaint->id,
                'summary' => $analysisResult['summary'] ?? 'Analysis completed via AI bridge',
                'risk_score' => $analysisResult['risk_score'] ?? 0.0,
                'category' => $analysisResult['category'] ?? 'General',
                'tags' => $analysisResult['tags'] ?? [],
            ]);

            Log::info('AI analysis completed successfully', [
                'complaint_id' => $this->complaint->id,
                'risk_score' => $analysis->risk_score,
                'category' => $analysis->category,
            ]);

            // Enable semantic search by generating vector embeddings
            // This is optional functionality that enhances search but doesn't break analysis
            try {
                $embedding = $embeddingService->generateEmbedding($this->complaint);
                
                if ($embedding) {
                    Log::info('Vector embedding generated for complaint', [
                        'complaint_id' => $this->complaint->id,
                        'embedding_id' => $embedding->id,
                        'dimension' => $embedding->embedding_dimension,
                    ]);
                } else {
                    Log::warning('Failed to generate vector embedding for complaint', [
                        'complaint_id' => $this->complaint->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Vector embedding generation failed', [
                    'complaint_id' => $this->complaint->id,
                    'error' => $e->getMessage(),
                ]);
                // Graceful degradation: embedding failure shouldn't break analysis
            }

            // Create searchable embeddings for AI-generated summaries
            // This allows users to search by the AI's interpretation, not just raw data
            if (!empty($analysis->summary)) {
                try {
                    $analysisEmbedding = $embeddingService->generateEmbedding($analysis);
                    
                    if ($analysisEmbedding) {
                        Log::info('Vector embedding generated for analysis', [
                            'complaint_id' => $this->complaint->id,
                            'analysis_id' => $analysis->id,
                            'embedding_id' => $analysisEmbedding->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Analysis embedding generation failed', [
                        'complaint_id' => $this->complaint->id,
                        'analysis_id' => $analysis->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Trigger automated escalation workflows for high-risk complaints
            // This demonstrates how AI insights can drive business process automation
            if ($analysis->risk_score >= config('complaints.escalate_threshold', 0.7)) {
                Log::info('High risk complaint detected, triggering escalation', [
                    'complaint_id' => $this->complaint->id,
                    'risk_score' => $analysis->risk_score,
                    'threshold' => config('complaints.escalate_threshold', 0.7),
                ]);

                // Queue escalation on a separate queue for different SLA requirements
                FlagComplaintJob::dispatch($this->complaint, $analysis)
                    ->onQueue(config('complaints.queues.escalation', 'escalation'));
            }

        } catch (\Exception $e) {
            Log::error('AI analysis failed', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger Laravel's retry mechanism
            throw $e;
        }
    }

    /**
     * Handle permanent job failure after all retries are exhausted.
     *
     * This provides visibility into systemic issues and allows for manual
     * intervention or alternative processing strategies when AI services
     * are consistently failing.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeComplaintJob failed permanently', [
            'complaint_id' => $this->complaint->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
