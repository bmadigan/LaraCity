<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    use HasFactory;
    
    // Action type constants
    const TYPE_ESCALATE = 'escalate';
    const TYPE_SUMMARIZE = 'summarize';
    const TYPE_NOTIFY = 'notify';
    const TYPE_ANALYZE = 'analyze';
    
    protected $fillable = [
        'type',
        'parameters',
        'triggered_by',
        'complaint_id',
    ];
    
    protected $casts = [
        'parameters' => 'array',
    ];
    
    // Relationships
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
    
    // Helper methods
    public static function getTypes(): array
    {
        return [
            self::TYPE_ESCALATE,
            self::TYPE_SUMMARIZE,
            self::TYPE_NOTIFY,
            self::TYPE_ANALYZE,
        ];
    }
    
    public function isSystemTriggered(): bool
    {
        return $this->triggered_by === 'system';
    }
    
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
