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

class LogComplaintEscalationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Complaint $complaint,
        public ComplaintAnalysis $analysis,
        public Action $escalationAction
    ) {
        $this->onQueue(config('complaints.queues.notification', 'notification'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Creating comprehensive escalation log entry', [
            'complaint_id' => $this->complaint->id,
            'escalation_action_id' => $this->escalationAction->id,
        ]);

        try {
            // Create comprehensive escalation log action
            $logAction = Action::create([
                'type' => Action::TYPE_ANALYZE,
                'parameters' => [
                    'log_type' => 'escalation_summary',
                    'escalation_action_id' => $this->escalationAction->id,
                    'escalation_workflow' => [
                        'ai_analysis_completed' => true,
                        'risk_threshold_exceeded' => true,
                        'complaint_flagged' => true,
                        'slack_notification_triggered' => true,
                        'comprehensive_log_created' => true,
                    ],
                    'complaint_details' => [
                        'complaint_number' => $this->complaint->complaint_number,
                        'type' => $this->complaint->complaint_type,
                        'borough' => $this->complaint->borough,
                        'agency' => $this->complaint->agency,
                        'address' => $this->complaint->incident_address,
                        'submitted_at' => $this->complaint->submitted_at?->toISOString(),
                        'status' => $this->complaint->status,
                    ],
                    'analysis_results' => [
                        'risk_score' => $this->analysis->risk_score,
                        'category' => $this->analysis->category,
                        'tags' => $this->analysis->tags,
                        'summary' => $this->analysis->summary,
                        'fallback_used' => $this->analysis->fallback ?? false,
                    ],
                    'escalation_metrics' => [
                        'threshold_used' => config('complaints.escalate_threshold', 0.7),
                        'risk_level' => $this->getRiskLevel($this->analysis->risk_score),
                        'escalation_time' => now()->toISOString(),
                        'workflow_duration' => now()->diffInSeconds($this->complaint->created_at),
                    ],
                    'system_state' => [
                        'queue_used' => config('complaints.queues.escalation', 'escalation'),
                        'php_version' => PHP_VERSION,
                        'laravel_version' => app()->version(),
                    ],
                ],
                'triggered_by' => 'system',
                'complaint_id' => $this->complaint->id,
            ]);

            // Log structured escalation summary for monitoring
            Log::info('ESCALATION_WORKFLOW_COMPLETED', [
                'complaint_id' => $this->complaint->id,
                'complaint_number' => $this->complaint->complaint_number,
                'risk_score' => $this->analysis->risk_score,
                'risk_level' => $this->getRiskLevel($this->analysis->risk_score),
                'category' => $this->analysis->category,
                'borough' => $this->complaint->borough,
                'agency' => $this->complaint->agency,
                'escalation_action_id' => $this->escalationAction->id,
                'log_action_id' => $logAction->id,
                'threshold' => config('complaints.escalate_threshold', 0.7),
                'workflow_completed_at' => now()->toISOString(),
                'total_actions_created' => Action::where('complaint_id', $this->complaint->id)->count(),
            ]);

            // Log analytics data for dashboard/reporting
            Log::info('ESCALATION_ANALYTICS', [
                'event_type' => 'complaint_escalated',
                'complaint_type' => $this->complaint->complaint_type,
                'borough' => $this->complaint->borough,
                'agency' => $this->complaint->agency,
                'risk_score' => $this->analysis->risk_score,
                'category' => $this->analysis->category,
                'tags' => $this->analysis->tags,
                'day_of_week' => now()->format('l'),
                'hour_of_day' => now()->format('H'),
                'escalated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create escalation log', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
            ]);

            // Don't rethrow - logging failure shouldn't break escalation workflow
        }
    }

    /**
     * Get risk level label based on score
     */
    private function getRiskLevel(float $riskScore): string
    {
        if ($riskScore >= 0.9) return 'CRITICAL';
        if ($riskScore >= 0.8) return 'HIGH';
        if ($riskScore >= 0.7) return 'ELEVATED';
        if ($riskScore >= 0.4) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::warning('LogComplaintEscalationJob failed - non-critical', [
            'complaint_id' => $this->complaint->id,
            'exception' => $exception->getMessage(),
        ]);

        // Logging failure is non-critical, so we just log the failure
        // The escalation workflow can continue without comprehensive logging
    }
}
