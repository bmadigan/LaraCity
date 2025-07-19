<?php

namespace Database\Factories;

use App\Models\Action;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Action>
 */
class ActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(Action::getTypes());
        
        return [
            'type' => $type,
            'parameters' => $this->generateParameters($type),
            'triggered_by' => $this->faker->randomElement(['system', '1']), // system or user ID
            'complaint_id' => $this->faker->optional(0.8)->passthrough(
                Complaint::count() > 0 ? Complaint::inRandomOrder()->first()?->id : null
            ),
        ];
    }
    
    /**
     * Generate type-specific parameters
     */
    private function generateParameters(string $type): array
    {
        switch ($type) {
            case Action::TYPE_ESCALATE:
                return [
                    'reason' => $this->faker->randomElement([
                        'High risk score threshold exceeded',
                        'Critical infrastructure issue',
                        'Public safety concern',
                        'Multiple complaints in area'
                    ]),
                    'risk_score' => $this->faker->randomFloat(2, 0.7, 1.0),
                    'escalation_level' => $this->faker->randomElement(['manager', 'supervisor', 'emergency']),
                    'notification_sent' => $this->faker->boolean(80)
                ];
                
            case Action::TYPE_NOTIFY:
                return [
                    'notification_type' => $this->faker->randomElement(['slack', 'email', 'sms']),
                    'recipient' => $this->faker->randomElement(['duty_manager', 'field_team', 'emergency_services']),
                    'message' => $this->faker->sentence(),
                    'delivery_status' => $this->faker->randomElement(['sent', 'delivered', 'failed'])
                ];
                
            case Action::TYPE_SUMMARIZE:
                return [
                    'summary_type' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'incident']),
                    'record_count' => $this->faker->numberBetween(1, 100),
                    'date_range' => [
                        'start' => $this->faker->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
                        'end' => $this->faker->dateTimeBetween('-1 day', 'now')->format('Y-m-d')
                    ]
                ];
                
            case Action::TYPE_ANALYZE:
                return [
                    'analysis_type' => $this->faker->randomElement(['risk_assessment', 'pattern_detection', 'priority_scoring']),
                    'model_used' => $this->faker->randomElement(['gpt-4o-mini', 'text-davinci-003']),
                    'processing_time_ms' => $this->faker->numberBetween(500, 5000),
                    'confidence_score' => $this->faker->randomFloat(2, 0.5, 1.0)
                ];
                
            default:
                return [
                    'action_details' => $this->faker->sentence(),
                    'timestamp' => now()->toISOString()
                ];
        }
    }
    
    /**
     * Create escalation action
     */
    public function escalation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Action::TYPE_ESCALATE,
            'triggered_by' => 'system',
            'parameters' => [
                'reason' => 'High risk score threshold exceeded',
                'risk_score' => $this->faker->randomFloat(2, 0.7, 1.0),
                'escalation_level' => 'supervisor',
                'notification_sent' => true,
                'escalated_at' => now()->toISOString()
            ]
        ]);
    }
    
    /**
     * Create notification action
     */
    public function notification(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Action::TYPE_NOTIFY,
            'triggered_by' => 'system',
            'parameters' => [
                'notification_type' => 'slack',
                'recipient' => 'duty_manager',
                'message' => 'High priority complaint requires immediate attention',
                'delivery_status' => 'delivered'
            ]
        ]);
    }
    
    /**
     * Create analysis action
     */
    public function analysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Action::TYPE_ANALYZE,
            'triggered_by' => 'system',
            'parameters' => [
                'analysis_type' => 'risk_assessment',
                'model_used' => 'gpt-4o-mini',
                'processing_time_ms' => $this->faker->numberBetween(800, 3000),
                'confidence_score' => $this->faker->randomFloat(2, 0.8, 1.0)
            ]
        ]);
    }
    
    /**
     * Create user-triggered action
     */
    public function userTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => '1' // Assumes user ID 1 exists
        ]);
    }
    
    /**
     * Create system-triggered action
     */
    public function systemTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => 'system'
        ]);
    }
}
