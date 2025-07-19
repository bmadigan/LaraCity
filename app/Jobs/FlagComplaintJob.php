<?php

namespace App\Jobs;

use App\Models\Action;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FlagComplaintJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries;
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Complaint $complaint,
        public ComplaintAnalysis $analysis
    ) {
        $this->tries = config('complaints.jobs.escalation_tries', 2);
        $this->timeout = config('complaints.jobs.escalation_timeout', 60);
        $this->onQueue(config('complaints.queues.escalation', 'escalation'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting complaint escalation process', [
            'complaint_id' => $this->complaint->id,
            'risk_score' => $this->analysis->risk_score,
            'category' => $this->analysis->category,
        ]);

        try {
            // Update complaint status to escalated
            $this->complaint->update([
                'status' => Complaint::STATUS_ESCALATED
            ]);

            // Create escalation action
            $escalationAction = Action::create([
                'type' => Action::TYPE_ESCALATE,
                'parameters' => [
                    'risk_score' => $this->analysis->risk_score,
                    'category' => $this->analysis->category,
                    'threshold' => config('complaints.escalate_threshold', 0.7),
                    'escalated_at' => now()->toISOString(),
                    'escalation_reason' => 'Automated escalation due to high risk score',
                    'complaint_type' => $this->complaint->complaint_type,
                    'borough' => $this->complaint->borough,
                    'agency' => $this->complaint->agency,
                ],
                'triggered_by' => 'system',
                'complaint_id' => $this->complaint->id,
            ]);

            Log::info('Complaint flagged and status updated', [
                'complaint_id' => $this->complaint->id,
                'action_id' => $escalationAction->id,
                'new_status' => $this->complaint->status,
            ]);

            // Dispatch Slack notification job
            SendSlackAlertJob::dispatch($this->complaint, $this->analysis, $escalationAction)
                ->onQueue(config('complaints.queues.notification', 'notification'))
                ->delay(now()->addSeconds(5));

            // Dispatch logging job
            LogComplaintEscalationJob::dispatch($this->complaint, $this->analysis, $escalationAction)
                ->onQueue(config('complaints.queues.notification', 'notification'))
                ->delay(now()->addSeconds(10));

        } catch (\Exception $e) {
            Log::error('Complaint escalation failed', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FlagComplaintJob failed permanently', [
            'complaint_id' => $this->complaint->id,
            'risk_score' => $this->analysis->risk_score,
            'exception' => $exception->getMessage(),
        ]);

        // Create failure action for audit trail
        try {
            Action::create([
                'type' => Action::TYPE_ESCALATE,
                'parameters' => [
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toISOString(),
                ],
                'triggered_by' => 'system',
                'complaint_id' => $this->complaint->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log escalation failure', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
