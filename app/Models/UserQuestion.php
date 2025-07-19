<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuestion extends Model
{
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
        return $query->whereNotNull('ai_response');
    }
}
