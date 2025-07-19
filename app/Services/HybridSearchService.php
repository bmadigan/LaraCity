<?php

namespace App\Services;

use App\Models\DocumentEmbedding;
use App\Models\Complaint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class HybridSearchService
{
    public function __construct(
        private VectorEmbeddingService $embeddingService,
        private PythonAiBridge $pythonBridge
    ) {}

    /**
     * Perform hybrid search combining vector similarity and metadata filtering
     */
    public function search(string $query, array $filters = [], array $options = []): array
    {
        $startTime = microtime(true);
        
        // Default options
        $options = array_merge([
            'vector_weight' => 0.7,
            'metadata_weight' => 0.3,
            'similarity_threshold' => 0.7,
            'limit' => 20,
            'include_fallback' => true,
        ], $options);

        try {
            Log::info('Starting hybrid search', [
                'query' => $query,
                'filters' => $filters,
                'options' => $options
            ]);

            // Step 1: Vector similarity search
            $vectorResults = $this->vectorSimilaritySearch($query, $options);
            
            // Step 2: Metadata-based search
            $metadataResults = $this->metadataSearch($query, $filters, $options);
            
            // Step 3: Combine and rank results
            $combinedResults = $this->combineResults($vectorResults, $metadataResults, $options);
            
            // Step 4: Enhance with complaint data
            $enhancedResults = $this->enhanceWithComplaintData($combinedResults);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('Hybrid search completed', [
                'query' => $query,
                'vector_results_count' => count($vectorResults),
                'metadata_results_count' => count($metadataResults),
                'final_results_count' => count($enhancedResults),
                'duration_ms' => $duration
            ]);

            return [
                'results' => $enhancedResults,
                'metadata' => [
                    'query' => $query,
                    'total_results' => count($enhancedResults),
                    'vector_results' => count($vectorResults),
                    'metadata_results' => count($metadataResults),
                    'search_duration_ms' => $duration,
                    'filters_applied' => $filters,
                    'options_used' => $options
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Hybrid search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to metadata search only
            if ($options['include_fallback']) {
                return $this->fallbackSearch($query, $filters, $options);
            }

            throw $e;
        }
    }

    /**
     * Vector similarity search using embeddings
     */
    private function vectorSimilaritySearch(string $query, array $options): array
    {
        try {
            // Generate embedding for the query
            $embeddingData = $this->pythonBridge->generateEmbedding($query);
            
            if (!$embeddingData || empty($embeddingData['embedding'])) {
                Log::warning('Failed to generate query embedding for vector search');
                return [];
            }

            // Search similar documents
            $results = DocumentEmbedding::similarTo(
                $embeddingData['embedding'],
                $options['similarity_threshold'],
                $options['limit']
            )->get();

            return $results->map(function ($embedding) {
                return [
                    'type' => 'vector',
                    'embedding_id' => $embedding->id,
                    'document_type' => $embedding->document_type,
                    'document_id' => $embedding->document_id,
                    'content' => $embedding->content,
                    'similarity' => $embedding->similarity ?? 0,
                    'metadata' => $embedding->metadata,
                    'source' => 'vector_search'
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::warning('Vector similarity search failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Metadata-based search using traditional query methods
     */
    private function metadataSearch(string $query, array $filters, array $options): array
    {
        try {
            $complaintQuery = Complaint::query();

            // Apply text search on complaint fields
            $complaintQuery->where(function ($q) use ($query) {
                $q->where('complaint_type', 'ILIKE', "%{$query}%")
                  ->orWhere('descriptor', 'ILIKE', "%{$query}%")
                  ->orWhere('incident_address', 'ILIKE', "%{$query}%")
                  ->orWhere('agency_name', 'ILIKE', "%{$query}%");
            });

            // Apply filters
            if (!empty($filters['borough'])) {
                $complaintQuery->where('borough', $filters['borough']);
            }

            if (!empty($filters['complaint_type'])) {
                $complaintQuery->where('complaint_type', 'ILIKE', "%{$filters['complaint_type']}%");
            }

            if (!empty($filters['status'])) {
                $complaintQuery->where('status', $filters['status']);
            }

            if (!empty($filters['agency'])) {
                $complaintQuery->where('agency', $filters['agency']);
            }

            if (!empty($filters['date_from'])) {
                $complaintQuery->where('submitted_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $complaintQuery->where('submitted_at', '<=', $filters['date_to']);
            }

            // Risk-based filtering if analysis exists
            if (!empty($filters['risk_level'])) {
                $complaintQuery->whereHas('analysis', function ($q) use ($filters) {
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

            $complaints = $complaintQuery
                ->with(['analysis'])
                ->limit($options['limit'])
                ->get();

            return $complaints->map(function ($complaint) use ($query) {
                // Calculate simple text relevance score
                $relevance = $this->calculateTextRelevance($complaint, $query);

                return [
                    'type' => 'metadata',
                    'complaint_id' => $complaint->id,
                    'document_type' => 'complaint',
                    'document_id' => $complaint->id,
                    'content' => $this->formatComplaintForSearch($complaint),
                    'relevance' => $relevance,
                    'complaint' => $complaint,
                    'source' => 'metadata_search'
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::warning('Metadata search failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Combine vector and metadata results with weighted scoring
     */
    private function combineResults(array $vectorResults, array $metadataResults, array $options): array
    {
        $combined = [];
        $seenDocuments = [];

        // Add vector results with weighted scoring
        foreach ($vectorResults as $result) {
            $key = $result['document_type'] . '_' . $result['document_id'];
            
            if (!isset($seenDocuments[$key])) {
                $result['combined_score'] = $result['similarity'] * $options['vector_weight'];
                $combined[] = $result;
                $seenDocuments[$key] = true;
            }
        }

        // Add metadata results, combining scores if document already exists
        foreach ($metadataResults as $result) {
            $key = $result['document_type'] . '_' . $result['document_id'];
            
            if (isset($seenDocuments[$key])) {
                // Find existing result and boost score
                foreach ($combined as &$existingResult) {
                    if ($existingResult['document_type'] === $result['document_type'] && 
                        $existingResult['document_id'] === $result['document_id']) {
                        $existingResult['combined_score'] += $result['relevance'] * $options['metadata_weight'];
                        $existingResult['sources'][] = 'metadata_search';
                        break;
                    }
                }
            } else {
                $result['combined_score'] = $result['relevance'] * $options['metadata_weight'];
                $result['sources'] = ['metadata_search'];
                $combined[] = $result;
                $seenDocuments[$key] = true;
            }
        }

        // Sort by combined score
        usort($combined, function ($a, $b) {
            return $b['combined_score'] <=> $a['combined_score'];
        });

        return array_slice($combined, 0, $options['limit']);
    }

    /**
     * Enhance results with full complaint data
     */
    private function enhanceWithComplaintData(array $results): array
    {
        foreach ($results as &$result) {
            if ($result['document_type'] === 'complaint' && !isset($result['complaint'])) {
                $complaint = Complaint::with(['analysis'])->find($result['document_id']);
                
                if ($complaint) {
                    $result['complaint'] = [
                        'id' => $complaint->id,
                        'complaint_number' => $complaint->complaint_number,
                        'type' => $complaint->complaint_type,
                        'description' => $complaint->descriptor,
                        'borough' => $complaint->borough,
                        'address' => $complaint->incident_address,
                        'agency' => $complaint->agency_name,
                        'status' => $complaint->status,
                        'submitted_at' => $complaint->submitted_at?->toISOString(),
                        'analysis' => $complaint->analysis ? [
                            'summary' => $complaint->analysis->summary,
                            'risk_score' => $complaint->analysis->risk_score,
                            'category' => $complaint->analysis->category,
                            'tags' => $complaint->analysis->tags,
                        ] : null
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Calculate text relevance score for metadata search
     */
    private function calculateTextRelevance(Complaint $complaint, string $query): float
    {
        $query = strtolower($query);
        $score = 0.0;

        // Check complaint type (highest weight)
        if (str_contains(strtolower($complaint->complaint_type), $query)) {
            $score += 0.4;
        }

        // Check description
        if (str_contains(strtolower($complaint->descriptor ?? ''), $query)) {
            $score += 0.3;
        }

        // Check address
        if (str_contains(strtolower($complaint->incident_address ?? ''), $query)) {
            $score += 0.2;
        }

        // Check agency
        if (str_contains(strtolower($complaint->agency_name ?? ''), $query)) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * Format complaint data for search display
     */
    private function formatComplaintForSearch(Complaint $complaint): string
    {
        $parts = [
            "TYPE: {$complaint->complaint_type}",
            "DESCRIPTION: {$complaint->descriptor}",
            "LOCATION: {$complaint->borough}, {$complaint->incident_address}",
            "AGENCY: {$complaint->agency_name}",
            "STATUS: {$complaint->status}",
        ];

        if ($complaint->analysis) {
            $parts[] = "SUMMARY: {$complaint->analysis->summary}";
        }

        return implode(" | ", $parts);
    }

    /**
     * Fallback search using only metadata when vector search fails
     */
    private function fallbackSearch(string $query, array $filters, array $options): array
    {
        Log::info('Using fallback search (metadata only)', [
            'query' => $query
        ]);

        $metadataResults = $this->metadataSearch($query, $filters, $options);
        $enhancedResults = $this->enhanceWithComplaintData($metadataResults);

        return [
            'results' => $enhancedResults,
            'metadata' => [
                'query' => $query,
                'total_results' => count($enhancedResults),
                'search_mode' => 'fallback_metadata_only',
                'filters_applied' => $filters
            ]
        ];
    }
}