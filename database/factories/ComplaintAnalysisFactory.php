<?php

namespace Database\Factories;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplaintAnalysis>
 */
class ComplaintAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $complaintTypes = [
            'Noise - Street/Sidewalk',
            'Illegal Parking',
            'Blocked Driveway',
            'Water System',
            'Heat/Hot Water',
            'Street Condition',
            'Traffic Signal Condition',
            'Graffiti',
            'Sanitation Condition',
            'Animal Abuse'
        ];
        
        $complaintType = $this->faker->randomElement($complaintTypes);
        $riskScore = $this->generateRiskScore($complaintType);
        
        return [
            'complaint_id' => Complaint::factory(),
            'summary' => $this->generateSummary($complaintType),
            'risk_score' => $riskScore,
            'category' => $this->generateCategory($complaintType),
            'tags' => $this->generateTags($complaintType, $riskScore),
        ];
    }
    
    /**
     * Generate risk score based on complaint type
     */
    private function generateRiskScore(string $complaintType): float
    {
        $highRiskTypes = ['Water System', 'Heat/Hot Water', 'Traffic Signal Condition'];
        $mediumRiskTypes = ['Street Condition', 'Animal Abuse'];
        
        if (in_array($complaintType, $highRiskTypes)) {
            return $this->faker->randomFloat(2, 0.6, 1.0); // High risk: 0.6-1.0
        } elseif (in_array($complaintType, $mediumRiskTypes)) {
            return $this->faker->randomFloat(2, 0.3, 0.7); // Medium risk: 0.3-0.7
        } else {
            return $this->faker->randomFloat(2, 0.0, 0.4); // Low risk: 0.0-0.4
        }
    }
    
    /**
     * Generate AI-style summary based on complaint type
     */
    private function generateSummary(string $complaintType): string
    {
        $summaries = [
            'Noise - Street/Sidewalk' => [
                'Routine noise complaint in residential area. Low priority for immediate response.',
                'Excessive noise disruption reported. Standard community mediation recommended.',
                'Street-level noise issue requiring basic enforcement action.'
            ],
            'Water System' => [
                'Critical water infrastructure issue requiring immediate attention.',
                'Water system malfunction detected. High priority for public safety.',
                'Potential water contamination risk identified. Emergency response needed.'
            ],
            'Heat/Hot Water' => [
                'Essential utility service disruption affecting resident safety.',
                'Heat/hot water outage requiring urgent repair coordination.',
                'Critical building system failure impacting multiple residents.'
            ],
            'Illegal Parking' => [
                'Standard parking violation requiring routine enforcement.',
                'Vehicle blocking access. Standard citation and towing protocol.',
                'Minor traffic flow disruption from improper parking.'
            ],
            'Traffic Signal Condition' => [
                'Traffic control system malfunction creating safety hazard.',
                'Signal equipment failure requiring immediate repair.',
                'Public safety risk from non-functioning traffic control.'
            ]
        ];
        
        $typeMessages = $summaries[$complaintType] ?? [
            'Standard municipal complaint requiring routine processing.',
            'Community issue identified for appropriate departmental response.',
            'Municipal service request logged for standard processing.'
        ];
        
        return $this->faker->randomElement($typeMessages);
    }
    
    /**
     * Generate category based on complaint type
     */
    private function generateCategory(string $complaintType): string
    {
        $categoryMap = [
            'Noise - Street/Sidewalk' => 'Noise Control',
            'Illegal Parking' => 'Traffic & Transportation',
            'Blocked Driveway' => 'Traffic & Transportation',
            'Water System' => 'Infrastructure',
            'Heat/Hot Water' => 'Housing & Buildings',
            'Street Condition' => 'Infrastructure',
            'Traffic Signal Condition' => 'Traffic & Transportation',
            'Graffiti' => 'Quality of Life',
            'Sanitation Condition' => 'Sanitation',
            'Animal Abuse' => 'Animal Services'
        ];
        
        return $categoryMap[$complaintType] ?? 'General Services';
    }
    
    /**
     * Generate relevant tags based on complaint type and risk
     */
    private function generateTags(string $complaintType, float $riskScore): array
    {
        $baseTags = [];
        
        // Risk-based tags
        if ($riskScore >= 0.7) {
            $baseTags[] = 'high-priority';
            $baseTags[] = 'urgent';
        } elseif ($riskScore >= 0.4) {
            $baseTags[] = 'medium-priority';
        } else {
            $baseTags[] = 'routine';
        }
        
        // Type-specific tags
        $typeTags = [
            'Noise - Street/Sidewalk' => ['noise', 'quality-of-life', 'community'],
            'Water System' => ['infrastructure', 'emergency', 'public-health'],
            'Heat/Hot Water' => ['housing', 'utilities', 'safety'],
            'Illegal Parking' => ['traffic', 'enforcement', 'violations'],
            'Traffic Signal Condition' => ['traffic', 'safety', 'infrastructure'],
            'Graffiti' => ['vandalism', 'quality-of-life', 'cleanup'],
        ];
        
        $specificTags = $typeTags[$complaintType] ?? ['general'];
        
        return array_merge($baseTags, $specificTags);
    }
    
    /**
     * Create high-risk analysis
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => $this->faker->randomFloat(2, 0.7, 1.0),
            'summary' => 'Critical issue requiring immediate attention and escalation.',
            'tags' => ['high-priority', 'urgent', 'escalate', 'emergency']
        ]);
    }
    
    /**
     * Create medium-risk analysis
     */
    public function mediumRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => $this->faker->randomFloat(2, 0.4, 0.69),
            'summary' => 'Moderate concern requiring timely response and monitoring.',
            'tags' => ['medium-priority', 'monitor', 'follow-up']
        ]);
    }
    
    /**
     * Create low-risk analysis
     */
    public function lowRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => $this->faker->randomFloat(2, 0.0, 0.39),
            'summary' => 'Routine issue for standard processing and resolution.',
            'tags' => ['routine', 'standard-process']
        ]);
    }
}
