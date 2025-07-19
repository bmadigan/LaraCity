<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ComplaintFilterRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    /**
     * Display a paginated listing of complaints with filtering
     */
    public function index(ComplaintFilterRequest $request): AnonymousResourceCollection
    {
        $query = $this->buildFilteredQuery($request);
        
        $pagination = $request->getPagination();
        $sorting = $request->getSorting();
        
        // Apply sorting
        $query = $this->applySorting($query, $sorting);
        
        $complaints = $query->with(['analysis'])
            ->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return ComplaintResource::collection($complaints);
    }

    /**
     * Display aggregated summary statistics
     */
    public function summary(ComplaintFilterRequest $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);
        
        // Use same filtered query for consistent totals
        $baseQuery = clone $query;
        
        $summary = [
            'total_complaints' => $baseQuery->count(),
            'by_status' => $this->getStatusBreakdown(clone $query),
            'by_priority' => $this->getPriorityBreakdown(clone $query),
            'by_borough' => $this->getBoroughBreakdown(clone $query),
            'by_agency' => $this->getAgencyBreakdown(clone $query),
            'risk_analysis' => $this->getRiskAnalysisBreakdown(clone $query),
            'date_range' => $this->getDateRangeStats(clone $query),
        ];

        return response()->json([
            'data' => $summary,
            'filters_applied' => $request->getFilters(),
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Display the specified complaint with analysis
     */
    public function show(Complaint $complaint): ComplaintResource
    {
        $complaint->load(['analysis', 'actions']);
        
        return new ComplaintResource($complaint);
    }

    /**
     * Build filtered query based on request parameters
     */
    private function buildFilteredQuery(ComplaintFilterRequest $request): Builder
    {
        $query = Complaint::query();
        $filters = $request->getFilters();

        // Borough filter
        if (!empty($filters['borough'])) {
            $query->byBorough($filters['borough']);
        }

        // Complaint type filter
        if (!empty($filters['type'])) {
            $query->where('complaint_type', 'LIKE', '%' . $filters['type'] . '%');
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        // Agency filter
        if (!empty($filters['agency'])) {
            $query->where('agency', $filters['agency']);
        }

        // Date range filter
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $dateFrom = $filters['date_from'] ?? '1900-01-01';
            $dateTo = $filters['date_to'] ?? now()->format('Y-m-d');
            $query->byDateRange($dateFrom, $dateTo);
        }

        // Risk level filter (requires analysis relationship)
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

        return $query;
    }

    /**
     * Apply sorting to query
     */
    private function applySorting(Builder $query, array $sorting): Builder
    {
        $sortBy = $sorting['sort_by'];
        $direction = $sorting['sort_direction'];

        // Handle special sorting cases
        if ($sortBy === 'risk_score') {
            // Sort by risk score from analysis table
            return $query->leftJoin('complaint_analyses', 'complaints.id', '=', 'complaint_analyses.complaint_id')
                ->orderBy('complaint_analyses.risk_score', $direction)
                ->select('complaints.*');
        }

        return $query->orderBy($sortBy, $direction);
    }

    /**
     * Get status breakdown
     */
    private function getStatusBreakdown(Builder $query): array
    {
        return $query->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get priority breakdown
     */
    private function getPriorityBreakdown(Builder $query): array
    {
        return $query->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();
    }

    /**
     * Get borough breakdown
     */
    private function getBoroughBreakdown(Builder $query): array
    {
        return $query->selectRaw('borough, count(*) as count')
            ->whereNotNull('borough')
            ->groupBy('borough')
            ->orderByDesc('count')
            ->pluck('count', 'borough')
            ->toArray();
    }

    /**
     * Get agency breakdown (top 10)
     */
    private function getAgencyBreakdown(Builder $query): array
    {
        return $query->selectRaw('agency, agency_name, count(*) as count')
            ->groupBy('agency', 'agency_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->agency => [
                    'name' => $item->agency_name,
                    'count' => $item->count,
                ]];
            })
            ->toArray();
    }

    /**
     * Get risk analysis breakdown
     */
    private function getRiskAnalysisBreakdown(Builder $query): array
    {
        $analysisQuery = $query->join('complaint_analyses', 'complaints.id', '=', 'complaint_analyses.complaint_id');
        
        return [
            'total_analyzed' => $analysisQuery->count(),
            'high_risk' => (clone $analysisQuery)->where('risk_score', '>=', 0.7)->count(),
            'medium_risk' => (clone $analysisQuery)->whereBetween('risk_score', [0.4, 0.69])->count(),
            'low_risk' => (clone $analysisQuery)->where('risk_score', '<', 0.4)->count(),
            'average_risk_score' => round((clone $analysisQuery)->avg('risk_score'), 3),
        ];
    }

    /**
     * Get date range statistics
     */
    private function getDateRangeStats(Builder $query): array
    {
        $stats = $query->selectRaw('
            MIN(submitted_at) as earliest,
            MAX(submitted_at) as latest,
            COUNT(*) as total
        ')->first();

        return [
            'earliest_complaint' => $stats->earliest?->toISOString(),
            'latest_complaint' => $stats->latest?->toISOString(),
            'total_complaints' => $stats->total,
            'date_span_days' => $stats->earliest && $stats->latest 
                ? $stats->earliest->diffInDays($stats->latest)
                : 0,
        ];
    }
}