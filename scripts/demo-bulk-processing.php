#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Demo Script: Bulk Processing and Performance
 * 
 * Educational Focus:
 * - Enterprise-scale data processing
 * - Queue-based AI analysis pipeline
 * - Performance monitoring and metrics
 * - Batch processing optimization
 * 
 * This script demonstrates processing large volumes of complaints
 * through the AI analysis pipeline with performance monitoring.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Complaint;
use App\Jobs\AnalyzeComplaintJob;
use App\Services\CsvImportService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class BulkProcessingDemo
{
    private CsvImportService $importService;
    private array $performanceMetrics = [];
    
    public function __construct()
    {
        $this->importService = app(CsvImportService::class);
    }

    public function runDemo(int $batchSize = 100): void
    {
        $this->printHeader();
        
        $this->printStep("🚀 BULK PROCESSING DEMONSTRATION", "Processing {$batchSize} complaints through AI pipeline");
        
        // Step 1: Generate sample data
        $complaints = $this->generateSampleComplaints($batchSize);
        $this->printResult("Sample data generated", "{$batchSize} complaints created");
        
        // Step 2: Bulk AI analysis processing
        $this->processBulkAnalysis($complaints);
        
        // Step 3: Generate embeddings in batches
        $this->processBulkEmbeddings($complaints);
        
        // Step 4: Performance analysis
        $this->displayPerformanceAnalysis();
        
        // Step 5: Data quality metrics
        $this->displayDataQualityMetrics($complaints);
        
        $this->printFooter();
    }
    
    private function generateSampleComplaints(int $count): array
    {
        $this->startTimer('data_generation');
        
        $complaintTypes = [
            'Heating/Hot Water',
            'Noise',
            'Water Leak',
            'Electrical',
            'Pest Control',
            'Garbage/Recycling',
            'Street Condition',
            'Traffic/Parking',
            'Building Safety',
            'Air Quality'
        ];
        
        $boroughs = ['MANHATTAN', 'BROOKLYN', 'QUEENS', 'BRONX', 'STATEN ISLAND'];
        $descriptions = [
            'Heating system not working properly in apartment building',
            'Loud music and parties until late hours disturbing residents',
            'Water leaking from ceiling causing damage to apartment below',
            'Electrical outlets not working, potential fire hazard',
            'Cockroach infestation throughout multiple apartment units',
            'Garbage not collected for over a week, attracting pests',
            'Large pothole in street causing damage to vehicles',
            'Illegal parking blocking fire hydrant access',
            'Broken stairs in building pose safety risk to residents',
            'Strong chemical odor coming from nearby construction site'
        ];
        
        $complaints = [];
        
        for ($i = 0; $i < $count; $i++) {
            $complaintData = [
                'complaint_number' => 'BULK-' . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT),
                'complaint_type' => $complaintTypes[array_rand($complaintTypes)],
                'descriptor' => 'Bulk Demo Complaint',
                'complaint_description' => $descriptions[array_rand($descriptions)],
                'borough' => $boroughs[array_rand($boroughs)],
                'incident_zip' => '1' . str_pad((string)rand(1001, 1299), 4, '0', STR_PAD_LEFT),
                'location_type' => 'Residential Building',
                'status' => 'Open',
                'priority' => rand(1, 10) > 7 ? 'High' : 'Normal',
                'created_date' => now()->subDays(rand(0, 30))->format('Y-m-d H:i:s'),
                'agency' => 'HPD',
                'agency_name' => 'Housing Preservation and Development'
            ];
            
            $complaint = Complaint::create($complaintData);
            $complaints[] = $complaint;
            
            // Progress indicator
            if (($i + 1) % 25 === 0 || $i === $count - 1) {
                $progress = round((($i + 1) / $count) * 100);
                echo "\rGenerating complaints... {$progress}% ({$i + 1}/{$count})";
            }
        }
        
        echo "\n";
        $this->endTimer('data_generation');
        
        return $complaints;
    }
    
    private function processBulkAnalysis(array $complaints): void
    {
        $this->printStep("🤖 BULK AI ANALYSIS", "Processing through LangChain pipeline");
        
        $this->startTimer('ai_analysis');
        $batchSize = 10; // Process in smaller batches for demo
        $batches = array_chunk($complaints, $batchSize);
        $totalProcessed = 0;
        $successful = 0;
        $failed = 0;
        
        foreach ($batches as $batchIndex => $batch) {
            echo "Processing batch " . ($batchIndex + 1) . "/" . count($batches) . " ({$batchSize} complaints)...\n";
            
            foreach ($batch as $complaint) {
                try {
                    // Dispatch job synchronously for demo (in production use async queues)
                    AnalyzeComplaintJob::dispatchSync($complaint);
                    $successful++;
                } catch (\Exception $e) {
                    $failed++;
                    echo "  ❌ Failed to analyze complaint {$complaint->id}: " . $e->getMessage() . "\n";
                }
                $totalProcessed++;
            }
            
            // Simulate processing time and show progress
            sleep(1);
            $progress = round(($totalProcessed / count($complaints)) * 100);
            echo "  Progress: {$progress}% ({$totalProcessed}/" . count($complaints) . ")\n";
        }
        
        $this->endTimer('ai_analysis');
        $this->printResult("AI analysis completed", "Success: {$successful}, Failed: {$failed}");
    }
    
    private function processBulkEmbeddings(array $complaints): void
    {
        $this->printStep("🧮 BULK EMBEDDINGS GENERATION", "Creating vector embeddings for semantic search");
        
        $this->startTimer('embeddings');
        
        try {
            // Use Laravel command for embeddings generation
            $exitCode = Artisan::call('lacity:generate-embeddings', [
                '--type' => 'complaints',
                '--batch-size' => 25,
                '--limit' => count($complaints)
            ]);
            
            if ($exitCode === 0) {
                $this->printResult("Embeddings generated", "Vector database updated");
            } else {
                $this->printError("Embeddings generation failed");
            }
            
        } catch (\Exception $e) {
            $this->printError("Embeddings error: " . $e->getMessage());
        }
        
        $this->endTimer('embeddings');
    }
    
    private function displayPerformanceAnalysis(): void
    {
        $this->printStep("📊 PERFORMANCE ANALYSIS", "Processing metrics and throughput");
        
        echo "⏱️  Processing Times:\n";
        foreach ($this->performanceMetrics as $operation => $duration) {
            $rate = $operation === 'data_generation' ? '' : 
                   " (" . round(100 / $duration, 1) . " complaints/sec)";
            echo "   • " . ucfirst(str_replace('_', ' ', $operation)) . ": " . 
                 round($duration, 2) . "s{$rate}\n";
        }
        
        // Memory usage
        $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        echo "   • Peak Memory Usage: {$memoryUsage} MB\n";
        
        // Database metrics
        $analysisCount = DB::table('complaint_analyses')->count();
        $embeddingCount = DB::table('document_embeddings')->count();
        
        echo "\n📈 Processing Results:\n";
        echo "   • Total Analyses Created: {$analysisCount}\n";
        echo "   • Vector Embeddings: {$embeddingCount}\n";
        
        // Queue metrics (if available)
        try {
            $queueSize = Queue::size();
            echo "   • Queue Size: {$queueSize} pending jobs\n";
        } catch (\Exception $e) {
            echo "   • Queue: Not configured for monitoring\n";
        }
        
        echo "\n";
    }
    
    private function displayDataQualityMetrics(array $complaints): void
    {
        $this->printStep("🎯 DATA QUALITY METRICS", "Analysis success rates and quality scores");
        
        // Analysis completion rate
        $complaintIds = array_column($complaints, 'id');
        $completedAnalyses = DB::table('complaint_analyses')
            ->whereIn('complaint_id', $complaintIds)
            ->count();
        
        $completionRate = round(($completedAnalyses / count($complaints)) * 100, 1);
        echo "✅ Analysis Completion Rate: {$completionRate}%\n";
        
        // Risk score distribution
        $riskScores = DB::table('complaint_analyses')
            ->whereIn('complaint_id', $complaintIds)
            ->whereNotNull('risk_score')
            ->pluck('risk_score');
        
        if ($riskScores->count() > 0) {
            $avgRisk = round($riskScores->avg(), 3);
            $highRisk = $riskScores->filter(fn($score) => $score >= 0.7)->count();
            $mediumRisk = $riskScores->filter(fn($score) => $score >= 0.4 && $score < 0.7)->count();
            $lowRisk = $riskScores->filter(fn($score) => $score < 0.4)->count();
            
            echo "📊 Risk Score Distribution:\n";
            echo "   • Average Risk Score: {$avgRisk}\n";
            echo "   • High Risk (≥0.7): {$highRisk} complaints\n";
            echo "   • Medium Risk (0.4-0.7): {$mediumRisk} complaints\n";
            echo "   • Low Risk (<0.4): {$lowRisk} complaints\n";
        }
        
        // Category distribution
        $categories = DB::table('complaint_analyses')
            ->whereIn('complaint_id', $complaintIds)
            ->whereNotNull('category')
            ->groupBy('category')
            ->selectRaw('category, count(*) as count')
            ->get();
        
        if ($categories->count() > 0) {
            echo "\n🏷️  Category Distribution:\n";
            foreach ($categories as $category) {
                echo "   • {$category->category}: {$category->count} complaints\n";
            }
        }
        
        echo "\n";
    }
    
    private function startTimer(string $operation): void
    {
        $this->performanceMetrics[$operation . '_start'] = microtime(true);
    }
    
    private function endTimer(string $operation): void
    {
        $startKey = $operation . '_start';
        if (isset($this->performanceMetrics[$startKey])) {
            $this->performanceMetrics[$operation] = microtime(true) - $this->performanceMetrics[$startKey];
            unset($this->performanceMetrics[$startKey]);
        }
    }
    
    private function printHeader(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "⚡ LaraCity Bulk Processing Demo\n";
        echo "Demonstrating enterprise-scale AI analysis pipeline performance\n";
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
        echo "Demo completed! Bulk processing pipeline demonstrated.\n";
        echo "🔧 Production tip: Use Redis queues for better performance\n";
        echo "📚 Learn more: See Tutorial-Details.md for implementation details\n";
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Main execution
$batchSize = isset($argv[1]) ? (int)$argv[1] : 50;

if ($batchSize < 1 || $batchSize > 1000) {
    echo "Usage: php demo-bulk-processing.php [batch_size]\n";
    echo "Batch size must be between 1 and 1000\n";
    exit(1);
}

$demo = new BulkProcessingDemo();
$demo->runDemo($batchSize);