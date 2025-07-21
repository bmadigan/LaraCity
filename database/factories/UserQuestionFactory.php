<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserQuestionFactory extends Factory
{
    protected $model = UserQuestion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_id' => 'conv-' . $this->faker->uuid(),
            'question' => $this->faker->randomElement([
                'What are the most common complaint types in Manhattan?',
                'Show me high risk complaints from last month',
                'How many water system complaints were there in Brooklyn?',
                'What complaints have been escalated recently?',
                'Can you analyze the noise complaints by borough?',
                'Show me complaints near Central Park',
                'What percentage of complaints are resolved within 30 days?',
                'Which agency handles the most complaints?',
                'Show me trends in complaint volumes over time',
                'What are the top complaint types in each borough?',
            ]),
            'ai_response' => $this->faker->optional(0.7)->randomElement([
                'Based on the data analysis, noise complaints represent the largest category.',
                'I found 247 high-risk complaints that require immediate attention.',
                'Brooklyn has reported 156 water system issues this month.',
                'There are currently 23 escalated complaints awaiting review.',
                'Here\'s the borough-by-borough breakdown of noise complaints...',
                'I found 12 complaints within a 1-mile radius of Central Park.',
                'Approximately 78% of complaints are resolved within the target timeframe.',
                'The NYPD handles the highest volume with 2,341 complaints this quarter.',
                'Complaint volumes show a 15% increase compared to last quarter.',
                'Each borough shows distinct patterns in complaint categories...',
            ]),
            'parsed_filters' => $this->faker->optional(0.6)->randomElement([
                ['borough' => 'MANHATTAN'],
                ['borough' => 'BROOKLYN', 'risk_level' => 'high'],
                ['complaint_type' => 'Noise', 'borough' => 'QUEENS'],
                ['date_from' => '2024-01-01', 'date_to' => '2024-01-31'],
                ['agency' => 'NYPD', 'status' => 'Open'],
                ['risk_level' => 'high', 'limit' => 50],
                null,
            ]),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Create a user question with an AI response
     */
    public function withResponse(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'ai_response' => $this->faker->randomElement([
                    'Based on your query, here are the key insights from the complaints data.',
                    'I found several patterns in the complaint data that match your criteria.',
                    'The analysis shows interesting trends in the selected time period.',
                    'Here\'s a summary of the complaints based on your search parameters.',
                    'The data reveals important patterns in complaint resolution times.',
                ]),
            ];
        });
    }

    /**
     * Create a user question without an AI response
     */
    public function withoutResponse(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'ai_response' => null,
            ];
        });
    }

    /**
     * Create a user question with specific filters
     */
    public function withFilters(array $filters): static
    {
        return $this->state(function (array $attributes) use ($filters) {
            return [
                'parsed_filters' => $filters,
            ];
        });
    }

    /**
     * Create a user question for a specific conversation
     */
    public function inConversation(string $conversationId): static
    {
        return $this->state(function (array $attributes) use ($conversationId) {
            return [
                'conversation_id' => $conversationId,
            ];
        });
    }

    /**
     * Create a user question for a specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user->id,
            ];
        });
    }

    /**
     * Create a statistical query user question
     */
    public function statistical(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question' => $this->faker->randomElement([
                    'What are the most common complaint types?',
                    'How many complaints were filed this month?',
                    'What percentage of complaints are high risk?',
                    'Show me complaint statistics by borough',
                    'What are the average response times by agency?',
                ]),
                'parsed_filters' => $this->faker->optional()->randomElement([
                    ['analysis_type' => 'statistics'],
                    ['group_by' => 'borough'],
                    ['group_by' => 'complaint_type'],
                    ['date_range' => 'last_30_days'],
                ]),
            ];
        });
    }

    /**
     * Create a search query user question
     */
    public function searchQuery(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'question' => $this->faker->randomElement([
                    'Find complaints about water leaks in Manhattan',
                    'Show me noise complaints near schools',
                    'Search for complaints with rats or mice',
                    'Find all escalated complaints from last week',
                    'Show me complaints at this address: 123 Main Street',
                ]),
                'parsed_filters' => $this->faker->randomElement([
                    ['search_term' => 'water leak', 'borough' => 'MANHATTAN'],
                    ['search_term' => 'noise', 'location_type' => 'school'],
                    ['search_term' => 'rat mouse'],
                    ['status' => 'Escalated', 'date_range' => 'last_week'],
                    ['address' => '123 Main Street'],
                ]),
            ];
        });
    }
}