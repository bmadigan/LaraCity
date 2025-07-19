<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HybridSearchService;
use App\Services\VectorEmbeddingService;
use App\Models\DocumentEmbedding;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SemanticSearchController extends Controller
{
    public function __construct(
        private HybridSearchService $searchService,
        private VectorEmbeddingService $embeddingService
    ) {}

    /**
     * Perform semantic search across complaint documents
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3|max:500',
            'filters' => 'sometimes|array',
            'filters.borough' => 'sometimes|string|in:MANHATTAN,BROOKLYN,QUEENS,BRONX,STATEN ISLAND',
            'filters.complaint_type' => 'sometimes|string',
            'filters.status' => 'sometimes|string|in:Open,InProgress,Closed,Escalated',
            'filters.agency' => 'sometimes|string',
            'filters.risk_level' => 'sometimes|string|in:low,medium,high',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date|after_or_equal:filters.date_from',
            'options' => 'sometimes|array',
            'options.vector_weight' => 'sometimes|numeric|between:0,1',
            'options.metadata_weight' => 'sometimes|numeric|between:0,1',
            'options.similarity_threshold' => 'sometimes|numeric|between:0,1',
            'options.limit' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $results = $this->searchService->search(
                $validated['query'],
                $validated['filters'] ?? [],
                $validated['options'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => "Found {$results['metadata']['total_results']} results"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Search similar documents by embedding
     */
    public function similar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3|max:500',
            'document_type' => ['sometimes', 'string', Rule::in([
                DocumentEmbedding::TYPE_COMPLAINT,
                DocumentEmbedding::TYPE_USER_QUESTION,
                DocumentEmbedding::TYPE_ANALYSIS
            ])],
            'threshold' => 'sometimes|numeric|between:0,1',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $results = $this->embeddingService->searchSimilar(
                $validated['query'],
                $validated['document_type'] ?? null,
                $validated['threshold'] ?? 0.7,
                $validated['limit'] ?? 10
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'metadata' => [
                        'query' => $validated['query'],
                        'total_results' => count($results),
                        'search_type' => 'vector_similarity',
                        'threshold' => $validated['threshold'] ?? 0.7,
                        'document_type' => $validated['document_type'] ?? 'all'
                    ]
                ],
                'message' => "Found " . count($results) . " similar documents"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Similarity search failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Generate and store embedding for provided text
     */
    public function embed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:10|max:2000',
            'metadata' => 'sometimes|array',
        ]);

        try {
            // For API usage, we'll create a temporary document embedding
            $embedding = new DocumentEmbedding([
                'document_type' => 'api_query',
                'document_id' => null,
                'document_hash' => DocumentEmbedding::createContentHash($validated['text']),
                'content' => $validated['text'],
                'metadata' => $validated['metadata'] ?? [],
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimension' => 1536,
            ]);

            // Generate the actual embedding
            $embeddingResult = $this->embeddingService->generateEmbedding($embedding, $validated['text']);

            if (!$embeddingResult) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to generate embedding',
                    'data' => null
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'embedding_id' => $embeddingResult->id,
                    'text' => $validated['text'],
                    'embedding_model' => $embeddingResult->embedding_model,
                    'dimension' => $embeddingResult->embedding_dimension,
                    'content_hash' => $embeddingResult->document_hash,
                    'created_at' => $embeddingResult->created_at
                ],
                'message' => 'Embedding generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Embedding generation failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get semantic search statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_embeddings' => DocumentEmbedding::count(),
                'by_type' => DocumentEmbedding::selectRaw('document_type, count(*) as count')
                    ->groupBy('document_type')
                    ->pluck('count', 'document_type'),
                'by_model' => DocumentEmbedding::selectRaw('embedding_model, count(*) as count')
                    ->groupBy('embedding_model')
                    ->pluck('count', 'embedding_model'),
                'dimensions' => DocumentEmbedding::select('embedding_dimension')
                    ->distinct()
                    ->pluck('embedding_dimension'),
                'recent_activity' => DocumentEmbedding::where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'oldest_embedding' => DocumentEmbedding::oldest()->first()?->created_at,
                'newest_embedding' => DocumentEmbedding::latest()->first()?->created_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Vector store statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve statistics: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Test semantic search functionality
     */
    public function test(): JsonResponse
    {
        try {
            $testQueries = [
                'noise complaint',
                'water leak',
                'street repair',
                'parking violation'
            ];

            $results = [];
            foreach ($testQueries as $query) {
                $searchResults = $this->searchService->search($query, [], ['limit' => 3]);
                
                $results[] = [
                    'query' => $query,
                    'results_count' => $searchResults['metadata']['total_results'],
                    'search_duration_ms' => $searchResults['metadata']['search_duration_ms'] ?? 0,
                    'sample_results' => array_slice($searchResults['results'], 0, 2)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'test_results' => $results,
                    'total_embeddings' => DocumentEmbedding::count(),
                    'test_completed_at' => now()->toISOString()
                ],
                'message' => 'Semantic search test completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Test failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
