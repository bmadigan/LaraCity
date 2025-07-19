<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DocumentEmbedding extends Model
{
    protected $fillable = [
        'document_type',
        'document_id',
        'document_hash',
        'content',
        'metadata',
        'embedding_model',
        'embedding_dimension',
        'embedding',
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding_dimension' => 'integer',
    ];

    // Document type constants
    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_USER_QUESTION = 'user_question';
    public const TYPE_ANALYSIS = 'analysis';

    /**
     * Get the document that this embedding belongs to
     */
    public function document(): MorphTo
    {
        return $this->morphTo('document', 'document_type', 'document_id');
    }

    /**
     * Create a hash of the content for deduplication
     */
    public static function createContentHash(string $content): string
    {
        return hash('sha256', trim($content));
    }

    /**
     * Find similar documents using vector similarity search
     */
    public function scopeSimilarTo(Builder $query, array $embedding, float $threshold = 0.8, int $limit = 10): Builder
    {
        $embeddingStr = '[' . implode(',', $embedding) . ']';
        
        return $query
            ->select('*')
            ->selectRaw('1 - (embedding <=> ?::vector) as similarity', [$embeddingStr])
            ->whereRaw('1 - (embedding <=> ?::vector) > ?', [$embeddingStr, $threshold])
            ->orderByRaw('embedding <=> ?::vector ASC', [$embeddingStr])
            ->limit($limit);
    }

    /**
     * Search by document type with similarity
     */
    public function scopeByTypeWithSimilarity(Builder $query, string $type, array $embedding, float $threshold = 0.8, int $limit = 10): Builder
    {
        return $query->where('document_type', $type)
            ->similarTo($embedding, $threshold, $limit);
    }

    /**
     * Search for semantically similar content
     */
    public static function searchSimilar(string $content, string $embeddingModel, array $embedding, string $documentType = null, float $threshold = 0.8, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('embedding_model', $embeddingModel);
        
        if ($documentType) {
            $query->where('document_type', $documentType);
        }
        
        return $query->similarTo($embedding, $threshold, $limit)->get();
    }

    /**
     * Find exact matches by content hash
     */
    public static function findByContentHash(string $hash): ?self
    {
        return static::where('document_hash', $hash)->first();
    }

    /**
     * Get embedding as array
     */
    public function getEmbeddingArrayAttribute(): array
    {
        if (!$this->embedding) {
            return [];
        }
        
        // Parse PostgreSQL vector format: "[1,2,3]" -> [1,2,3]
        $cleaned = trim($this->embedding, '[]');
        return array_map('floatval', explode(',', $cleaned));
    }

    /**
     * Set embedding from array
     */
    public function setEmbeddingFromArray(array $embedding): void
    {
        $this->embedding = '[' . implode(',', $embedding) . ']';
    }
}
