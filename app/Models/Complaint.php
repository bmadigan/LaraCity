<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central model representing NYC 311 complaint data with AI analysis capabilities.
 *
 * This model serves as the core entity in our complaint management system,
 * bridging raw NYC 311 data with our AI analysis pipeline. The constants
 * provide type safety and consistency across the application, while the
 * relationships enable rich data modeling.
 *
 * The unguarded approach reflects Laravel's modern philosophy of trusting
 * developers to validate data at the boundaries rather than the model level.
 */
class Complaint extends Model
{
    use HasFactory;
    
    /**
     * Complaint lifecycle states reflecting NYC's processing workflow.
     * These align with how the city actually tracks complaint resolution.
     */
    public const STATUS_OPEN = 'Open';
    public const STATUS_IN_PROGRESS = 'InProgress';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_ESCALATED = 'Escalated';
    
    /**
     * Priority levels for internal triage and resource allocation.
     * These supplement the city's data with our AI-driven prioritization.
     */
    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_CRITICAL = 'Critical';
    
    /**
     * NYC borough identifiers matching the official 311 data format.
     * These constants prevent typos and enable consistent querying.
     */
    public const BOROUGH_MANHATTAN = 'MANHATTAN';
    public const BOROUGH_BROOKLYN = 'BROOKLYN';
    public const BOROUGH_QUEENS = 'QUEENS';
    public const BOROUGH_BRONX = 'BRONX';
    public const BOROUGH_STATEN_ISLAND = 'STATEN ISLAND';
    
    /**
     * Initialize the model with unguarded mass assignment.
     *
     * This follows Laravel's modern approach of handling mass assignment
     * protection at the application boundaries rather than the model level,
     * trusting that validation happens in form requests and controllers.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Trust the application to validate data rather than using $fillable
        static::unguard();
    }
    
    /**
     * Automatically cast database values to appropriate PHP types.
     *
     * The precision on coordinates ensures accurate geolocation for NYC,
     * while datetime casting enables proper temporal operations and API serialization.
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'due_date' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];
    
    /**
     * One-to-one relationship with AI-generated analysis.
     *
     * This relationship enables rich insights beyond the raw complaint data,
     * including risk scoring, categorization, and automated summaries.
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(ComplaintAnalysis::class);
    }
    
    /**
     * Track resolution actions taken on this complaint.
     *
     * This provides an audit trail of agency responses and enables
     * workflow tracking through the complaint lifecycle.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }
    
    /**
     * Vector embeddings enabling semantic search capabilities.
     *
     * These relationships connect complaints to their vector representations,
     * allowing users to find similar complaints through natural language queries.
     */
    public function embeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class, 'document_id')
                    ->where('document_type', DocumentEmbedding::TYPE_COMPLAINT);
    }
    
    /**
     * Get all valid complaint statuses for UI dropdowns and validation.
     *
     * This method provides a single source of truth for status values,
     * ensuring consistency across forms, APIs, and database constraints.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_CLOSED,
            self::STATUS_ESCALATED,
        ];
    }
    
    /**
     * Get all priority levels for complaint triage.
     *
     * These supplement NYC's data with our internal prioritization system,
     * enabling better resource allocation and response planning.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL,
        ];
    }
    
    /**
     * Get all NYC boroughs for geographic filtering and analysis.
     *
     * These match the official NYC 311 data format, ensuring compatibility
     * with external datasets and geographic analysis tools.
     */
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
    
    /**
     * Determine if this complaint requires urgent attention based on AI analysis.
     *
     * This business logic encapsulates our risk assessment threshold,
     * enabling consistent risk identification across the application.
     */
    public function isHighRisk(): bool
    {
        return $this->analysis && $this->analysis->risk_score >= 0.7;
    }
    
    /**
     * Filter complaints by NYC borough with case normalization.
     *
     * The uppercase conversion ensures consistent matching regardless
     * of how users input borough names in forms or APIs.
     */
    public function scopeByBorough($query, string $borough)
    {
        return $query->where('borough', strtoupper($borough));
    }
    
    /**
     * Filter complaints by their current processing status.
     *
     * This scope enables tracking complaint resolution progress
     * and building status-specific dashboards and reports.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Filter complaints by their assigned priority level.
     *
     * This supports triage workflows and helps teams focus on
     * the most critical issues requiring immediate attention.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }
    
    /**
     * Filter complaints within a specific time window.
     *
     * This scope enables temporal analysis and reporting, such as
     * monthly complaint volumes or seasonal trend identification.
     */
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('submitted_at', [$start, $end]);
    }
}
