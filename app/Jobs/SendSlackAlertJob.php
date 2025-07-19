<?php

namespace App\Jobs;

use App\Models\Action;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Services\SlackNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSlackAlertJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;
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
    public function handle(SlackNotificationService $slackService): void
    {
        Log::info('Sending Slack alert for escalated complaint', [
            'complaint_id' => $this->complaint->id,
            'risk_score' => $this->analysis->risk_score,
        ]);

        try {
            $success = $slackService->sendEscalationAlert(
                $this->complaint,
                $this->analysis,
                $this->escalationAction
            );

            if ($success) {
                // Create notification action for audit trail
                Action::create([
                    'type' => Action::TYPE_NOTIFY,
                    'parameters' => [
                        'notification_type' => 'slack_alert',
                        'status' => 'sent',
                        'escalation_action_id' => $this->escalationAction->id,
                        'sent_at' => now()->toISOString(),
                        'risk_score' => $this->analysis->risk_score,
                    ],
                    'triggered_by' => 'system',
                    'complaint_id' => $this->complaint->id,
                ]);

                Log::info('Slack alert sent successfully', [
                    'complaint_id' => $this->complaint->id,
                ]);
            } else {
                throw new \RuntimeException('Slack notification service returned false');
            }

        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', [
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
        Log::error('SendSlackAlertJob failed permanently', [
            'complaint_id' => $this->complaint->id,
            'exception' => $exception->getMessage(),
        ]);

        // Create failed notification action for audit trail
        try {
            Action::create([
                'type' => Action::TYPE_NOTIFY,
                'parameters' => [
                    'notification_type' => 'slack_alert',
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'escalation_action_id' => $this->escalationAction->id,
                    'failed_at' => now()->toISOString(),
                ],
                'triggered_by' => 'system',
                'complaint_id' => $this->complaint->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log Slack notification failure', [
                'complaint_id' => $this->complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
