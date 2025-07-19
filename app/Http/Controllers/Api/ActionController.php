<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Complaints\CreateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EscalateComplaintsRequest;
use App\Http\Resources\ActionResource;
use App\Models\Action;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ActionController extends Controller
{
    /**
     * Escalate complaints based on IDs or filters
     */
    public function escalate(EscalateComplaintsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        
        try {
            DB::beginTransaction();
            
            $complaints = $this->getComplaintsToEscalate($validated);
            
            if ($complaints->isEmpty()) {
                return response()->json([
                    'message' => 'No complaints found matching the criteria',
                    'data' => [
                        'escalated_count' => 0,
                        'actions_created' => [],
                    ]
                ], 404);
            }
            
            $actions = [];
            $escalatedCount = 0;
            
            foreach ($complaints as $complaint) {
                // Skip if already escalated
                if ($complaint->status === Complaint::STATUS_ESCALATED) {
                    continue;
                }
                
                // Update complaint status
                $complaint->update(['status' => Complaint::STATUS_ESCALATED]);
                
                // Create escalation action
                $action = CreateAction::run(
                    Action::TYPE_ESCALATE,
                    [
                        'reason' => $validated['reason'],
                        'escalation_level' => $validated['escalation_level'],
                        'notification_sent' => $validated['send_notification'] ?? false,
                        'risk_score' => $complaint->analysis?->risk_score,
                        'escalated_at' => now()->toISOString(),
                        'escalated_by_user_id' => $userId,
                    ],
                    (string) $userId,
                    $complaint
                );
                
                $actions[] = $action;
                $escalatedCount++;
                
                // Create notification action if requested
                if ($validated['send_notification'] ?? false) {
                    CreateAction::run(
                        Action::TYPE_NOTIFY,
                        [
                            'notification_type' => 'escalation_alert',
                            'recipient' => $validated['escalation_level'],
                            'message' => "Complaint #{$complaint->complaint_number} escalated: {$validated['reason']}",
                            'complaint_id' => $complaint->id,
                            'escalation_level' => $validated['escalation_level'],
                        ],
                        'system',
                        $complaint
                    );
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => "Successfully escalated {$escalatedCount} complaints",
                'data' => [
                    'escalated_count' => $escalatedCount,
                    'actions_created' => ActionResource::collection($actions),
                    'escalation_level' => $validated['escalation_level'],
                    'reason' => $validated['reason'],
                    'notification_sent' => $validated['send_notification'] ?? false,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Escalation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get complaints to escalate based on request criteria
     */
    private function getComplaintsToEscalate(array $validated): \Illuminate\Database\Eloquent\Collection
    {
        if (!empty($validated['complaint_ids'])) {
            // Escalate specific complaints by ID
            return Complaint::with(['analysis'])
                ->whereIn('id', $validated['complaint_ids'])
                ->get();
        }
        
        if (!empty($validated['filters'])) {
            // Escalate complaints matching filters
            $query = Complaint::with(['analysis']);
            $filters = $validated['filters'];
            
            if (!empty($filters['borough'])) {
                $query->byBorough($filters['borough']);
            }
            
            if (!empty($filters['type'])) {
                $query->where('complaint_type', 'LIKE', '%' . $filters['type'] . '%');
            }
            
            if (!empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }
            
            if (!empty($filters['risk_level'])) {
                $query->whereHas('analysis', function (Builder $q) use ($filters) {
                    switch ($filters['risk_level']) {
                        case 'high':
                            $q->where('risk_score', '>=', 0.7);
                            break;
                        case 'medium':
                            $q->whereBetween('risk_score', [0.4, 0.69]);
                            break;
                        case 'low':
                            $q->where('risk_score', '<', 0.4);
                            break;
                    }
                });
            }
            
            // Limit to prevent mass escalation accidents
            return $query->limit(100)->get();
        }
        
        return collect();
    }
}