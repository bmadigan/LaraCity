<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use Illuminate\Database\Seeder;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create some demo complaints if they don't exist
        if (Complaint::count() < 10) {
            $this->createDemoComplaints();
        }
    }

    private function createDemoComplaints(): void
    {
        $complaints = [
            [
                'complaint_number' => 'DEMO-001',
                'complaint_type' => 'Heating',
                'complaint_description' => 'Apartment heating system not working properly during winter months. Multiple tenants affected.',
                'borough' => 'MANHATTAN',
                'status' => 'Open',
                'priority' => 'High',
                'risk_score' => 0.85
            ],
            [
                'complaint_number' => 'DEMO-002',
                'complaint_type' => 'Noise',
                'complaint_description' => 'Loud music and parties until late hours disturbing residents in building.',
                'borough' => 'BROOKLYN',
                'status' => 'In Progress',
                'priority' => 'Medium',
                'risk_score' => 0.45
            ],
            [
                'complaint_number' => 'DEMO-003',
                'complaint_type' => 'Water Leak',
                'complaint_description' => 'Water leaking from ceiling causing damage to apartment below.',
                'borough' => 'QUEENS',
                'status' => 'Escalated',
                'priority' => 'High',
                'risk_score' => 0.92
            ],
            [
                'complaint_number' => 'DEMO-004',
                'complaint_type' => 'Electrical',
                'complaint_description' => 'Electrical outlets not working properly, potential fire hazard.',
                'borough' => 'BRONX',
                'status' => 'Open',
                'priority' => 'High',
                'risk_score' => 0.78
            ],
            [
                'complaint_number' => 'DEMO-005',
                'complaint_type' => 'Garbage Collection',
                'complaint_description' => 'Garbage not collected for over a week, attracting pests and rodents.',
                'borough' => 'STATEN ISLAND',
                'status' => 'Closed',
                'priority' => 'Medium',
                'risk_score' => 0.35
            ],
            [
                'complaint_number' => 'DEMO-006',
                'complaint_type' => 'Street Condition',
                'complaint_description' => 'Large pothole in street causing damage to vehicles.',
                'borough' => 'MANHATTAN',
                'status' => 'Open',
                'priority' => 'Medium',
                'risk_score' => 0.55
            ],
            [
                'complaint_number' => 'DEMO-007',
                'complaint_type' => 'Building Safety',
                'complaint_description' => 'Broken stairs in building pose safety risk to residents.',
                'borough' => 'BROOKLYN',
                'status' => 'Escalated',
                'priority' => 'High',
                'risk_score' => 0.88
            ],
            [
                'complaint_number' => 'DEMO-008',
                'complaint_type' => 'Air Quality',
                'complaint_description' => 'Strong chemical odor coming from nearby construction site.',
                'borough' => 'QUEENS',
                'status' => 'In Progress',
                'priority' => 'Medium',
                'risk_score' => 0.62
            ]
        ];

        foreach ($complaints as $complaintData) {
            $riskScore = $complaintData['risk_score'];
            unset($complaintData['risk_score']);
            
            $complaint = Complaint::create([
                ...$complaintData,
                'created_date' => now()->subDays(rand(1, 30))->format('Y-m-d H:i:s'),
                'location_type' => 'Residential Building',
                'incident_zip' => '1' . str_pad((string)rand(1001, 1299), 4, '0', STR_PAD_LEFT),
                'agency' => 'HPD',
                'agency_name' => 'Housing Preservation and Development'
            ]);

            // Create analysis
            ComplaintAnalysis::create([
                'complaint_id' => $complaint->id,
                'risk_score' => $riskScore,
                'category' => $this->getCategoryFromType($complaint->complaint_type),
                'sentiment' => $riskScore > 0.7 ? 'negative' : ($riskScore > 0.4 ? 'neutral' : 'positive'),
                'priority' => $riskScore > 0.7 ? 'high' : ($riskScore > 0.4 ? 'medium' : 'low'),
                'tags' => $this->getTagsFromType($complaint->complaint_type),
                'summary' => "AI analysis: " . $this->getSummaryFromType($complaint->complaint_type)
            ]);
        }
    }

    private function getCategoryFromType(string $type): string
    {
        return match ($type) {
            'Heating', 'Water Leak', 'Electrical' => 'Infrastructure',
            'Noise' => 'Quality of Life',
            'Garbage Collection' => 'Sanitation',
            'Street Condition' => 'Transportation',
            'Building Safety' => 'Safety',
            'Air Quality' => 'Environmental',
            default => 'General'
        };
    }

    private function getTagsFromType(string $type): array
    {
        return match ($type) {
            'Heating' => ['heating', 'winter', 'temperature'],
            'Noise' => ['noise', 'loud', 'disturbance'],
            'Water Leak' => ['water', 'leak', 'damage'],
            'Electrical' => ['electrical', 'fire_hazard', 'safety'],
            'Garbage Collection' => ['garbage', 'waste', 'pests'],
            'Street Condition' => ['street', 'pothole', 'vehicles'],
            'Building Safety' => ['safety', 'building', 'stairs'],
            'Air Quality' => ['air', 'chemical', 'odor'],
            default => ['general']
        };
    }

    private function getSummaryFromType(string $type): string
    {
        return match ($type) {
            'Heating' => 'Critical heating system failure affecting multiple residents during winter period.',
            'Noise' => 'Ongoing noise disturbance affecting residential quality of life.',
            'Water Leak' => 'Active water leak causing property damage and potential structural issues.',
            'Electrical' => 'Electrical system malfunction presenting fire hazard and safety risk.',
            'Garbage Collection' => 'Sanitation issue with pest control implications.',
            'Street Condition' => 'Infrastructure damage affecting vehicle safety and traffic flow.',
            'Building Safety' => 'Structural safety concern requiring immediate attention.',
            'Air Quality' => 'Environmental health concern from chemical emissions.',
            default => 'General complaint requiring investigation and resolution.'
        };
    }
}