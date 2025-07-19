<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    // Status constants
    public const STATUS_OPEN = 'Open';
    public const STATUS_IN_PROGRESS = 'InProgress';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_ESCALATED = 'Escalated';
    
    // Priority constants
    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_CRITICAL = 'Critical';
    
    // Borough constants
    public const BOROUGH_MANHATTAN = 'MANHATTAN';
    public const BOROUGH_BROOKLYN = 'BROOKLYN';
    public const BOROUGH_QUEENS = 'QUEENS';
    public const BOROUGH_BRONX = 'BRONX';
    public const BOROUGH_STATEN_ISLAND = 'STATEN ISLAND';
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Use Model::unguard() instead of $fillable as per .cursor rules
        static::unguard();
    }
    
    protected $casts = [
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'due_date' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];
    
    // Relationships
    public function analysis(): HasOne
    {
        return $this->hasOne(ComplaintAnalysis::class);
    }
    
    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }
    
    // Helper methods
    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_CLOSED,
            self::STATUS_ESCALATED,
        ];
    }
    
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL,
        ];
    }
    
    public static function getBoroughs(): array
    {
        return [
            self::BOROUGH_MANHATTAN,
            self::BOROUGH_BROOKLYN,
            self::BOROUGH_QUEENS,
            self::BOROUGH_BRONX,
            self::BOROUGH_STATEN_ISLAND,
        ];
    }
    
    public function isHighRisk(): bool
    {
        return $this->analysis && $this->analysis->risk_score >= 0.7;
    }
    
    public function scopeByBorough($query, string $borough)
    {
        return $query->where('borough', strtoupper($borough));
    }
    
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }
    
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('submitted_at', [$start, $end]);
    }
}
