<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Complaint;
use App\Services\HybridSearchService;
use App\Services\PythonAiBridge;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChatMessage
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public string $message,
        public string $sessionId,
        public int $responseIndex,
        public int $userId
    ) {}

    public function handle(PythonAiBridge $aiBridge, HybridSearchService $searchService): void
    {
        try {
            // First, check if the message is asking about complaints
            if ($this->isComplaintQuery($this->message)) {
                $this->handleComplaintQuery($searchService);
                return;
            }

            // Otherwise, use the AI chat service
            $this->handleAiChat($aiBridge);
            
        } catch (\Exception $e) {
            Log::error('Chat processing failed', [
                'message' => $this->message,
                'sessionId' => $this->sessionId,
                'error' => $e->getMessage()
            ]);

            // Store error in cache
            cache()->put("chat:{$this->sessionId}:error", 'Failed to process your message. Please try again.', 300);
        }
    }

    private function isComplaintQuery(string $message): bool
    {
        $keywords = ['complaint', 'search', 'find', 'show', 'list', 'how many', 'borough', 'status', 'risk'];
        $lowerMessage = strtolower($message);
        
        foreach ($keywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    private function handleComplaintQuery(HybridSearchService $searchService): void
    {
        try {
            // Use semantic search to find relevant complaints
            $results = $searchService->search($this->message, [], [
                'limit' => 5,
                'similarity_threshold' => 0.6
            ]);

            $response = $this->formatComplaintResults($results);
            
            // Emit the complete response
            event(new \App\Events\ChatResponseChunk(
                $this->sessionId,
                $this->responseIndex,
                $response
            ));

            event(new \App\Events\ChatResponseComplete(
                $this->sessionId,
                $this->responseIndex
            ));

        } catch (\Exception $e) {
            $this->handleAiChat(app(PythonAiBridge::class));
        }
    }

    private function handleAiChat(PythonAiBridge $aiBridge): void
    {
        // Get recent complaints for context
        $recentComplaints = Complaint::with('analysis')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'type' => $c->complaint_type,
                'description' => substr($c->complaint_description, 0, 100),
                'risk_score' => $c->analysis?->risk_score ?? 0,
                'borough' => $c->borough
            ])
            ->toArray();

        $result = $aiBridge->chat([
            'message' => $this->message,
            'session_id' => $this->sessionId,
            'complaint_data' => $recentComplaints,
            'stream' => true
        ]);

        if ($result['success'] && isset($result['data']['response'])) {
            // For demo purposes, we'll simulate streaming by chunking the response
            $response = $result['data']['response'];
            $chunks = $this->chunkResponse($response);
            
            foreach ($chunks as $chunk) {
                event(new \App\Events\ChatResponseChunk(
                    $this->sessionId,
                    $this->responseIndex,
                    $chunk
                ));
                
                // Small delay to simulate streaming
                usleep(50000); // 50ms
            }

            event(new \App\Events\ChatResponseComplete(
                $this->sessionId,
                $this->responseIndex
            ));
        } else {
            throw new \Exception('Failed to get AI response');
        }
    }

    private function formatComplaintResults(array $results): string
    {
        if (empty($results['results'])) {
            return "I couldn't find any complaints matching your query. Try searching with different keywords or ask me about specific complaint types, boroughs, or risk levels.";
        }

        $response = "I found " . count($results['results']) . " relevant complaints:\n\n";
        
        foreach ($results['results'] as $index => $result) {
            $complaint = $result['complaint'];
            $analysis = $complaint['analysis'] ?? null;
            
            $response .= ($index + 1) . ". **Complaint #{$complaint['complaint_number']}**\n";
            $response .= "   - Type: {$complaint['complaint_type']}\n";
            $response .= "   - Location: {$complaint['borough']}\n";
            $response .= "   - Status: {$complaint['status']}\n";
            
            if ($analysis) {
                $riskLevel = $analysis['risk_score'] >= 0.7 ? 'High' : 
                            ($analysis['risk_score'] >= 0.4 ? 'Medium' : 'Low');
                $response .= "   - Risk Level: {$riskLevel} ({$analysis['risk_score']})\n";
            }
            
            $response .= "   - Description: " . substr($complaint['complaint_description'], 0, 100) . "...\n\n";
        }

        $response .= "\nWould you like more details about any of these complaints?";
        
        return $response;
    }

    private function chunkResponse(string $response, int $chunkSize = 20): array
    {
        // Split response into words
        $words = explode(' ', $response);
        $chunks = [];
        
        for ($i = 0; $i < count($words); $i += $chunkSize) {
            $chunk = implode(' ', array_slice($words, $i, $chunkSize));
            if ($i + $chunkSize < count($words)) {
                $chunk .= ' ';
            }
            $chunks[] = $chunk;
        }
        
        return $chunks;
    }
}