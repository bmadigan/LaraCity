<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuestion extends Model
{
    protected $fillable = [
        'question',
        'parsed_filters',
        'ai_response',
        'embedding',
        'conversation_id',
        'user_id',
    ];
    
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
