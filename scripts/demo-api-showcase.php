#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Demo Script: API Endpoint Showcase
 * 
 * Educational Focus:
 * - REST API integration patterns
 * - Authentication with Laravel Sanctum
 * - Advanced filtering and pagination
 * - Error handling and response formats
 * 
 * This script demonstrates all LaraCity API endpoints
 * with practical usage examples and response handling.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class ApiShowcaseDemo
{
    private string $baseUrl;
    private string $apiToken;
    private array $apiMetrics = [];

    public function __construct()
    {
        $this->baseUrl = config('app.url') . '/api';
    }

    public function runDemo(): void
    {
        $this->printHeader();
        
        // Step 1: Authentication setup
        if (!$this->setupAuthentication()) {
            $this->printError("Failed to setup authentication");
            return;
        }
        
        // Step 2: Demonstrate complaint endpoints
        $this->demonstrateComplaintEndpoints();
        
        // Step 3: Demonstrate search endpoints
        $this->demonstrateSearchEndpoints();
        
        // Step 4: Demonstrate action endpoints
        $this->demonstrateActionEndpoints();
        
        // Step 5: Show API performance metrics
        $this->displayApiMetrics();
        
        $this->printFooter();
    }
    
    private function setupAuthentication(): bool
    {
        $this->printStep("🔐 AUTHENTICATION SETUP", "Creating API token for demonstration");
        
        try {
            // Create or find demo user
            $user = User::firstOrCreate(
                ['email' => 'demo@laracity.local'],
                [
                    'name' => 'Demo User',
                    'password' => Hash::make('demo-password'),
                    'email_verified_at' => now()
                ]
            );
            
            // Create API token
            $token = $user->createToken('api-demo-token');
            $this->apiToken = $token->plainTextToken;
            
            $this->printResult("Authentication ready", "Token created for user: {$user->email}");
            return true;
            
        } catch (\Exception $e) {
            $this->printError("Authentication setup failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function demonstrateComplaintEndpoints(): void
    {
        $this->printStep("📋 COMPLAINT ENDPOINTS", "Testing complaint management APIs");
        
        // GET /api/complaints - List complaints with filters
        $this->demoComplaintsList();
        
        // GET /api/complaints/summary - Aggregated statistics
        $this->demoComplaintsSummary();
        
        // GET /api/complaints/{id} - Individual complaint details
        $this->demoComplaintShow();
    }
    
    private function demoComplaintsList(): void
    {
        echo "🔍 GET /api/complaints - List complaints with advanced filtering\n";
        
        $filters = [
            'borough' => 'MANHATTAN',
            'status' => 'Open',
            'per_page' => 5,
            'sort_by' => 'created_date',
            'sort_order' => 'desc'
        ];
        
        $response = $this->makeApiRequest('GET', '/complaints', $filters);
        
        if ($response['success']) {
            $data = $response['data'];
            echo "   ✅ Found {$data['total']} complaints (showing {$data['per_page']} per page)\n";
            echo "   📄 Pages: {$data['current_page']}/{$data['last_page']}\n";
            
            if (!empty($data['data'])) {
                $complaint = $data['data'][0];
                echo "   📝 Sample: #{$complaint['complaint_number']} - {$complaint['complaint_type']}\n";
            }
        }
        
        echo "\n";
    }
    
    private function demoComplaintsSummary(): void
    {
        echo "📊 GET /api/complaints/summary - Aggregated statistics\n";
        
        $response = $this->makeApiRequest('GET', '/complaints/summary');
        
        if ($response['success']) {
            $summary = $response['data'];
            echo "   📈 Total Complaints: {$summary['total_complaints']}\n";
            echo "   🏗️  By Status: " . json_encode($summary['by_status']) . "\n";
            echo "   🗺️  By Borough: " . json_encode($summary['by_borough']) . "\n";
            
            if (isset($summary['risk_distribution'])) {
                echo "   ⚠️  Risk Distribution: " . json_encode($summary['risk_distribution']) . "\n";
            }
        }
        
        echo "\n";
    }
    
    private function demoComplaintShow(): void
    {
        echo "🔍 GET /api/complaints/{id} - Individual complaint details\n";
        
        // Get a sample complaint ID
        $listResponse = $this->makeApiRequest('GET', '/complaints', ['per_page' => 1]);
        
        if ($listResponse['success'] && !empty($listResponse['data']['data'])) {
            $sampleId = $listResponse['data']['data'][0]['id'];
            
            $response = $this->makeApiRequest('GET', "/complaints/{$sampleId}");
            
            if ($response['success']) {
                $complaint = $response['data'];
                echo "   ✅ Complaint Details Retrieved\n";
                echo "   📋 Number: {$complaint['complaint_number']}\n";
                echo "   🏷️  Type: {$complaint['complaint_type']}\n";
                echo "   📍 Location: {$complaint['borough']}\n";
                
                if (isset($complaint['analysis'])) {
                    echo "   🤖 AI Analysis: Risk Score {$complaint['analysis']['risk_score']}\n";
                }
            }
        } else {
            echo "   ⚠️  No complaints available for demo\n";
        }
        
        echo "\n";
    }
    
    private function demonstrateSearchEndpoints(): void
    {
        $this->printStep("🔍 SEARCH ENDPOINTS", "Testing semantic and vector search APIs");
        
        // POST /api/search/semantic - Hybrid semantic search
        $this->demoSemanticSearch();
        
        // POST /api/search/similar - Pure vector similarity
        $this->demoSimilaritySearch();
        
        // GET /api/search/stats - Vector store statistics
        $this->demoSearchStats();
    }
    
    private function demoSemanticSearch(): void
    {
        echo "🧠 POST /api/search/semantic - Hybrid semantic search\n";
        
        $searchData = [
            'query' => 'heating problems in apartment building',
            'filters' => ['borough' => 'BROOKLYN'],
            'options' => [
                'vector_weight' => 0.7,
                'metadata_weight' => 0.3,
                'similarity_threshold' => 0.6
            ]
        ];
        
        $response = $this->makeApiRequest('POST', '/search/semantic', $searchData);
        
        if ($response['success']) {
            $results = $response['data'];
            $resultCount = count($results['results'] ?? []);
            echo "   ✅ Found {$resultCount} semantically similar complaints\n";
            
            if (isset($results['search_metadata'])) {
                $metadata = $results['search_metadata'];
                echo "   ⚡ Search Time: {$metadata['total_time_ms']}ms\n";
                echo "   🔧 Method: {$metadata['method']}\n";
            }
            
            if ($resultCount > 0) {
                $topResult = $results['results'][0];
                $score = number_format($topResult['combined_score'], 3);
                echo "   🎯 Top Result: Score {$score} - {$topResult['complaint']['complaint_type']}\n";
            }
        }
        
        echo "\n";
    }
    
    private function demoSimilaritySearch(): void
    {
        echo "📐 POST /api/search/similar - Pure vector similarity search\n";
        
        $searchData = [
            'query' => 'water leak ceiling damage',
            'limit' => 5
        ];
        
        $response = $this->makeApiRequest('POST', '/search/similar', $searchData);
        
        if ($response['success']) {
            $results = $response['data'];
            $resultCount = count($results['results'] ?? []);
            echo "   ✅ Found {$resultCount} similar complaints by vector distance\n";
            
            if ($resultCount > 0) {
                $avgSimilarity = array_sum(array_column($results['results'], 'similarity_score')) / $resultCount;
                echo "   📊 Average Similarity: " . number_format($avgSimilarity, 3) . "\n";
            }
        }
        
        echo "\n";
    }
    
    private function demoSearchStats(): void
    {
        echo "📈 GET /api/search/stats - Vector store statistics\n";
        
        $response = $this->makeApiRequest('GET', '/search/stats');
        
        if ($response['success']) {
            $stats = $response['data'];
            echo "   📚 Total Documents: {$stats['total_documents']}\n";
            echo "   🧮 Vector Dimensions: {$stats['vector_dimension']}\n";
            echo "   💾 Index Type: {$stats['index_type']}\n";
            
            if (isset($stats['last_updated'])) {
                echo "   ⏰ Last Updated: {$stats['last_updated']}\n";
            }
        }
        
        echo "\n";
    }
    
    private function demonstrateActionEndpoints(): void
    {
        $this->printStep("⚡ ACTION ENDPOINTS", "Testing complaint action and escalation APIs");
        
        // POST /api/actions/escalate - Batch escalation
        $this->demoEscalateActions();
        
        // POST /api/user-questions - Natural language queries
        $this->demoUserQuestions();
    }
    
    private function demoEscalateActions(): void
    {
        echo "🚨 POST /api/actions/escalate - Batch complaint escalation\n";
        
        // First get some complaints to escalate
        $listResponse = $this->makeApiRequest('GET', '/complaints', ['per_page' => 2]);
        
        if ($listResponse['success'] && !empty($listResponse['data']['data'])) {
            $complaintIds = array_column($listResponse['data']['data'], 'id');
            
            $escalationData = [
                'complaint_ids' => $complaintIds,
                'reason' => 'Demo escalation for testing',
                'priority' => 'high'
            ];
            
            $response = $this->makeApiRequest('POST', '/actions/escalate', $escalationData);
            
            if ($response['success']) {
                $result = $response['data'];
                echo "   ✅ Escalated {$result['escalated_count']} complaints\n";
                echo "   📋 Actions Created: {$result['actions_created']}\n";
                
                if ($result['notifications_sent'] > 0) {
                    echo "   📢 Notifications Sent: {$result['notifications_sent']}\n";
                }
            }
        } else {
            echo "   ⚠️  No complaints available for escalation demo\n";
        }
        
        echo "\n";
    }
    
    private function demoUserQuestions(): void
    {
        echo "❓ POST /api/user-questions - Natural language query logging\n";
        
        $questionData = [
            'question' => 'How many noise complaints were filed in Brooklyn last month?',
            'context' => 'API demo testing',
            'user_session' => 'demo-session-' . uniqid()
        ];
        
        $response = $this->makeApiRequest('POST', '/user-questions', $questionData);
        
        if ($response['success']) {
            $result = $response['data'];
            echo "   ✅ Question logged with ID: {$result['id']}\n";
            echo "   📝 Question: {$result['question']}\n";
            echo "   ⏰ Timestamp: {$result['created_at']}\n";
        }
        
        echo "\n";
    }
    
    private function displayApiMetrics(): void
    {
        $this->printStep("📊 API PERFORMANCE METRICS", "Response times and success rates");
        
        if (empty($this->apiMetrics)) {
            echo "No metrics collected during demo.\n";
            return;
        }
        
        $totalRequests = count($this->apiMetrics);
        $successfulRequests = count(array_filter($this->apiMetrics, fn($m) => $m['success']));
        $avgResponseTime = array_sum(array_column($this->apiMetrics, 'response_time')) / $totalRequests;
        
        echo "📈 Request Statistics:\n";
        echo "   • Total Requests: {$totalRequests}\n";
        echo "   • Success Rate: " . round(($successfulRequests / $totalRequests) * 100, 1) . "%\n";
        echo "   • Average Response Time: " . round($avgResponseTime, 2) . "ms\n";
        
        echo "\n⚡ Endpoint Performance:\n";
        $endpointStats = [];
        
        foreach ($this->apiMetrics as $metric) {
            $endpoint = $metric['method'] . ' ' . $metric['endpoint'];
            if (!isset($endpointStats[$endpoint])) {
                $endpointStats[$endpoint] = ['times' => [], 'success' => 0, 'total' => 0];
            }
            
            $endpointStats[$endpoint]['times'][] = $metric['response_time'];
            $endpointStats[$endpoint]['total']++;
            if ($metric['success']) {
                $endpointStats[$endpoint]['success']++;
            }
        }
        
        foreach ($endpointStats as $endpoint => $stats) {
            $avgTime = round(array_sum($stats['times']) / count($stats['times']), 2);
            $successRate = round(($stats['success'] / $stats['total']) * 100, 1);
            echo "   • {$endpoint}: {$avgTime}ms (success: {$successRate}%)\n";
        }
        
        echo "\n";
    }
    
    private function makeApiRequest(string $method, string $endpoint, array $data = []): array
    {
        $startTime = microtime(true);
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        
        try {
            $response = Http::withHeaders($headers)
                ->timeout(30);
            
            if ($method === 'GET') {
                $response = $response->get($this->baseUrl . $endpoint, $data);
            } else {
                $response = $response->post($this->baseUrl . $endpoint, $data);
            }
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $success = $response->successful();
            
            // Track metrics
            $this->apiMetrics[] = [
                'method' => $method,
                'endpoint' => $endpoint,
                'response_time' => $responseTime,
                'status_code' => $response->status(),
                'success' => $success
            ];
            
            if ($success) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'response_time' => $responseTime
                ];
            } else {
                $this->printError("API Error: {$response->status()} - " . $response->body());
                return ['success' => false, 'error' => $response->body()];
            }
            
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->apiMetrics[] = [
                'method' => $method,
                'endpoint' => $endpoint,
                'response_time' => $responseTime,
                'status_code' => 0,
                'success' => false
            ];
            
            $this->printError("Request failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function printHeader(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "🚀 LaraCity API Showcase Demo\n";
        echo "Demonstrating all REST API endpoints with practical examples\n";
        echo str_repeat("=", 80) . "\n\n";
    }
    
    private function printStep(string $title, string $description): void
    {
        echo "\n{$title}\n";
        echo str_repeat("-", strlen($title)) . "\n";
        echo "{$description}\n\n";
    }
    
    private function printResult(string $action, string $result): void
    {
        echo "✅ {$action}: {$result}\n";
    }
    
    private function printError(string $message): void
    {
        echo "❌ ERROR: {$message}\n";
    }
    
    private function printFooter(): void
    {
        echo str_repeat("=", 80) . "\n";
        echo "Demo completed! All API endpoints demonstrated.\n";
        echo "🔗 Postman Collection: Available in docs/postman/\n";
        echo "📚 Learn more: See Tutorial-Details.md for implementation details\n";
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Main execution
echo "Starting LaraCity API demo...\n";
echo "Make sure the Laravel server is running: php artisan serve\n\n";

$demo = new ApiShowcaseDemo();
$demo->runDemo();