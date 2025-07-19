<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAnalysis extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'complaint_id',
        'summary',
        'risk_score',
        'category',
        'tags',
    ];
    
    protected $casts = [
        'risk_score' => 'decimal:2',
        'tags' => 'array',
    ];
    
    // Relationships
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
    
    // Helper methods
    public function isHighRisk(): bool
    {
        return $this->risk_score >= 0.7;
    }
    
    public function isMediumRisk(): bool
    {
        return $this->risk_score >= 0.4 && $this->risk_score < 0.7;
    }
    
    public function isLowRisk(): bool
    {
        return $this->risk_score < 0.4;
    }
    
    public function getRiskLevelAttribute(): string
    {
        if ($this->isHighRisk()) {
            return 'High';
        } elseif ($this->isMediumRisk()) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }
}
