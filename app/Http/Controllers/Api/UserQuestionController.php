<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateUserQuestionRequest;
use App\Http\Resources\UserQuestionResource;
use App\Models\UserQuestion;
use Illuminate\Http\JsonResponse;

class UserQuestionController extends Controller
{
    /**
     * Store a new user question for chat/RAG system
     */
    public function store(CreateUserQuestionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        
        try {
            // Parse filters from question (basic implementation)
            $parsedFilters = $this->parseFiltersFromQuestion($validated['question']);
            
            $userQuestion = UserQuestion::create([
                'question' => $validated['question'],
                'parsed_filters' => $parsedFilters,
                'conversation_id' => $validated['conversation_id'],
                'user_id' => $userId,
                'ai_response' => null, // Will be populated by AI system in Phase E
            ]);
            
            return response()->json([
                'message' => 'Question received and queued for processing',
                'data' => new UserQuestionResource($userQuestion),
                'status' => 'pending_ai_response',
                'context' => $validated['context'] ?? null,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process question',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Basic filter parsing from natural language question
     * This will be enhanced with AI in Phase E
     */
    private function parseFiltersFromQuestion(string $question): array
    {
        $filters = [];
        $lowerQuestion = strtolower($question);
        
        // Borough detection
        $boroughs = ['manhattan', 'brooklyn', 'queens', 'bronx', 'staten island'];
        foreach ($boroughs as $borough) {
            if (str_contains($lowerQuestion, $borough)) {
                $filters['borough'] = strtoupper($borough);
                break;
            }
        }
        
        // Complaint type detection
        $types = [
            'noise' => 'Noise - Street/Sidewalk',
            'parking' => 'Illegal Parking',
            'water' => 'Water System',
            'heat' => 'Heat/Hot Water',
            'street' => 'Street Condition',
            'graffiti' => 'Graffiti',
        ];
        
        foreach ($types as $keyword => $type) {
            if (str_contains($lowerQuestion, $keyword)) {
                $filters['complaint_type'] = $type;
                break;
            }
        }
        
        // Status detection
        if (str_contains($lowerQuestion, 'open')) {
            $filters['status'] = 'Open';
        } elseif (str_contains($lowerQuestion, 'closed')) {
            $filters['status'] = 'Closed';
        } elseif (str_contains($lowerQuestion, 'progress')) {
            $filters['status'] = 'InProgress';
        }
        
        // Priority detection
        if (str_contains($lowerQuestion, 'high priority') || str_contains($lowerQuestion, 'urgent')) {
            $filters['priority'] = 'High';
        } elseif (str_contains($lowerQuestion, 'critical')) {
            $filters['priority'] = 'Critical';
        }
        
        // Risk level detection
        if (str_contains($lowerQuestion, 'high risk') || str_contains($lowerQuestion, 'dangerous')) {
            $filters['risk_level'] = 'high';
        } elseif (str_contains($lowerQuestion, 'medium risk')) {
            $filters['risk_level'] = 'medium';
        } elseif (str_contains($lowerQuestion, 'low risk')) {
            $filters['risk_level'] = 'low';
        }
        
        // Time-based detection (basic)
        if (str_contains($lowerQuestion, 'last week')) {
            $filters['date_from'] = now()->subWeek()->format('Y-m-d');
        } elseif (str_contains($lowerQuestion, 'last month')) {
            $filters['date_from'] = now()->subMonth()->format('Y-m-d');
        } elseif (str_contains($lowerQuestion, 'today')) {
            $filters['date_from'] = now()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }
        
        return $filters;
    }
}