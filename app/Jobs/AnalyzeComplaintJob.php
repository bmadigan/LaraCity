<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Services\PythonAiBridge;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeComplaintJob implements ShouldQueue
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
        $this->tries = config('complaints.jobs.analyze_tries', 3);
        $this->timeout = config('complaints.jobs.analyze_timeout', 120);
        $this->onQueue(config('complaints.queues.ai_analysis', 'ai-analysis'));
    }

    /**
     * Execute the job.
     */
    public function handle(PythonAiBridge $pythonBridge): void
    {
        Log::info('Starting AI analysis for complaint', [
            'complaint_id' => $this->complaint->id,
            'complaint_number' => $this->complaint->complaint_number,
        ]);

        try {
            // Skip if already analyzed
            if ($this->complaint->analysis()->exists()) {
                Log::info('Complaint already analyzed, skipping', [
                    'complaint_id' => $this->complaint->id,
                ]);
                return;
            }

            // Prepare data for Python AI analysis
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

            // Call Python AI bridge for analysis
            $analysisResult = $pythonBridge->analyzeComplaint($complaintData);

            // Create analysis record
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

            // Check if escalation is needed
            if ($analysis->risk_score >= config('complaints.escalate_threshold', 0.7)) {
                Log::info('High risk complaint detected, triggering escalation', [
                    'complaint_id' => $this->complaint->id,
                    'risk_score' => $analysis->risk_score,
                    'threshold' => config('complaints.escalate_threshold', 0.7),
                ]);

                // Dispatch escalation job
                FlagComplaintJob::dispatch($this->complaint, $analysis)
                    ->onQueue(config('complaints.queues.escalation', 'escalation'));
            }

        } catch (\Exception $e) {
            Log::error('AI analysis failed', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeComplaintJob failed permanently', [
            'complaint_id' => $this->complaint->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
