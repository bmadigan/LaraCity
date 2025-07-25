<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Complaint;
use App\Services\HybridSearchService;
use App\Services\PythonAiBridge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * AI-powered chat agent for natural language queries about complaint data.
 *
 * This component intelligently routes user questions to the appropriate handler:
 * statistical queries go to database aggregation, search queries use vector
 * similarity, and general conversation routes to the AI bridge.
 */
class ChatAgent extends Component
{
    /**
     * The conversation history as an array of message objects.
     */
    public array $messages = [];

    /**
     * The current user input being typed.
     */
    public string $userMessage = '';

    /**
     * Whether we're currently processing a user message.
     */
    public bool $isProcessing = false;

    /**
     * Unique identifier for this chat session.
     */
    public string $sessionId;
    
    /**
     * Livewire event listeners for real-time chat functionality.
     *
     * These handle streaming responses and error states for a more
     * interactive chat experience.
     */
    protected $listeners = [
        'messageReceived' => 'addMessage',
        'chatResponseChunk' => 'handleResponseChunk',
        'chatResponseComplete' => 'handleResponseComplete',
        'chatResponseError' => 'handleResponseError'
    ];

    /**
     * Initialize the chat session with a unique ID and welcome message.
     */
    public function mount(): void
    {
        // Generate a unique session ID for this user's chat session
        $this->sessionId = 'chat-' . auth()->id() . '-' . uniqid();
        
        // Start with a friendly welcome message that explains capabilities
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Hello! I\'m your LaraCity AI assistant. I can help you understand complaints, search for patterns, and provide insights about the data. How can I assist you today?',
                'timestamp' => now()->toISOString(),
            ]
        ];
    }

    /**
     * Process and send a user message to the AI chat agent.
     *
     * This handles the complete message flow: validation, adding to chat history,
     * and routing to the appropriate AI handler based on query type.
     */
    public function sendMessage(): void
    {
        // Ensure we have valid input before processing
        $this->validate([
            'userMessage' => 'required|string|max:1000'
        ]);

        if (empty(trim($this->userMessage))) {
            return;
        }

        // Add the user's message to our conversation history
        $this->messages[] = [
            'role' => 'user',
            'content' => trim($this->userMessage),
            'timestamp' => now()->toISOString(),
        ];

        $userMessage = trim($this->userMessage);
        $this->userMessage = '';  // Clear input for next message
        $this->isProcessing = true;

        // Create a placeholder response that we'll update with the AI's answer
        $responseIndex = count($this->messages);
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '',
            'timestamp' => now()->toISOString(),
            'isStreaming' => true,  // Shows loading indicator
        ];

        // Route the message to the appropriate AI handler
        $this->processMessage($userMessage, $responseIndex);
    }

    #[On('chat-response-chunk')]
    public function handleResponseChunk(array $data): void
    {
        if ($data['sessionId'] !== $this->sessionId) {
            return;
        }

        $responseIndex = $data['responseIndex'];
        $chunk = $data['chunk'];
        
        // Update the streaming message
        if (isset($this->messages[$responseIndex])) {
            $this->messages[$responseIndex]['content'] .= $chunk;
        }
    }

    #[On('chat-response-complete')]
    public function handleResponseComplete(array $data): void
    {
        if ($data['sessionId'] !== $this->sessionId) {
            return;
        }

        $responseIndex = $data['responseIndex'];
        
        // Mark message as complete
        if (isset($this->messages[$responseIndex])) {
            unset($this->messages[$responseIndex]['isStreaming']);
        }
        
        $this->isProcessing = false;
        
        // Scroll to bottom
        $this->dispatch('scroll-to-bottom');
    }

    #[On('chat-response-error')]
    public function handleResponseError(array $data): void
    {
        if ($data['sessionId'] !== $this->sessionId) {
            return;
        }

        $responseIndex = $data['responseIndex'];
        $error = $data['error'] ?? 'An error occurred while processing your message.';
        
        // Update message with error
        if (isset($this->messages[$responseIndex])) {
            $this->messages[$responseIndex]['content'] = "I apologize, but I encountered an error: {$error}";
            $this->messages[$responseIndex]['isError'] = true;
            unset($this->messages[$responseIndex]['isStreaming']);
        }
        
        $this->isProcessing = false;
    }

    public function clearChat(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Chat history cleared. How can I help you?',
                'timestamp' => now()->toISOString(),
            ]
        ];
        $this->sessionId = 'chat-' . auth()->id() . '-' . uniqid();
    }

    public function getMessagesProperty(): array
    {
        return $this->messages;
    }

    /**
     * Intelligently route user messages to the appropriate handler using AI.
     *
     * This uses our Python AI bridge to classify query intent rather than
     * brittle keyword matching, making it scalable for complex natural language.
     */
    private function processMessage(string $message, int $responseIndex): void
    {
        try {
            // Use AI to classify the query intent and extract parameters
            $queryAnalysis = $this->analyzeQueryIntent($message);
            
            Log::info('ChatAgent AI-powered query routing', [
                'message' => $message,
                'intent' => $queryAnalysis['intent'],
                'parameters' => $queryAnalysis['parameters'],
                'confidence' => $queryAnalysis['confidence'],
                'reasoning' => $queryAnalysis['reasoning'] ?? 'No reasoning provided'
            ]);
            
            $response = '';
            
            // Route based on AI-classified intent
            switch ($queryAnalysis['intent']) {
                case 'statistical_analysis':
                    $response = $this->handleStatisticalQuery($message, $queryAnalysis['parameters']);
                    break;
                    
                case 'search_complaints':
                    $response = $this->handleComplaintQuery($message, $queryAnalysis['parameters']);
                    break;
                    
                case 'general_conversation':
                default:
                    $response = $this->handleGeneralQuery($message);
                    break;
            }
            
            // Replace the placeholder with the actual response
            if (isset($this->messages[$responseIndex])) {
                $this->messages[$responseIndex]['content'] = $response;
                unset($this->messages[$responseIndex]['isStreaming']);
            }
            
        } catch (\Exception $e) {
            Log::error('ChatAgent message processing failed', [
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Graceful degradation - show a helpful error instead of breaking
            if (isset($this->messages[$responseIndex])) {
                $this->messages[$responseIndex]['content'] = "I apologize, but I encountered an error while processing your message. Please try again.";
                $this->messages[$responseIndex]['isError'] = true;
                unset($this->messages[$responseIndex]['isStreaming']);
            }
        }
        
        $this->isProcessing = false;
        $this->dispatch('scroll-to-bottom');
    }
    
    /**
     * Use AI to analyze query intent and extract structured parameters.
     *
     * This replaces brittle keyword matching with intelligent classification
     * that can handle complex natural language queries at scale.
     */
    private function analyzeQueryIntent(string $message): array
    {
        try {
            $aiBridge = app(PythonAiBridge::class);
            
            $intentClassificationPrompt = [
                'message' => $message,
                'task' => 'intent_classification',
                'instructions' => 'Analyze this query about complaint data and return JSON with:
                - intent: "statistical_analysis" | "search_complaints" | "general_conversation"  
                - parameters: extracted structured data (complaint_type, borough, time_filter, risk_level, etc.)
                - confidence: 0.0-1.0 confidence score
                - reasoning: brief explanation
                
                IMPORTANT: This system tracks COMPLAINTS about issues, not people or entities being added/removed from databases.
                
                Examples:
                "which borough has the most gun complaints?" -> statistical_analysis with complaint_type=gun, groupby=borough
                "where do most noise complaints come from after 9pm?" -> statistical_analysis with complaint_type=noise, time_filter=after_9pm, groupby=location
                "find water leak complaints in Manhattan" -> search_complaints with complaint_type=water, borough=Manhattan
                "show me high risk complaints" -> search_complaints with risk_level=high
                "any dirty conditions anywhere in the past week?" -> search_complaints with complaint_type=sanitation/dirty, time_filter=past_week
                "are there graffiti issues in Brooklyn?" -> search_complaints with complaint_type=graffiti, borough=Brooklyn
                "has anyone complained about noise?" -> search_complaints with complaint_type=noise
                "what problems were reported yesterday?" -> search_complaints with time_filter=yesterday
                "how many homeless person has been added?" -> general_conversation (asking about adding people, not complaints)
                "how many complaints about homeless people?" -> statistical_analysis with complaint_type=homeless',
                'session_id' => $this->sessionId
            ];
            
            $result = $aiBridge->chat($intentClassificationPrompt);
            
            if (isset($result['response']) && !$result['fallback']) {
                // Try to parse structured JSON response from AI
                $parsed = json_decode($result['response'], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['intent'])) {
                    return [
                        'intent' => $parsed['intent'],
                        'parameters' => $parsed['parameters'] ?? [],
                        'confidence' => $parsed['confidence'] ?? 0.8,
                        'reasoning' => $parsed['reasoning'] ?? ''
                    ];
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('ChatAgent intent analysis failed, falling back to simple heuristics', [
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }
        
        // Fallback to simple heuristics if AI analysis fails
        return $this->fallbackIntentClassification($message);
    }
    
    /**
     * Simple fallback intent classification using basic patterns.
     *
     * Used when AI intent analysis fails, provides basic routing capability.
     */
    private function fallbackIntentClassification(string $message): array
    {
        $lowerMessage = strtolower($message);
        
        // Check for non-complaint queries first (people being added/removed, system operations)
        $nonComplaintPatterns = ['has been added', 'have been added', 'adding people', 'adding users', 'people added', 'users added'];
        $isNonComplaint = false;
        foreach ($nonComplaintPatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                $isNonComplaint = true;
                break;
            }
        }
        
        if ($isNonComplaint) {
            return [
                'intent' => 'general_conversation',
                'parameters' => [],
                'confidence' => 0.8,
                'reasoning' => 'Query about adding/removing entities, not complaints'
            ];
        }
        
        // Statistical query patterns (only for complaint-related queries)
        $statPatterns = ['most', 'how many', 'count', 'statistics', 'breakdown', 'which', 'what are', 'percentage'];
        $complaintRelated = ['complaint', 'report', 'issue', 'problem'];
        
        $isStatistical = false;
        $hasComplaintContext = false;
        
        foreach ($statPatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                $isStatistical = true;
                break;
            }
        }
        
        foreach ($complaintRelated as $context) {
            if (str_contains($lowerMessage, $context)) {
                $hasComplaintContext = true;
                break;
            }
        }
        
        // Only classify as statistical if it's both statistical AND complaint-related
        // OR if it mentions locations/types that suggest complaint analysis
        $locationMentioned = str_contains($lowerMessage, 'borough') || 
                           str_contains($lowerMessage, 'manhattan') || 
                           str_contains($lowerMessage, 'brooklyn') ||
                           str_contains($lowerMessage, 'queens') ||
                           str_contains($lowerMessage, 'bronx') ||
                           str_contains($lowerMessage, 'staten island');
                           
        $complaintTypeMentioned = str_contains($lowerMessage, 'gun') ||
                                str_contains($lowerMessage, 'noise') ||
                                str_contains($lowerMessage, 'water') ||
                                str_contains($lowerMessage, 'heat') ||
                                str_contains($lowerMessage, 'parking');
        
        if ($isStatistical && ($hasComplaintContext || $locationMentioned || $complaintTypeMentioned)) {
            return [
                'intent' => 'statistical_analysis',
                'parameters' => $this->extractBasicParameters($message),
                'confidence' => 0.6,
                'reasoning' => 'Fallback statistical pattern matching with complaint context'
            ];
        }
        
        // Search query patterns - expanded to include conversational queries
        $searchPatterns = [
            'find', 'search', 'show me', 'list', 'get',
            'any', 'are there', 'were there', 'has there been', 'have there been',
            'is there', 'was there', 'anyone', 'anybody', 'someone',
            'what about', 'how about', 'tell me about', 'what problems',
            'what issues', 'what complaints', 'reported', 'complained'
        ];
        $isSearch = false;
        foreach ($searchPatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                $isSearch = true;
                break;
            }
        }
        
        if ($isSearch) {
            return [
                'intent' => 'search_complaints',
                'parameters' => $this->extractBasicParameters($message),
                'confidence' => 0.6,
                'reasoning' => 'Fallback search pattern matching'
            ];
        }
        
        return [
            'intent' => 'general_conversation',
            'parameters' => [],
            'confidence' => 0.5,
            'reasoning' => 'No specific pattern detected'
        ];
    }
    
    /**
     * Extract basic parameters from message for fallback classification.
     */
    private function extractBasicParameters(string $message): array
    {
        $lowerMessage = strtolower($message);
        $parameters = [];
        
        // Extract borough
        $boroughs = ['manhattan', 'brooklyn', 'queens', 'bronx', 'staten island'];
        foreach ($boroughs as $borough) {
            if (str_contains($lowerMessage, $borough)) {
                $parameters['borough'] = strtoupper($borough);
                break;
            }
        }
        
        // Extract complaint type - expanded with synonyms and related terms
        $complaintTypeMap = [
            'sanitation' => ['dirty', 'garbage', 'trash', 'sanitation', 'waste', 'filthy', 'unsanitary', 'litter'],
            'noise' => ['noise', 'loud', 'noisy', 'sound', 'music', 'quiet'],
            'graffiti' => ['graffiti', 'vandalism', 'tagging', 'spray paint', 'defacement'],
            'water' => ['water', 'leak', 'flooding', 'pipe', 'plumbing', 'drip'],
            'heat' => ['heat', 'heating', 'cold', 'hot water', 'boiler', 'temperature'],
            'parking' => ['parking', 'vehicle', 'car', 'truck', 'double parked'],
            'gun' => ['gun', 'shooting', 'weapon', 'firearm', 'shots']
        ];
        
        foreach ($complaintTypeMap as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowerMessage, $keyword)) {
                    $parameters['complaint_type'] = $type;
                    break 2;
                }
            }
        }
        
        // Extract risk level
        if (str_contains($lowerMessage, 'high risk') || str_contains($lowerMessage, 'high-risk') || 
            str_contains($lowerMessage, 'dangerous') || str_contains($lowerMessage, 'danger') ||
            str_contains($lowerMessage, 'urgent') || str_contains($lowerMessage, 'serious') ||
            str_contains($lowerMessage, 'critical') || str_contains($lowerMessage, 'severe')) {
            $parameters['risk_level'] = 'high';
        }
        
        // Extract grouping
        if (str_contains($lowerMessage, 'by borough') || str_contains($lowerMessage, 'borough')) {
            $parameters['group_by'] = 'borough';
        }
        
        // Extract time filters
        if (str_contains($lowerMessage, 'past week') || str_contains($lowerMessage, 'last week') || 
            str_contains($lowerMessage, 'this week')) {
            $parameters['time_filter'] = 'past_week';
        } elseif (str_contains($lowerMessage, 'yesterday')) {
            $parameters['time_filter'] = 'yesterday';
        } elseif (str_contains($lowerMessage, 'today')) {
            $parameters['time_filter'] = 'today';
        } elseif (str_contains($lowerMessage, 'past month') || str_contains($lowerMessage, 'last month')) {
            $parameters['time_filter'] = 'past_month';
        }
        
        return $parameters;
    }
    
    /**
     * Handle requests for statistical analysis using AI-extracted parameters.
     *
     * This builds flexible SQL queries based on the structured parameters
     * extracted by AI, making it infinitely scalable for new query patterns.
     */
    private function handleStatisticalQuery(string $message, array $parameters): string
    {
        try {
            Log::info('ChatAgent statistical query', [
                'message' => $message,
                'parameters' => $parameters
            ]);
            
            // Build flexible statistical query based on parameters
            return $this->buildStatisticalAnalysis($parameters, $message);
            
        } catch (\Exception $e) {
            Log::error('ChatAgent statistical query failed', [
                'message' => $message,
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);
            return "I encountered an error while gathering statistics. Please try asking your question in a different way.";
        }
    }
    
    /**
     * Build flexible statistical analysis based on AI-extracted parameters.
     *
     * This replaces hardcoded query routing with dynamic SQL generation,
     * making the system scalable for any statistical query pattern.
     */
    private function buildStatisticalAnalysis(array $parameters, string $originalMessage): string
    {
        $query = Complaint::query();
        $groupBy = $parameters['group_by'] ?? 'complaint_type';
        $complaintType = $parameters['complaint_type'] ?? null;
        $borough = $parameters['borough'] ?? null;
        $riskLevel = $parameters['risk_level'] ?? null;
        $timeFilter = $parameters['time_filter'] ?? null;
        
        // Apply complaint type filter
        if ($complaintType) {
            $query->where(function ($q) use ($complaintType) {
                $q->where('complaint_type', 'ILIKE', "%{$complaintType}%")
                  ->orWhere('descriptor', 'ILIKE', "%{$complaintType}%");
            });
        }
        
        // Apply borough filter
        if ($borough) {
            $query->where('borough', $borough);
        }
        
        // Apply risk level filter
        if ($riskLevel) {
            $query->whereHas('analysis', function ($q) use ($riskLevel) {
                switch ($riskLevel) {
                    case 'high':
                        $q->where('risk_score', '>=', 0.7);
                        break;
                    case 'medium':
                        $q->whereBetween('risk_score', [0.4, 0.69]);
                        break;
                    case 'low':
                        $q->where('risk_score', '<', 0.4);
                        break;
                }
            });
        }
        
        // Apply time filters (basic implementation)
        if ($timeFilter) {
            if (str_contains($timeFilter, 'after_9pm') || str_contains($timeFilter, 'evening')) {
                $query->whereRaw('EXTRACT(hour FROM submitted_at) >= 21');
            } elseif (str_contains($timeFilter, 'morning')) {
                $query->whereRaw('EXTRACT(hour FROM submitted_at) BETWEEN 6 AND 12');
            } elseif (str_contains($timeFilter, 'weekend')) {
                $query->whereRaw('EXTRACT(dow FROM submitted_at) IN (0, 6)'); // Sunday = 0, Saturday = 6
            }
        }
        
        // Group and count based on the groupBy parameter
        switch ($groupBy) {
            case 'borough':
                $results = $query->select('borough')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('borough')
                    ->orderByDesc('count')
                    ->get();
                return $this->formatBoroughStatistics($results, $complaintType, $originalMessage);
                
            case 'complaint_type':
                $results = $query->select('complaint_type')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('complaint_type')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
                return $this->formatComplaintTypeStatistics($results, $borough, $originalMessage);
                
            case 'time':
                $results = $query->selectRaw('
                    CASE 
                        WHEN EXTRACT(hour FROM submitted_at) BETWEEN 6 AND 12 THEN \'Morning (6AM-12PM)\'
                        WHEN EXTRACT(hour FROM submitted_at) BETWEEN 12 AND 18 THEN \'Afternoon (12PM-6PM)\'
                        WHEN EXTRACT(hour FROM submitted_at) BETWEEN 18 AND 22 THEN \'Evening (6PM-10PM)\'
                        ELSE \'Night (10PM-6AM)\'
                    END as time_period,
                    COUNT(*) as count
                ')
                ->groupByRaw('
                    CASE 
                        WHEN EXTRACT(hour FROM submitted_at) BETWEEN 6 AND 12 THEN \'Morning (6AM-12PM)\'
                        WHEN EXTRACT(hour FROM submitted_at) BETWEEN 12 AND 18 THEN \'Afternoon (12PM-6PM)\'
                        WHEN EXTRACT(hour FROM submitted_at) BETWEEN 18 AND 22 THEN \'Evening (6PM-10PM)\'
                        ELSE \'Night (10PM-6AM)\'
                    END
                ')
                ->orderByDesc('count')
                ->get();
                return $this->formatTimeStatistics($results, $complaintType, $originalMessage);
                
            default:
                // Default to borough breakdown
                $results = $query->select('borough')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('borough')
                    ->orderByDesc('count')
                    ->get();
                return $this->formatBoroughStatistics($results, $complaintType, $originalMessage);
        }
    }
    
    /**
     * Handle searches for specific complaints using AI-extracted parameters.
     *
     * This leverages our hybrid search system to find individual complaints
     * using structured filters extracted by AI from natural language.
     */
    private function handleComplaintQuery(string $message, array $parameters): string
    {
        try {
            // Convert AI parameters to search filters
            $filters = [];
            $options = [
                'limit' => 5,
                'similarity_threshold' => 0.3
            ];
            
            if (isset($parameters['borough'])) {
                $filters['borough'] = $parameters['borough'];
            }
            
            if (isset($parameters['risk_level'])) {
                $filters['risk_level'] = $parameters['risk_level'];
            }
            
            if (isset($parameters['complaint_type'])) {
                // Use complaint type as part of the search query for better semantic matching
                $message = $message . ' ' . $parameters['complaint_type'];
            }
            
            // Apply time filter if present
            if (isset($parameters['time_filter'])) {
                switch ($parameters['time_filter']) {
                    case 'past_week':
                        $filters['created_after'] = now()->subWeek()->toDateString();
                        break;
                    case 'yesterday':
                        $filters['created_after'] = now()->subDay()->startOfDay()->toDateString();
                        $filters['created_before'] = now()->subDay()->endOfDay()->toDateString();
                        break;
                    case 'today':
                        $filters['created_after'] = now()->startOfDay()->toDateString();
                        break;
                    case 'past_month':
                        $filters['created_after'] = now()->subMonth()->toDateString();
                        break;
                }
            }
            
            $searchService = app(HybridSearchService::class);
            $results = $searchService->search($message, $filters, $options);
            
            // If hybrid search returns no results, try a simple database search as fallback
            if (empty($results['results'])) {
                Log::info('ChatAgent hybrid search returned no results, trying database fallback');
                $results = $this->fallbackDatabaseSearch($message, $filters, $options['limit']);
            }
            
            return $this->formatComplaintResults($results);
            
        } catch (\Exception $e) {
            Log::error('ChatAgent complaint search failed', [
                'message' => $message,
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);
            return $this->handleGeneralQuery($message);
        }
    }
    
    /**
     * Format borough statistics with contextual messaging.
     */
    private function formatBoroughStatistics($results, $complaintType, $originalMessage): string
    {
        if ($results->isEmpty()) {
            $typeText = $complaintType ? "{$complaintType} " : '';
            return "I couldn't find any {$typeText}complaints in the database.";
        }
        
        $total = $results->sum('count');
        $typeText = $complaintType ? "{$complaintType} " : '';
        $response = "**Borough breakdown for {$typeText}complaints:**\n\n";
        
        foreach ($results as $index => $borough) {
            $percentage = $total > 0 ? round(($borough->count / $total) * 100, 1) : 0;
            $indicator = $index === 0 ? ' 👑 **MOST**' : '';
            $response .= ($index + 1) . ". **{$borough->borough}**{$indicator} - {$borough->count} complaints ({$percentage}%)\n";
        }
        
        $topBorough = $results->first();
        $response .= "\n**Answer:** {$topBorough->borough} has the most {$typeText}complaints with {$topBorough->count} reports.";
        $response .= "\n\n**Total {$typeText}complaints:** {$total}";
        
        return $response;
    }
    
    /**
     * Format complaint type statistics with contextual messaging.
     */
    private function formatComplaintTypeStatistics($results, $borough, $originalMessage): string
    {
        if ($results->isEmpty()) {
            $boroughText = $borough ? "in {$borough} " : '';
            return "I couldn't find any complaints {$boroughText}in the database.";
        }
        
        $total = $results->sum('count');
        $boroughText = $borough ? "in {$borough} " : '';
        $response = "**Most common complaint types {$boroughText}:**\n\n";
        
        foreach ($results as $index => $type) {
            $percentage = $total > 0 ? round(($type->count / $total) * 100, 1) : 0;
            $response .= ($index + 1) . ". **{$type->complaint_type}** - {$type->count} complaints ({$percentage}%)\n";
        }
        
        $response .= "\n**Total complaints {$boroughText}:** {$total}";
        return $response;
    }
    
    /**
     * Format time-based statistics with contextual messaging.
     */
    private function formatTimeStatistics($results, $complaintType, $originalMessage): string
    {
        if ($results->isEmpty()) {
            $typeText = $complaintType ? "{$complaintType} " : '';
            return "I couldn't find any {$typeText}complaints with time data.";
        }
        
        $total = $results->sum('count');
        $typeText = $complaintType ? "{$complaintType} " : '';
        $response = "**{$typeText}Complaints by time of day:**\n\n";
        
        foreach ($results as $index => $timeSlot) {
            $percentage = $total > 0 ? round(($timeSlot->count / $total) * 100, 1) : 0;
            $indicator = $index === 0 ? ' 🕐 **PEAK TIME**' : '';
            $response .= ($index + 1) . ". **{$timeSlot->time_period}**{$indicator} - {$timeSlot->count} complaints ({$percentage}%)\n";
        }
        
        $peakTime = $results->first();
        $response .= "\n**Answer:** Most {$typeText}complaints occur during {$peakTime->time_period} with {$peakTime->count} reports.";
        $response .= "\n\n**Total {$typeText}complaints:** {$total}";
        
        return $response;
    }
    
    /**
     * Handle general conversational queries using the AI bridge.
     *
     * This provides context-aware responses for questions that don't fit
     * the statistical or search patterns, using our Python AI system.
     */
    private function handleGeneralQuery(string $message): string
    {
        $lowerMessage = strtolower($message);
        
        // Check for specific misunderstood query patterns and provide helpful explanations
        if (str_contains($lowerMessage, 'has been added') || str_contains($lowerMessage, 'have been added')) {
            return "I understand you're asking about adding records to a system, but LaraCity tracks **complaints about issues**, not adding people or entities to databases.\n\n" .
                   "If you're looking for:\n" .
                   "• **Complaints involving homeless people** → Try: \"show me complaints about homeless people\"\n" .
                   "• **Statistics about housing complaints** → Try: \"how many housing complaints are there?\"\n" .
                   "• **General complaint statistics** → Try: \"what are the most common complaint types?\"\n\n" .
                   "How can I help you analyze complaint data instead?";
        }
        
        // Provide recent complaint context to make AI responses more relevant
        $recentComplaints = Complaint::with('analysis')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'type' => $c->complaint_type,
                'description' => substr($c->descriptor ?? '', 0, 100),
                'risk_score' => $c->analysis?->risk_score ?? 0,
                'borough' => $c->borough
            ])
            ->toArray();
        
        try {
            $aiBridge = app(PythonAiBridge::class);
            $result = $aiBridge->chat([
                'message' => $message,
                'session_id' => $this->sessionId,
                'complaint_data' => $recentComplaints,
                'context' => 'This system analyzes NYC 311 complaints, not adding/removing people from databases.'
            ]);
            
            if (isset($result['response']) && !$result['fallback']) {
                return $result['response'];
            }
            
        } catch (\Exception $e) {
            // Python AI bridge unavailable - provide helpful fallback
            Log::warning('ChatAgent Python bridge failed', [
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }
        
        // Instead of generic fallback, try to provide a more helpful response
        Log::info('ChatAgent using fallback response', ['message' => $message]);
        
        return "I'm here to help you with LaraCity complaints data. You can ask me to search for specific complaints, show statistics by borough, find high-risk complaints, or analyze patterns in the data. How can I assist you?";
    }
    
    /**
     * Generate a ranked list of the most frequently reported complaint types.
     *
     * This uses SQL aggregation to efficiently count complaints by type,
     * avoiding the need to load individual records into memory.
     */
    private function getMostCommonComplaintTypes(): string
    {
        $complaintTypes = Complaint::select('complaint_type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('complaint_type')
            ->orderByDesc('count')
            ->limit(10)  // Top 10 keeps the response readable
            ->get();
            
        if ($complaintTypes->isEmpty()) {
            return "I couldn't find any complaint data to analyze.";
        }
        
        $response = "**Most Common Complaint Types:**\n\n";
        $total = Complaint::count();
        
        foreach ($complaintTypes as $index => $type) {
            $percentage = $total > 0 ? round(($type->count / $total) * 100, 1) : 0;
            $response .= ($index + 1) . ". **{$type->complaint_type}** - {$type->count} complaints ({$percentage}%)\n";
        }
        
        $response .= "\n**Total Complaints:** {$total}";
        return $response;
    }
    
    /**
     * Break down complaint volume by NYC borough.
     *
     * Useful for understanding geographic distribution patterns
     * and identifying which areas generate the most reports.
     */
    private function getComplaintsByBorough(): string
    {
        $boroughStats = Complaint::select('borough')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('borough')
            ->orderByDesc('count')  // Show highest volume boroughs first
            ->get();
            
        if ($boroughStats->isEmpty()) {
            return "I couldn't find any complaint data by borough.";
        }
        
        $response = "**Complaints by Borough:**\n\n";
        $total = Complaint::count();
        
        foreach ($boroughStats as $borough) {
            $percentage = $total > 0 ? round(($borough->count / $total) * 100, 1) : 0;
            $response .= "• **{$borough->borough}** - {$borough->count} complaints ({$percentage}%)\n";
        }
        
        return $response;
    }
    
    /**
     * Analyze risk score distribution from AI-generated assessments.
     *
     * This provides insight into how our AI rates complaints by severity,
     * helping identify patterns in public safety concerns.
     */
    private function getRiskLevelDistribution(): string
    {
        $riskStats = DB::table('complaint_analysis')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN risk_score >= 0.7 THEN 1 ELSE 0 END) as high_risk,
                SUM(CASE WHEN risk_score >= 0.4 AND risk_score < 0.7 THEN 1 ELSE 0 END) as medium_risk,
                SUM(CASE WHEN risk_score < 0.4 THEN 1 ELSE 0 END) as low_risk,
                AVG(risk_score) as avg_risk_score
            ')
            ->first();
            
        if (!$riskStats || $riskStats->total == 0) {
            return "I couldn't find any risk analysis data yet. Complaints need to be analyzed first.";
        }
        
        $response = "**Risk Level Distribution:**\n\n";
        $response .= "• **High Risk (≥0.7):** {$riskStats->high_risk} complaints (" . round(($riskStats->high_risk / $riskStats->total) * 100, 1) . "%)\n";
        $response .= "• **Medium Risk (0.4-0.69):** {$riskStats->medium_risk} complaints (" . round(($riskStats->medium_risk / $riskStats->total) * 100, 1) . "%)\n";
        $response .= "• **Low Risk (<0.4):** {$riskStats->low_risk} complaints (" . round(($riskStats->low_risk / $riskStats->total) * 100, 1) . "%)\n\n";
        $response .= "**Average Risk Score:** " . number_format((float) $riskStats->avg_risk_score, 2) . "\n";
        $response .= "**Total Analyzed:** {$riskStats->total} complaints";
        
        return $response;
    }
    
    /**
     * Find which borough has the most complaints of a specific type.
     *
     * Parses complaint type from natural language queries like
     * "which borough has the most gun complaints?" and returns
     * borough-by-borough breakdown for that specific complaint type.
     */
    private function getBoroughWithMostComplaints(string $message): string
    {
        $lowerMessage = strtolower($message);
        
        // Extract complaint type keywords from the message
        $complaintTypeMap = [
            'gun' => ['gun', 'firearm', 'weapon', 'shooting'],
            'noise' => ['noise', 'loud', 'music', 'sound'],
            'water' => ['water', 'leak', 'pipe', 'plumbing'],
            'heat' => ['heat', 'heating', 'hot water', 'boiler'],
            'parking' => ['parking', 'vehicle', 'car', 'truck'],
            'sanitation' => ['garbage', 'trash', 'sanitation', 'waste'],
            'graffiti' => ['graffiti', 'vandalism', 'spray paint'],
            'street' => ['street', 'road', 'pothole', 'sidewalk'],
            'animal' => ['animal', 'dog', 'cat', 'rat', 'pest'],
            'drug' => ['drug', 'narcotic', 'substance', 'illegal']
        ];
        
        $detectedTypes = [];
        foreach ($complaintTypeMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowerMessage, $keyword)) {
                    $detectedTypes[] = $category;
                    break;
                }
            }
        }
        
        // Build the query
        $query = Complaint::select('borough')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('borough')
            ->orderByDesc('count');
        
        // Apply complaint type filter if detected
        if (!empty($detectedTypes)) {
            $query->where(function ($q) use ($detectedTypes) {
                foreach ($detectedTypes as $type) {
                    switch ($type) {
                        case 'gun':
                            $q->orWhere('complaint_type', 'ILIKE', '%weapon%')
                              ->orWhere('complaint_type', 'ILIKE', '%gun%')
                              ->orWhere('complaint_type', 'ILIKE', '%firearm%')
                              ->orWhere('complaint_type', 'ILIKE', '%shooting%')
                              ->orWhere('descriptor', 'ILIKE', '%gun%')
                              ->orWhere('descriptor', 'ILIKE', '%weapon%')
                              ->orWhere('descriptor', 'ILIKE', '%firearm%')
                              ->orWhere('descriptor', 'ILIKE', '%shooting%');
                            break;
                        case 'noise':
                            $q->orWhere('complaint_type', 'ILIKE', '%noise%')
                              ->orWhere('descriptor', 'ILIKE', '%noise%')
                              ->orWhere('descriptor', 'ILIKE', '%loud%');
                            break;
                        case 'water':
                            $q->orWhere('complaint_type', 'ILIKE', '%water%')
                              ->orWhere('descriptor', 'ILIKE', '%water%')
                              ->orWhere('descriptor', 'ILIKE', '%leak%')
                              ->orWhere('descriptor', 'ILIKE', '%pipe%');
                            break;
                        case 'heat':
                            $q->orWhere('complaint_type', 'ILIKE', '%heat%')
                              ->orWhere('complaint_type', 'ILIKE', '%hot water%')
                              ->orWhere('descriptor', 'ILIKE', '%heat%')
                              ->orWhere('descriptor', 'ILIKE', '%heating%');
                            break;
                        case 'parking':
                            $q->orWhere('complaint_type', 'ILIKE', '%parking%')
                              ->orWhere('descriptor', 'ILIKE', '%parking%')
                              ->orWhere('descriptor', 'ILIKE', '%vehicle%');
                            break;
                        case 'sanitation':
                            $q->orWhere('complaint_type', 'ILIKE', '%sanitation%')
                              ->orWhere('complaint_type', 'ILIKE', '%garbage%')
                              ->orWhere('descriptor', 'ILIKE', '%trash%')
                              ->orWhere('descriptor', 'ILIKE', '%garbage%');
                            break;
                        case 'graffiti':
                            $q->orWhere('complaint_type', 'ILIKE', '%graffiti%')
                              ->orWhere('descriptor', 'ILIKE', '%graffiti%')
                              ->orWhere('descriptor', 'ILIKE', '%vandalism%');
                            break;
                        case 'street':
                            $q->orWhere('complaint_type', 'ILIKE', '%street%')
                              ->orWhere('complaint_type', 'ILIKE', '%road%')
                              ->orWhere('descriptor', 'ILIKE', '%pothole%')
                              ->orWhere('descriptor', 'ILIKE', '%sidewalk%');
                            break;
                        case 'animal':
                            $q->orWhere('complaint_type', 'ILIKE', '%animal%')
                              ->orWhere('descriptor', 'ILIKE', '%animal%')
                              ->orWhere('descriptor', 'ILIKE', '%dog%')
                              ->orWhere('descriptor', 'ILIKE', '%rat%');
                            break;
                        case 'drug':
                            $q->orWhere('complaint_type', 'ILIKE', '%drug%')
                              ->orWhere('descriptor', 'ILIKE', '%drug%')
                              ->orWhere('descriptor', 'ILIKE', '%narcotic%');
                            break;
                    }
                }
            });
        }
        
        $boroughStats = $query->get();
        
        if ($boroughStats->isEmpty()) {
            $typeText = !empty($detectedTypes) ? implode('/', $detectedTypes) . ' ' : '';
            return "I couldn't find any {$typeText}complaints in the database.";
        }
        
        $total = $boroughStats->sum('count');
        $typeText = !empty($detectedTypes) ? implode('/', $detectedTypes) . ' ' : '';
        $response = "**Borough breakdown for {$typeText}complaints:**\n\n";
        
        foreach ($boroughStats as $index => $borough) {
            $percentage = $total > 0 ? round(($borough->count / $total) * 100, 1) : 0;
            $indicator = $index === 0 ? ' 👑 **MOST**' : '';
            $response .= ($index + 1) . ". **{$borough->borough}**{$indicator} - {$borough->count} complaints ({$percentage}%)\n";
        }
        
        $topBorough = $boroughStats->first();
        $response .= "\n**Answer:** {$topBorough->borough} has the most {$typeText}complaints with {$topBorough->count} reports.";
        $response .= "\n\n**Total {$typeText}complaints:** {$total}";
        
        return $response;
    }
    
    /**
     * Provide a high-level overview of the complaint dataset.
     *
     * This serves as a dashboard summary when users ask for general
     * statistics without specifying a particular dimension.
     */
    private function getGeneralStatistics(): string
    {
        $totalComplaints = Complaint::count();
        $analyzedComplaints = DB::table('complaint_analysis')->count();
        $recentComplaints = Complaint::where('created_at', '>=', now()->subDays(7))->count();
        
        $response = "**LaraCity Complaints Overview:**\n\n";
        $response .= "• **Total Complaints:** {$totalComplaints}\n";
        $response .= "• **AI Analyzed:** {$analyzedComplaints}\n";
        $response .= "• **This Week:** {$recentComplaints}\n\n";
        
        if ($totalComplaints > 0) {
            $response .= "**Most Active Borough:** ";
            $topBorough = Complaint::select('borough')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('borough')
                ->orderByDesc('count')
                ->first();
            $response .= $topBorough ? $topBorough->borough : "Unknown";
            $response .= "\n\n";
        }
        
        $response .= "Ask me for more specific statistics like 'most common complaint types' or 'complaints by borough'!";
        
        return $response;
    }
    
    /**
     * Format search results into a readable chat response.
     *
     * This handles both enhanced array data from our search service and
     * raw Eloquent models, presenting them in a consistent format.
     */
    private function formatComplaintResults(array $results): string
    {
        if (empty($results['results'])) {
            return "I couldn't find any complaints matching your query. Try searching with different keywords or ask me about specific complaint types, boroughs, or risk levels.";
        }

        $response = "I found " . count($results['results']) . " relevant complaints:\n\n";
        
        foreach ($results['results'] as $index => $result) {
            // Handle both enhanced (array) and raw (Eloquent model) complaint data
            $complaint = $result['complaint'];
            if (is_array($complaint)) {
                // Enhanced complaint data from HybridSearchService
                $complaintNumber = $complaint['complaint_number'] ?? 'Unknown';
                $type = $complaint['type'] ?? 'Unknown';
                $borough = $complaint['borough'] ?? 'Unknown';
                $status = $complaint['status'] ?? 'Unknown';
                $description = $complaint['description'] ?? '';
                $analysis = $complaint['analysis'] ?? null;
            } else {
                // Raw Eloquent model from fallback searches
                $complaintNumber = $complaint->complaint_number ?? 'Unknown';
                $type = $complaint->complaint_type ?? 'Unknown';
                $borough = $complaint->borough ?? 'Unknown';
                $status = $complaint->status ?? 'Unknown';
                $description = $complaint->descriptor ?? '';
                $analysis = $complaint->analysis ?? null;
            }
            
            $response .= ($index + 1) . ". **Complaint #{$complaintNumber}**\n";
            $response .= "   - Type: {$type}\n";
            $response .= "   - Location: {$borough}\n";
            $response .= "   - Status: {$status}\n";
            
            // Include AI risk assessment if available
            if ($analysis) {
                $riskScore = is_array($analysis) ? $analysis['risk_score'] : $analysis->risk_score;
                // Ensure risk score is numeric
                $riskScore = (float) $riskScore;
                $riskLevel = $riskScore >= 0.7 ? 'High' : 
                            ($riskScore >= 0.4 ? 'Medium' : 'Low');
                $response .= "   - Risk Level: {$riskLevel} (" . number_format($riskScore, 2) . ")\n";
            }
            
            // Only show description if it's not empty
            if (!empty($description)) {
                $truncatedDescription = strlen($description) > 100 
                    ? substr($description, 0, 100) . "..." 
                    : $description;
                $response .= "   - Description: {$truncatedDescription}\n\n";
            } else {
                $response .= "   - Description: No description available\n\n";
            }
        }

        $response .= "\nWould you like more details about any of these complaints or search for something else?";
        
        return $response;
    }
    
    /**
     * Fallback database search when hybrid search returns no results.
     *
     * This uses simple SQL LIKE queries to find complaints when vector search fails.
     * It's less sophisticated but provides better coverage for basic searches.
     */
    private function fallbackDatabaseSearch(string $message, array $filters, int $limit): array
    {
        Log::info('ChatAgent performing fallback database search', [
            'message' => $message,
            'filters' => $filters
        ]);
        
        $query = Complaint::with('analysis');
        
        // Apply existing filters
        if (!empty($filters['borough'])) {
            $query->where('borough', $filters['borough']);
        }
        
        if (!empty($filters['risk_level'])) {
            $query->whereHas('analysis', function ($q) use ($filters) {
                switch ($filters['risk_level']) {
                    case 'high':
                        $q->where('risk_score', '>=', 0.7);
                        break;
                    case 'medium':
                        $q->whereBetween('risk_score', [0.4, 0.69]);
                        break;
                    case 'low':
                        $q->where('risk_score', '<', 0.4);
                        break;
                }
            });
        }
        
        // Simple text search in complaint type and descriptor
        $searchTerms = explode(' ', strtolower($message));
        $searchTerms = array_filter($searchTerms, fn($term) => strlen($term) > 2); // Filter short words
        
        if (!empty($searchTerms)) {
            $query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('complaint_type', 'ILIKE', "%{$term}%")
                      ->orWhere('descriptor', 'ILIKE', "%{$term}%");
                }
            });
        }
        
        $complaints = $query->latest()->limit($limit)->get();
        
        Log::info('ChatAgent fallback search results', [
            'found_count' => $complaints->count(),
            'search_terms' => $searchTerms,
        ]);
        
        // Format results in the same structure as HybridSearchService
        return [
            'results' => $complaints->map(function ($complaint) {
                return [
                    'complaint' => $complaint,
                    'similarity' => 0.5, // Indicate this is a fallback match
                ];
            })->toArray(),
            'metadata' => [
                'query' => $message,
                'total_results' => $complaints->count(),
                'search_type' => 'fallback_database',
            ]
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard.chat-agent');
    }
}