<?php

namespace App\Services;

use App\Models\DocumentEmbedding;
use App\Models\Complaint;
use App\Models\UserQuestion;
use App\Models\ComplaintAnalysis;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class VectorEmbeddingService
{
    public function __construct(
        private PythonAiBridge $pythonBridge
    ) {}

    /**
     * Generate and store embedding for a document
     */
    public function generateEmbedding(Model $document, ?string $customContent = null): ?DocumentEmbedding
    {
        try {
            // Extract content and metadata based on document type
            $documentData = $this->extractDocumentData($document, $customContent);
            
            if (empty($documentData['content'])) {
                Log::warning("No content found for embedding generation", [
                    'document_type' => $documentData['type'],
                    'document_id' => $document->id
                ]);
                return null;
            }

            // Check if embedding already exists
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

            // Generate embedding using Python bridge
            $embeddingData = $this->pythonBridge->generateEmbedding($documentData['content']);
            
            if (!$embeddingData || empty($embeddingData['embedding'])) {
                Log::error("Failed to generate embedding", [
                    'document_type' => $documentData['type'],
                    'document_id' => $document->id
                ]);
                return null;
            }

            // Store embedding in database
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
     * Search for similar documents using vector similarity
     */
    public function searchSimilar(string $query, string $documentType = null, float $threshold = 0.8, int $limit = 10): array
    {
        try {
            // Generate embedding for the query
            $queryEmbedding = $this->pythonBridge->generateEmbedding($query);
            
            if (!$queryEmbedding || empty($queryEmbedding['embedding'])) {
                Log::error("Failed to generate query embedding", ['query' => $query]);
                return [];
            }

            // Search for similar documents
            $embeddingModel = $queryEmbedding['model'] ?? 'text-embedding-3-small';
            $results = DocumentEmbedding::searchSimilar(
                $query,
                $embeddingModel,
                $queryEmbedding['embedding'],
                $documentType,
                $threshold,
                $limit
            );

            // Load related documents and format results
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
     * Generate embeddings for multiple documents in batch
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
            
            // Small delay between batches to avoid rate limiting
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Extract content and metadata from different document types
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
     * Format complaint data into searchable text content
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
     * Load the related document for an embedding
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
     * Update Python bridge to support pgvector
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