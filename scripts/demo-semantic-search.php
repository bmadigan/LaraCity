#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Demo Script: Semantic Search Capabilities
 * 
 * Educational Focus:
 * - Vector similarity search with pgvector
 * - Natural language query processing
 * - Hybrid search (semantic + metadata filtering)
 * - Real-world search scenarios and examples
 * 
 * This script demonstrates the semantic search capabilities
 * using real NYC 311 complaint data and vector embeddings.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\HybridSearchService;
use App\Services\VectorEmbeddingService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class SemanticSearchDemo
{
    private HybridSearchService $searchService;
    private VectorEmbeddingService $embeddingService;
    
    private array $searchScenarios = [
        [
            'title' => 'Natural Language Query',
            'query' => 'apartment heating not working in winter',
            'description' => 'Find heating-related complaints using natural language',
            'filters' => ['borough' => 'BROOKLYN'],
            'options' => ['similarity_threshold' => 0.7]
        ],
        [
            'title' => 'Noise Complaints Discovery',
            'query' => 'loud music late at night disturbing neighbors',
            'description' => 'Discover noise complaints with semantic understanding',
            'filters' => [],
            'options' => ['vector_weight' => 0.8, 'metadata_weight' => 0.2]
        ],
        [
            'title' => 'Infrastructure Issues',
            'query' => 'water pressure problems building plumbing issues',
            'description' => 'Find infrastructure and plumbing related complaints',
            'filters' => ['borough' => 'MANHATTAN'],
            'options' => ['similarity_threshold' => 0.6, 'limit' => 10]
        ],
        [
            'title' => 'Safety Concerns',
            'query' => 'dangerous conditions safety hazards building',
            'description' => 'Identify safety-related complaints across all boroughs',
            'filters' => [],
            'options' => ['similarity_threshold' => 0.75]
        ]
    ];

    public function __construct()
    {
        $this->searchService = app(HybridSearchService::class);
        $this->embeddingService = app(VectorEmbeddingService::class);
    }

    public function runDemo(string $scenario = 'all'): void
    {
        $this->printHeader();
        
        // Check system readiness
        if (!$this->checkSystemReadiness()) {
            return;
        }
        
        if ($scenario === 'all') {
            $this->runAllScenarios();
        } else {
            $this->runSingleScenario($scenario);
        }
        
        $this->printFooter();
    }
    
    private function checkSystemReadiness(): bool
    {
        $this->printStep("🔍 SYSTEM READINESS CHECK", "Verifying search components");
        
        // Check if embeddings exist
        $embeddingCount = DB::table('document_embeddings')->count();
        if ($embeddingCount === 0) {
            $this->printError("No vector embeddings found. Run: php artisan lacity:generate-embeddings --type=all");
            return false;
        }
        
        $this->printResult("Vector embeddings found", "{$embeddingCount} documents indexed");
        
        // Check if complaints exist
        $complaintCount = DB::table('complaints')->count();
        if ($complaintCount === 0) {
            $this->printError("No complaints found. Import data with: php artisan lacity:import-csv");
            return false;
        }
        
        $this->printResult("Complaint data ready", "{$complaintCount} complaints available");
        
        return true;
    }
    
    private function runAllScenarios(): void
    {
        foreach ($this->searchScenarios as $index => $scenario) {
            $this->runSearchScenario($scenario, $index + 1);
            
            if ($index < count($this->searchScenarios) - 1) {
                echo "\n" . str_repeat("-", 80) . "\n";
            }
        }
    }
    
    private function runSingleScenario(string $scenarioName): void
    {
        $scenarioIndex = (int)$scenarioName - 1;
        
        if (!isset($this->searchScenarios[$scenarioIndex])) {
            $this->printError("Invalid scenario number. Available: 1-" . count($this->searchScenarios));
            return;
        }
        
        $this->runSearchScenario($this->searchScenarios[$scenarioIndex], $scenarioIndex + 1);
    }
    
    private function runSearchScenario(array $scenario, int $number): void
    {
        $this->printStep("📊 SCENARIO {$number}: {$scenario['title']}", $scenario['description']);
        
        echo "Query: \"{$scenario['query']}\"\n";
        if (!empty($scenario['filters'])) {
            echo "Filters: " . json_encode($scenario['filters']) . "\n";
        }
        echo "\n";
        
        try {
            // Perform semantic search
            $startTime = microtime(true);
            $results = $this->searchService->search(
                $scenario['query'],
                $scenario['filters'],
                $scenario['options']
            );
            $searchTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->printResult("Search completed", "{$searchTime}ms");
            $this->displaySearchResults($results);
            
            // Show search analytics
            $this->displaySearchAnalytics($results, $scenario['query']);
            
        } catch (\Exception $e) {
            $this->printError("Search failed: " . $e->getMessage());
        }
    }
    
    private function displaySearchResults(array $results): void
    {
        $resultCount = count($results['results'] ?? []);
        
        echo "\n🎯 Search Results ({$resultCount} found):\n";
        echo str_repeat("─", 50) . "\n";
        
        if ($resultCount === 0) {
            echo "No matching complaints found.\n";
            return;
        }
        
        foreach (array_slice($results['results'], 0, 5) as $index => $result) {
            $complaint = $result['complaint'];
            $score = number_format($result['combined_score'], 3);
            
            echo ($index + 1) . ". [Score: {$score}] Complaint #{$complaint['complaint_number']}\n";
            echo "   Type: {$complaint['complaint_type']}\n";
            echo "   Location: {$complaint['borough']}\n";
            echo "   Content: " . substr($result['content'], 0, 120) . "...\n";
            
            if (isset($result['similarity_score'])) {
                echo "   Vector Similarity: " . number_format($result['similarity_score'], 3) . "\n";
            }
            
            echo "\n";
        }
        
        if ($resultCount > 5) {
            echo "... and " . ($resultCount - 5) . " more results\n\n";
        }
    }
    
    private function displaySearchAnalytics(array $results, string $query): void
    {
        echo "📈 Search Analytics:\n";
        
        // Performance metrics
        if (isset($results['search_metadata'])) {
            $metadata = $results['search_metadata'];
            echo "   • Search Method: " . ($metadata['method'] ?? 'hybrid') . "\n";
            echo "   • Processing Time: " . ($metadata['total_time_ms'] ?? 'N/A') . "ms\n";
            
            if (isset($metadata['vector_search_time_ms'])) {
                echo "   • Vector Search Time: {$metadata['vector_search_time_ms']}ms\n";
            }
        }
        
        // Result distribution
        $results_data = $results['results'] ?? [];
        if (!empty($results_data)) {
            $boroughs = array_count_values(array_column(array_column($results_data, 'complaint'), 'borough'));
            echo "   • Borough Distribution: " . json_encode($boroughs) . "\n";
            
            $avgScore = array_sum(array_column($results_data, 'combined_score')) / count($results_data);
            echo "   • Average Relevance: " . number_format($avgScore, 3) . "\n";
        }
        
        // Query analysis
        $queryWords = str_word_count($query);
        echo "   • Query Complexity: {$queryWords} words\n";
        
        echo "\n";
    }
    
    public function demoInteractiveSearch(): void
    {
        $this->printStep("🎮 INTERACTIVE SEARCH MODE", "Enter your own search queries");
        
        echo "Type your search queries below (or 'quit' to exit):\n\n";
        
        while (true) {
            echo "Search> ";
            $handle = fopen("php://stdin", "r");
            $query = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($query) === 'quit' || strtolower($query) === 'exit') {
                echo "Goodbye!\n";
                break;
            }
            
            if (empty($query)) {
                continue;
            }
            
            try {
                $results = $this->searchService->search($query);
                $this->displaySearchResults($results);
                
            } catch (\Exception $e) {
                $this->printError("Search failed: " . $e->getMessage());
            }
            
            echo "\n";
        }
    }
    
    private function printHeader(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "🔍 LaraCity Semantic Search Demo\n";
        echo "Demonstrating vector similarity search with natural language queries\n";
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
        echo "Demo completed! Semantic search allows natural language queries\n";
        echo "🔧 Try: php " . basename(__FILE__) . " interactive\n";
        echo "📚 Learn more: See Tutorial-Details.md for implementation details\n";
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Main execution
$scenario = $argv[1] ?? 'all';

if ($scenario === 'interactive') {
    $demo = new SemanticSearchDemo();
    $demo->demoInteractiveSearch();
} else {
    if ($argc >= 2 && !in_array($scenario, ['all', '1', '2', '3', '4'])) {
        echo "Usage: php demo-semantic-search.php [scenario|interactive]\n";
        echo "Scenarios: all, 1, 2, 3, 4, interactive\n";
        exit(1);
    }
    
    $demo = new SemanticSearchDemo();
    $demo->runDemo($scenario);
}