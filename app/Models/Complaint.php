<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    // Status constants
    const STATUS_OPEN = 'Open';
    const STATUS_IN_PROGRESS = 'InProgress';
    const STATUS_CLOSED = 'Closed';
    const STATUS_ESCALATED = 'Escalated';
    
    // Priority constants
    const PRIORITY_LOW = 'Low';
    const PRIORITY_MEDIUM = 'Medium';
    const PRIORITY_HIGH = 'High';
    const PRIORITY_CRITICAL = 'Critical';
    
    // Borough constants
    const BOROUGH_MANHATTAN = 'MANHATTAN';
    const BOROUGH_BROOKLYN = 'BROOKLYN';
    const BOROUGH_QUEENS = 'QUEENS';
    const BOROUGH_BRONX = 'BRONX';
    const BOROUGH_STATEN_ISLAND = 'STATEN ISLAND';
    
    protected $fillable = [
        'complaint_number',
        'complaint_type',
        'descriptor',
        'agency',
        'agency_name',
        'borough',
        'city',
        'incident_address',
        'street_name',
        'cross_street_1',
        'cross_street_2',
        'incident_zip',
        'address_type',
        'latitude',
        'longitude',
        'location_type',
        'status',
        'resolution_description',
        'priority',
        'community_board',
        'council_district',
        'police_precinct',
        'school_district',
        'submitted_at',
        'resolved_at',
        'due_date',
        'facility_type',
        'park_facility_name',
        'vehicle_type',
        'embedding'
    ];
    
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
