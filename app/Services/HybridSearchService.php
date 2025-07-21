<?php

namespace App\Services;

use App\Models\DocumentEmbedding;
use App\Models\Complaint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid search combining vector similarity with traditional metadata filtering.
 *
 * This service represents a key architectural decision: rather than relying solely
 * on either vector search or SQL queries, we combine both approaches to achieve
 * better relevance. Vector search excels at semantic understanding while metadata
 * filters provide precise categorical matching.
 *
 * The weighted scoring system allows fine-tuning the balance between semantic
 * relevance and exact matches based on the use case.
 */
class HybridSearchService
{
    /**
     * Inject dependencies to enable testing and flexibility.
     *
     * Constructor injection makes it easy to mock these services in tests
     * and allows for different implementations in different environments.
     */
    public function __construct(
        private VectorEmbeddingService $embeddingService,
        private PythonAiBridge $pythonBridge
    ) {}

    /**
     * Execute a hybrid search strategy combining multiple approaches.
     *
     * The key insight is that different search approaches excel at different
     * tasks: vector search for semantic similarity, metadata search for exact
     * matches. By combining them with configurable weights, we can optimize
     * for different use cases.
     */
    public function search(string $query, array $filters = [], array $options = []): array
    {
        $startTime = microtime(true);
        
        // Configurable weights allow tuning the search strategy
        // Higher vector weight favors semantic similarity over exact matches
        $options = array_merge([
            'vector_weight' => 0.7,      // Semantic understanding
            'metadata_weight' => 0.3,     // Exact field matches
            'similarity_threshold' => 0.7, // Quality gate for vector results
            'limit' => 20,
            'include_fallback' => true,    // Graceful degradation
        ], $options);

        try {
            Log::info('Starting hybrid search', [
                'query' => $query,
                'filters' => $filters,
                'options' => $options
            ]);

            // Execute parallel search strategies for maximum coverage
            $vectorResults = $this->vectorSimilaritySearch($query, $options);
            $metadataResults = $this->metadataSearch($query, $filters, $options);
            
            // Merge results using weighted scoring to balance relevance types
            $combinedResults = $this->combineResults($vectorResults, $metadataResults, $options);
            
            // Enrich results with full complaint data for UI display
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

            // Graceful degradation: fall back to simpler search when AI fails
            if ($options['include_fallback']) {
                return $this->fallbackSearch($query, $filters, $options);
            }

            throw $e;
        }
    }

    /**
     * Perform semantic search using vector embeddings.
     *
     * This searches by meaning rather than exact keywords. A query for "water"
     * might match complaints about "plumbing" or "leaks" based on semantic
     * similarity rather than literal text matching.
     */
    private function vectorSimilaritySearch(string $query, array $options): array
    {
        try {
            // Convert user query into the same vector space as our documents
            $embeddingData = $this->pythonBridge->generateEmbedding($query);
            
            if (!$embeddingData || empty($embeddingData['embedding'])) {
                Log::warning('Failed to generate query embedding for vector search');
                return [];
            }

            // Find documents with similar semantic meaning using cosine similarity
            $results = DocumentEmbedding::similarTo(
                $embeddingData['embedding'],
                $options['similarity_threshold'],
                $options['limit']
            )->get();

            // Structure results for consistent processing in the combination phase
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
     * Traditional keyword-based search with precise filtering.
     *
     * This complements vector search by providing exact matches and supporting
     * complex filtering that vector search can't handle well. The ILIKE queries
     * enable case-insensitive partial matching across key fields.
     */
    private function metadataSearch(string $query, array $filters, array $options): array
    {
        try {
            $complaintQuery = Complaint::query();

            // Search across key text fields using case-insensitive pattern matching
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

            // Apply AI-generated risk level filters when available
            // This demonstrates how traditional SQL can leverage AI insights
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

            // Transform SQL results into a format compatible with vector results
            return $complaints->map(function ($complaint) use ($query) {
                // Calculate relevance based on field-specific text matching
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
     * Merge and score results from multiple search strategies.
     *
     * The weighting system allows tuning between semantic understanding
     * (vector) and exact matching (metadata). Documents found by both
     * methods get boosted scores, reflecting higher confidence.
     */
    private function combineResults(array $vectorResults, array $metadataResults, array $options): array
    {
        $combined = [];
        $seenDocuments = [];

        // Process vector results first, applying semantic relevance weights
        foreach ($vectorResults as $result) {
            $key = $result['document_type'] . '_' . $result['document_id'];
            
            if (!isset($seenDocuments[$key])) {
                $result['combined_score'] = $result['similarity'] * $options['vector_weight'];
                $combined[] = $result;
                $seenDocuments[$key] = true;
            }
        }

        // Merge metadata results, boosting scores for documents found by both methods
        foreach ($metadataResults as $result) {
            $key = $result['document_type'] . '_' . $result['document_id'];
            
            if (isset($seenDocuments[$key])) {
                // Boost score for documents that match both semantically and literally
                foreach ($combined as &$existingResult) {
                    if ($existingResult['document_type'] === $result['document_type'] && 
                        $existingResult['document_id'] === $result['document_id']) {
                        $existingResult['combined_score'] += $result['relevance'] * $options['metadata_weight'];
                        $existingResult['sources'][] = 'metadata_search';
                        break;
                    }
                }
            } else {
                // Add metadata-only results with appropriate weighting
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
     * Enrich search results with complete complaint data for UI display.
     *
     * This lazy-loading approach fetches full records only for the final
     * result set, avoiding expensive joins during the search phase while
     * ensuring the UI has all necessary data.
     */
    private function enhanceWithComplaintData(array $results): array
    {
        foreach ($results as &$result) {
            if ($result['document_type'] === 'complaint' && !isset($result['complaint'])) {
                $complaint = Complaint::with(['analysis'])->find($result['document_id']);
                
                if ($complaint) {
                    // Structure the data for consistent API responses
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
     * Calculate relevance scores based on field-specific importance.
     *
     * This scoring system reflects the relative importance of different
     * fields: complaint type is most important for categorization, while
     * agency name provides context but is less critical for relevance.
     */
    private function calculateTextRelevance(Complaint $complaint, string $query): float
    {
        $query = strtolower($query);
        $score = 0.0;

        // Complaint type carries highest weight for classification accuracy
        if (str_contains(strtolower($complaint->complaint_type), $query)) {
            $score += 0.4;
        }

        // Description provides detailed context about the issue
        if (str_contains(strtolower($complaint->descriptor ?? ''), $query)) {
            $score += 0.3;
        }

        // Address enables location-based search
        if (str_contains(strtolower($complaint->incident_address ?? ''), $query)) {
            $score += 0.2;
        }

        // Agency provides administrative context
        if (str_contains(strtolower($complaint->agency_name ?? ''), $query)) {
            $score += 0.1;
        }

        return min(1.0, $score); // Cap at maximum relevance
    }

    /**
     * Create a unified text representation for search results.
     *
     * This formatting provides a consistent way to display search results
     * regardless of which search method found them, making the UI predictable.
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
     * Provide degraded but functional search when AI services fail.
     *
     * This fallback ensures our application remains usable even when
     * external dependencies are unavailable, maintaining core functionality
     * through traditional database queries.
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
                'search_mode' => 'fallback_metadata_only', // Signal degraded mode to UI
                'filters_applied' => $filters
            ]
        ];
    }
}