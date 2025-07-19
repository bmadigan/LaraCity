<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;

class CreateComplaintAnalysis
{
    public static function run(Complaint $complaint, array $analysisData): ComplaintAnalysis
    {
        return ComplaintAnalysis::create([
            'complaint_id' => $complaint->id,
            'summary' => $analysisData['summary'],
            'risk_score' => $analysisData['risk_score'],
            'category' => $analysisData['category'],
            'tags' => $analysisData['tags'],
        ]);
    }
}