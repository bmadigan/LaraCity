<?php

namespace App\Services;

use App\Models\DocumentEmbedding;
use App\Models\Complaint;
use App\Models\UserQuestion;
use App\Models\ComplaintAnalysis;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

/**
 * Vector embedding service enabling semantic search across different document types.
 *
 * This service abstracts the complexity of converting various Laravel models into
 * searchable vector representations. The key insight is that different document
 * types require different content extraction strategies, but they all share the
 * same underlying embedding and search mechanics.
 *
 * We use content hashing to avoid regenerating embeddings for identical content,
 * which is crucial for performance when dealing with large datasets.
 */
class VectorEmbeddingService
{
    /**
     * Dependency injection allows us to mock the Python bridge in tests
     * and swap implementations without changing this service.
     */
    public function __construct(
        private PythonAiBridge $pythonBridge
    ) {}

    /**
     * Transform any Laravel model into a searchable vector embedding.
     *
     * This method demonstrates the adapter pattern: we extract content from
     * different model types using type-specific logic, then apply the same
     * embedding generation process. The content hashing prevents duplicate
     * work when multiple documents have identical content.
     */
    public function generateEmbedding(Model $document, ?string $customContent = null): ?DocumentEmbedding
    {
        try {
            // Extract searchable content using document-specific formatting
            $documentData = $this->extractDocumentData($document, $customContent);
            
            if (empty($documentData['content'])) {
                Log::warning("No content found for embedding generation", [
                    'document_type' => $documentData['type'],
                    'document_id' => $document->id
                ]);
                return null;
            }

            // Avoid expensive AI calls by reusing embeddings for identical content
            $contentHash = DocumentEmbedding::createContentHash($documentData['content']);
            $existingEmbedding = DocumentEmbedding::findByContentHash($contentHash);
            
            if ($existingEmbedding) {
                Log::info("Using existing embedding for document", [
                    'document_type' => $documentData['type'],
                    'document_id' => $document->id,
                    'embedding_id' => $existingEmbedding->id
                ]);
                return $existingEmbedding;
            }

            // Delegate to Python for the heavy AI lifting
            $embeddingData = $this->pythonBridge->generateEmbedding($documentData['content']);
            
            if (!$embeddingData || empty($embeddingData['embedding'])) {
                Log::error("Failed to generate embedding", [
                    'document_type' => $documentData['type'],
                    'document_id' => $document->id
                ]);
                return null;
            }

            // Persist the vector representation for future searches
            $embedding = DocumentEmbedding::create([
                'document_type' => $documentData['type'],
                'document_id' => $document->id,
                'document_hash' => $contentHash,
                'content' => $documentData['content'],
                'metadata' => $documentData['metadata'],
                'embedding_model' => $embeddingData['model'] ?? 'text-embedding-3-small',
                'embedding_dimension' => count($embeddingData['embedding']),
                'embedding' => '[' . implode(',', $embeddingData['embedding']) . ']',
            ]);

            Log::info("Generated and stored embedding", [
                'document_type' => $documentData['type'],
                'document_id' => $document->id,
                'embedding_id' => $embedding->id,
                'dimension' => $embedding->embedding_dimension
            ]);

            return $embedding;

        } catch (\Exception $e) {
            Log::error("Error generating embedding", [
                'document_type' => get_class($document),
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find documents with similar semantic meaning to a query.
     *
     * This is where the magic happens: we convert the user's natural language
     * query into the same vector space as our documents, then use mathematical
     * similarity (typically cosine distance) to find semantically related content.
     */
    public function searchSimilar(string $query, string $documentType = null, float $threshold = 0.8, int $limit = 10): array
    {
        try {
            // Transform the search query into the same vector space as our documents
            $queryEmbedding = $this->pythonBridge->generateEmbedding($query);
            
            if (!$queryEmbedding || empty($queryEmbedding['embedding'])) {
                Log::error("Failed to generate query embedding", ['query' => $query]);
                return [];
            }

            // Execute the vector similarity search using database-stored embeddings
            $embeddingModel = $queryEmbedding['model'] ?? 'text-embedding-3-small';
            $results = DocumentEmbedding::searchSimilar(
                $query,
                $embeddingModel,
                $queryEmbedding['embedding'],
                $documentType,
                $threshold,
                $limit
            );

            // Enrich results with full model data for UI consumption
            $formattedResults = [];
            foreach ($results as $embedding) {
                $relatedDocument = $this->loadRelatedDocument($embedding);
                
                $formattedResults[] = [
                    'embedding_id' => $embedding->id,
                    'similarity' => $embedding->similarity ?? 0,
                    'content' => $embedding->content,
                    'document_type' => $embedding->document_type,
                    'document_id' => $embedding->document_id,
                    'document' => $relatedDocument,
                    'metadata' => $embedding->metadata,
                ];
            }

            Log::info("Vector search completed", [
                'query' => $query,
                'document_type' => $documentType,
                'results_count' => count($formattedResults),
                'threshold' => $threshold
            ]);

            return $formattedResults;

        } catch (\Exception $e) {
            Log::error("Error in vector search", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process large document collections efficiently with rate limiting.
     *
     * Batch processing is essential for initial data imports and periodic
     * updates. The rate limiting prevents overwhelming external AI services
     * and ensures we stay within API quotas.
     */
    public function generateBatchEmbeddings(array $documents, int $batchSize = 10): array
    {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $batches = array_chunk($documents, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $document) {
                $results['processed']++;
                
                $embedding = $this->generateEmbedding($document);
                
                if ($embedding) {
                    $results['succeeded']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'document_type' => get_class($document),
                        'document_id' => $document->id,
                        'error' => 'Failed to generate embedding'
                    ];
                }
            }
            
            // Respectful rate limiting to avoid overwhelming AI service quotas
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Transform different Laravel models into a unified searchable format.
     *
     * This method implements the strategy pattern: each document type has its
     * own content extraction logic, but they all conform to the same interface.
     * The metadata captures structured information for filtering and faceting.
     */
    private function extractDocumentData(Model $document, ?string $customContent = null): array
    {
        if ($customContent) {
            return [
                'type' => strtolower(class_basename($document)),
                'content' => $customContent,
                'metadata' => [
                    'source' => 'custom_content',
                    'document_id' => $document->id
                ]
            ];
        }

        // Map each document type to its optimal search representation
        return match (get_class($document)) {
            Complaint::class => [
                'type' => DocumentEmbedding::TYPE_COMPLAINT,
                'content' => $this->formatComplaintContent($document),
                'metadata' => [
                    'borough' => $document->borough,
                    'complaint_type' => $document->complaint_type,
                    'agency' => $document->agency,
                    'status' => $document->status,
                    'submitted_at' => $document->submitted_at?->toISOString(),
                ]
            ],
            UserQuestion::class => [
                'type' => DocumentEmbedding::TYPE_USER_QUESTION,
                'content' => $document->question,
                'metadata' => [
                    'conversation_id' => $document->conversation_id,
                    'parsed_filters' => $document->parsed_filters,
                    'asked_at' => $document->created_at?->toISOString(),
                ]
            ],
            ComplaintAnalysis::class => [
                'type' => DocumentEmbedding::TYPE_ANALYSIS,
                'content' => $document->summary ?? '',
                'metadata' => [
                    'risk_score' => $document->risk_score,
                    'category' => $document->category,
                    'tags' => $document->tags,
                    'complaint_id' => $document->complaint_id,
                ]
            ],
            default => [
                'type' => strtolower(class_basename($document)),
                'content' => '',
                'metadata' => []
            ]
        };
    }

    /**
     * Create rich, searchable text from structured complaint data.
     *
     * This formatting strategy balances completeness with relevance. We include
     * both original complaint data and AI-generated insights to create a
     * comprehensive searchable representation that captures both facts and context.
     */
    private function formatComplaintContent(Complaint $complaint): string
    {
        $parts = [
            "COMPLAINT TYPE: {$complaint->complaint_type}",
            "DESCRIPTION: {$complaint->descriptor}",
            "LOCATION: {$complaint->borough}, {$complaint->incident_address}",
            "AGENCY: {$complaint->agency_name} ({$complaint->agency})",
            "STATUS: {$complaint->status}",
        ];

        // Enhance with AI insights when available for richer semantic search
        if ($complaint->analysis) {
            $parts[] = "AI SUMMARY: {$complaint->analysis->summary}";
            $parts[] = "CATEGORY: {$complaint->analysis->category}";
            if ($complaint->analysis->tags) {
                $parts[] = "TAGS: " . implode(', ', $complaint->analysis->tags);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Hydrate embedding results with full Eloquent models.
     *
     * This lazy loading approach fetches complete model data only when needed,
     * optimizing performance by avoiding expensive joins during the initial
     * vector similarity calculation.
     */
    private function loadRelatedDocument(DocumentEmbedding $embedding): ?Model
    {
        return match ($embedding->document_type) {
            DocumentEmbedding::TYPE_COMPLAINT => Complaint::with('analysis')->find($embedding->document_id),
            DocumentEmbedding::TYPE_USER_QUESTION => UserQuestion::find($embedding->document_id),
            DocumentEmbedding::TYPE_ANALYSIS => ComplaintAnalysis::with('complaint')->find($embedding->document_id),
            default => null
        };
    }

    /**
     * Synchronize embeddings with the pgvector database for advanced search.
     *
     * This operation bridges our Laravel-managed embeddings with PostgreSQL's
     * pgvector extension, enabling more sophisticated vector operations than
     * our basic similarity search can provide.
     */
    public function syncVectorStore(): array
    {
        try {
            $result = $this->pythonBridge->syncPgVectorStore();
            
            Log::info("Vector store sync completed", $result);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Vector store sync failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}