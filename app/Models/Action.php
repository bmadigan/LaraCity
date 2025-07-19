<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    use HasFactory;
    
    // Action type constants
    public const TYPE_ESCALATE = 'escalate';
    public const TYPE_SUMMARIZE = 'summarize';
    public const TYPE_NOTIFY = 'notify';
    public const TYPE_ANALYZE = 'analyze';
    public const TYPE_ANALYSIS_TRIGGERED = 'analysis_triggered';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_COMPLAINT_DELETED = 'complaint_deleted';
    public const TYPE_COMPLAINT_RESTORED = 'complaint_restored';
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Use Model::unguard() instead of $fillable as per .cursor rules
        static::unguard();
    }
    
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
            self::TYPE_ANALYSIS_TRIGGERED,
            self::TYPE_STATUS_CHANGE,
            self::TYPE_COMPLAINT_DELETED,
            self::TYPE_COMPLAINT_RESTORED,
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
