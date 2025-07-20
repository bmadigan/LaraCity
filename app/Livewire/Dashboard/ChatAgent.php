<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Complaint;
use App\Services\HybridSearchService;
use App\Services\PythonAiBridge;
use Illuminate\Support\Facades\DB;
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
        $message = $this->messages->get($responseIndex);
        if ($message) {
            $message['content'] = "I apologize, but I encountered an error: {$error}";
            $message['isError'] = true;
            unset($message['isStreaming']);
            $this->messages->put($responseIndex, $message);
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
     * Intelligently route user messages to the appropriate handler.
     *
     * The key insight here is that different types of questions require
     * fundamentally different approaches: statistics need database aggregation,
     * searches need vector similarity, and conversations need AI reasoning.
     */
    private function processMessage(string $message, int $responseIndex): void
    {
        try {
            $response = '';
            
            // Route based on query intent rather than trying one-size-fits-all
            if ($this->isStatisticalQuery($message)) {
                $response = $this->handleStatisticalQuery($message);
            } elseif ($this->isComplaintQuery($message)) {
                $response = $this->handleComplaintQuery($message);
            } else {
                $response = $this->handleGeneralQuery($message);
            }
            
            // Replace the placeholder with the actual response
            if (isset($this->messages[$responseIndex])) {
                $this->messages[$responseIndex]['content'] = $response;
                unset($this->messages[$responseIndex]['isStreaming']);
            }
            
        } catch (\Exception $e) {
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
     * Determine if the user wants to search for specific complaints.
     *
     * These queries should use vector similarity to find individual
     * complaints that match the user's criteria.
     */
    private function isComplaintQuery(string $message): bool
    {
        $searchKeywords = ['search', 'find', 'show me complaints', 'list complaints', 'graffiti', 'noise', 'water'];
        $lowerMessage = strtolower($message);
        
        foreach ($searchKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Determine if the user wants statistical analysis of the data.
     *
     * These queries should use database aggregation to provide summaries,
     * counts, and breakdowns rather than individual complaint records.
     */
    private function isStatisticalQuery(string $message): bool
    {
        $statsKeywords = ['most common', 'how many', 'statistics', 'count', 'total', 'percentage', 'breakdown', 'distribution', 'trends'];
        $lowerMessage = strtolower($message);
        
        foreach ($statsKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle requests for statistical analysis using database aggregation.
     *
     * Rather than searching for specific complaints, this generates summaries
     * and insights from the entire dataset using SQL aggregation functions.
     */
    private function handleStatisticalQuery(string $message): string
    {
        try {
            $lowerMessage = strtolower($message);
            
            // Route to the most specific handler first
            if (str_contains($lowerMessage, 'most common') && str_contains($lowerMessage, 'complaint type')) {
                return $this->getMostCommonComplaintTypes();
            }
            
            if (str_contains($lowerMessage, 'borough') && (str_contains($lowerMessage, 'how many') || str_contains($lowerMessage, 'count'))) {
                return $this->getComplaintsByBorough();
            }
            
            if (str_contains($lowerMessage, 'risk') && (str_contains($lowerMessage, 'distribution') || str_contains($lowerMessage, 'breakdown'))) {
                return $this->getRiskLevelDistribution();
            }
            
            // Fallback to general statistics for unmatched statistical queries
            return $this->getGeneralStatistics();
            
        } catch (\Exception $e) {
            \Log::error('ChatAgent statistical query failed', [
                'message' => $message,
                'error' => $e->getMessage()
            ]);
            return "I encountered an error while gathering statistics. Please try asking your question in a different way.";
        }
    }
    
    /**
     * Handle searches for specific complaints using vector similarity.
     *
     * This leverages our hybrid search system to find individual complaints
     * that semantically match the user's query, rather than aggregate data.
     */
    private function handleComplaintQuery(string $message): string
    {
        try {
            $searchService = app(HybridSearchService::class);
            $results = $searchService->search($message, [], [
                'limit' => 5,
                'similarity_threshold' => 0.6  // Reasonable threshold for chat queries
            ]);
            
            // Log search metrics for monitoring and debugging
            \Log::info('ChatAgent search results', [
                'query' => $message,
                'results_count' => count($results['results'] ?? []),
                'has_results' => !empty($results['results']),
                'first_result_type' => !empty($results['results']) ? gettype($results['results'][0]['complaint'] ?? null) : 'none'
            ]);
            
            return $this->formatComplaintResults($results);
            
        } catch (\Exception $e) {
            \Log::error('ChatAgent complaint query failed', [
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Graceful fallback to general AI handling if search fails
            return $this->handleGeneralQuery($message);
        }
    }
    
    /**
     * Handle general conversational queries using the AI bridge.
     *
     * This provides context-aware responses for questions that don't fit
     * the statistical or search patterns, using our Python AI system.
     */
    private function handleGeneralQuery(string $message): string
    {
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
                'complaint_data' => $recentComplaints
            ]);
            
            if ($result['success'] && isset($result['data']['response'])) {
                return $result['data']['response'];
            }
            
        } catch (\Exception $e) {
            // Python AI bridge unavailable - provide helpful fallback
        }
        
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
        $response .= "**Average Risk Score:** " . number_format($riskStats->avg_risk_score, 2) . "\n";
        $response .= "**Total Analyzed:** {$riskStats->total} complaints";
        
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
                $complaintNumber = $complaint['complaint_number'];
                $type = $complaint['type'];
                $borough = $complaint['borough'];
                $status = $complaint['status'];
                $description = $complaint['description'];
                $analysis = $complaint['analysis'] ?? null;
            } else {
                // Raw Eloquent model from fallback searches
                $complaintNumber = $complaint->complaint_number;
                $type = $complaint->complaint_type;
                $borough = $complaint->borough;
                $status = $complaint->status;
                $description = $complaint->descriptor;
                $analysis = $complaint->analysis ?? null;
            }
            
            $response .= ($index + 1) . ". **Complaint #{$complaintNumber}**\n";
            $response .= "   - Type: {$type}\n";
            $response .= "   - Location: {$borough}\n";
            $response .= "   - Status: {$status}\n";
            
            // Include AI risk assessment if available
            if ($analysis) {
                $riskScore = is_array($analysis) ? $analysis['risk_score'] : $analysis->risk_score;
                $riskLevel = $riskScore >= 0.7 ? 'High' : 
                            ($riskScore >= 0.4 ? 'Medium' : 'Low');
                $response .= "   - Risk Level: {$riskLevel} (" . number_format($riskScore, 2) . ")\n";
            }
            
            $response .= "   - Description: " . substr($description, 0, 100) . "...\n\n";
        }

        $response .= "\nWould you like more details about any of these complaints or search for something else?";
        
        return $response;
    }

    public function render(): View
    {
        return view('livewire.dashboard.chat-agent');
    }
}