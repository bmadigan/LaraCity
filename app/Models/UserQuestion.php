<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserQuestion extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Use Model::unguard() instead of $fillable as per .cursor rules
        static::unguard();
    }
    
    protected $casts = [
        'parsed_filters' => 'array',
    ];
    
    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function embeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class, 'document_id')
            ->where('document_type', DocumentEmbedding::TYPE_USER_QUESTION);
    }
    
    // Helper methods
    public function hasResponse(): bool
    {
        return !empty($this->ai_response);
    }
    
    public function hasFilters(): bool
    {
        return !empty($this->parsed_filters);
    }
    
    public function scopeByConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }
    
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    public function scopeWithResponse($query)
    {
        return $query->whereNotNull('ai_response')->where('ai_response', '!=', '');
    }
}
