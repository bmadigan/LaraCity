<?php

namespace App\Observers;

use App\Jobs\AnalyzeComplaintJob;
use App\Models\Action;
use App\Models\Complaint;
use Illuminate\Support\Facades\Log;

class ComplaintObserver
{
    /**
     * Handle the Complaint "created" event.
     */
    public function created(Complaint $complaint): void
    {
        Log::info('New complaint created, triggering AI analysis', [
            'complaint_id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
            'type' => $complaint->complaint_type,
            'borough' => $complaint->borough,
        ]);

        // Log the complaint creation action
        Action::create([
            'type' => Action::TYPE_ANALYSIS_TRIGGERED,
            'parameters' => [
                'trigger' => 'complaint_created',
                'complaint_type' => $complaint->complaint_type,
                'borough' => $complaint->borough,
                'triggered_at' => now()->toISOString(),
            ],
            'triggered_by' => 'system',
            'complaint_id' => $complaint->id,
        ]);

        // Dispatch AI analysis job
        AnalyzeComplaintJob::dispatch($complaint)
            ->delay(now()->addSeconds(5)); // Small delay to ensure complaint is fully persisted
    }

    /**
     * Handle the Complaint "updated" event.
     */
    public function updated(Complaint $complaint): void
    {
        // Check if status changed to something that might need re-analysis
        if ($complaint->isDirty('status')) {
            $oldStatus = $complaint->getOriginal('status');
            $newStatus = $complaint->status;

            Log::info('Complaint status changed', [
                'complaint_id' => $complaint->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            // Log status change action
            Action::create([
                'type' => Action::TYPE_STATUS_CHANGE,
                'parameters' => [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_at' => now()->toISOString(),
                ],
                'triggered_by' => 'system', // Could be improved to track actual user
                'complaint_id' => $complaint->id,
            ]);

            // Re-analyze if status changed to Open (complaint was reopened)
            if ($newStatus === Complaint::STATUS_OPEN && $oldStatus !== Complaint::STATUS_OPEN) {
                Log::info('Complaint reopened, triggering re-analysis', [
                    'complaint_id' => $complaint->id,
                ]);

                AnalyzeComplaintJob::dispatch($complaint)
                    ->delay(now()->addSeconds(10));
            }
        }

        // Check if critical fields changed that might affect risk assessment
        $criticalFields = ['complaint_type', 'descriptor', 'borough', 'agency'];
        $criticalFieldsChanged = collect($criticalFields)->some(fn($field) => $complaint->isDirty($field));

        if ($criticalFieldsChanged && $complaint->status === Complaint::STATUS_OPEN) {
            Log::info('Critical complaint fields changed, triggering re-analysis', [
                'complaint_id' => $complaint->id,
                'changed_fields' => collect($criticalFields)->filter(fn($field) => $complaint->isDirty($field))->values(),
            ]);

            // Delete existing analysis to force fresh analysis
            $complaint->analysis()?->delete();

            AnalyzeComplaintJob::dispatch($complaint)
                ->delay(now()->addSeconds(15));
        }
    }

    /**
     * Handle the Complaint "deleted" event.
     */
    public function deleted(Complaint $complaint): void
    {
        Log::info('Complaint deleted', [
            'complaint_id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
        ]);

        // Log deletion action (if not soft delete, this might not persist)
        try {
            Action::create([
                'type' => Action::TYPE_COMPLAINT_DELETED,
                'parameters' => [
                    'complaint_number' => $complaint->complaint_number,
                    'complaint_type' => $complaint->complaint_type,
                    'deleted_at' => now()->toISOString(),
                ],
                'triggered_by' => 'system',
                'complaint_id' => $complaint->id,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log deletion action', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Complaint "restored" event.
     */
    public function restored(Complaint $complaint): void
    {
        Log::info('Complaint restored, triggering re-analysis', [
            'complaint_id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
        ]);

        Action::create([
            'type' => Action::TYPE_COMPLAINT_RESTORED,
            'parameters' => [
                'restored_at' => now()->toISOString(),
            ],
            'triggered_by' => 'system',
            'complaint_id' => $complaint->id,
        ]);

        // Re-analyze restored complaint
        AnalyzeComplaintJob::dispatch($complaint)
            ->delay(now()->addSeconds(5));
    }

    /**
     * Handle the Complaint "force deleted" event.
     */
    public function forceDeleted(Complaint $complaint): void
    {
        Log::warning('Complaint force deleted', [
            'complaint_id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
        ]);

        // Cannot log action as complaint is permanently deleted
        // This event is mainly for cleanup and logging
    }
}
