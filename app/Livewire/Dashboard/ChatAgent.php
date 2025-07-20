<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Complaint;
use App\Services\HybridSearchService;
use App\Services\PythonAiBridge;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatAgent extends Component
{
    public array $messages = [];
    public string $userMessage = '';
    public bool $isProcessing = false;
    public string $sessionId;
    
    protected $listeners = [
        'messageReceived' => 'addMessage',
        'chatResponseChunk' => 'handleResponseChunk',
        'chatResponseComplete' => 'handleResponseComplete',
        'chatResponseError' => 'handleResponseError'
    ];

    public function mount(): void
    {
        $this->sessionId = 'chat-' . auth()->id() . '-' . uniqid();
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Hello! I\'m your LaraCity AI assistant. I can help you understand complaints, search for patterns, and provide insights about the data. How can I assist you today?',
                'timestamp' => now()->toISOString(),
            ]
        ];
    }

    public function sendMessage(): void
    {
        // Validate input
        $this->validate([
            'userMessage' => 'required|string|max:1000'
        ]);

        if (empty(trim($this->userMessage))) {
            return;
        }

        // Add user message to chat
        $this->messages[] = [
            'role' => 'user',
            'content' => trim($this->userMessage),
            'timestamp' => now()->toISOString(),
        ];

        $userMessage = trim($this->userMessage);
        $this->userMessage = '';
        $this->isProcessing = true;

        // Add placeholder for assistant response
        $responseIndex = count($this->messages);
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '',
            'timestamp' => now()->toISOString(),
            'isStreaming' => true,
        ];

        // Process message directly for simplicity
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

    private function processMessage(string $message, int $responseIndex): void
    {
        try {
            $response = '';
            
            // Check if it's a complaint-related query
            if ($this->isComplaintQuery($message)) {
                $response = $this->handleComplaintQuery($message);
            } else {
                $response = $this->handleGeneralQuery($message);
            }
            
            // Update the message with the response
            if (isset($this->messages[$responseIndex])) {
                $this->messages[$responseIndex]['content'] = $response;
                unset($this->messages[$responseIndex]['isStreaming']);
            }
            
        } catch (\Exception $e) {
            // Handle error
            if (isset($this->messages[$responseIndex])) {
                $this->messages[$responseIndex]['content'] = "I apologize, but I encountered an error while processing your message. Please try again.";
                $this->messages[$responseIndex]['isError'] = true;
                unset($this->messages[$responseIndex]['isStreaming']);
            }
        }
        
        $this->isProcessing = false;
        $this->dispatch('scroll-to-bottom');
    }
    
    private function isComplaintQuery(string $message): bool
    {
        $keywords = ['complaint', 'search', 'find', 'show', 'list', 'how many', 'borough', 'status', 'risk', 'manhattan', 'brooklyn', 'queens', 'bronx', 'staten island'];
        $lowerMessage = strtolower($message);
        
        foreach ($keywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function handleComplaintQuery(string $message): string
    {
        try {
            $searchService = app(HybridSearchService::class);
            $results = $searchService->search($message, [], [
                'limit' => 5,
                'similarity_threshold' => 0.6
            ]);
            
            // Log the results for debugging
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
            return $this->handleGeneralQuery($message);
        }
    }
    
    private function handleGeneralQuery(string $message): string
    {
        // Get some recent complaints for context
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
            // Fall back to simple response
        }
        
        return "I'm here to help you with LaraCity complaints data. You can ask me to search for specific complaints, show statistics by borough, find high-risk complaints, or analyze patterns in the data. How can I assist you?";
    }
    
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
                // Raw Eloquent model
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