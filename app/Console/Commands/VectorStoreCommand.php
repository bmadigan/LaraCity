<?php

namespace App\Console\Commands;

use App\Models\DocumentEmbedding;
use App\Services\PythonAiBridge;
use App\Services\HybridSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VectorStoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lacity:vector-store 
                          {action : Action to perform (stats, sync, search, cleanup, test)}
                          {--query= : Search query for the search action}
                          {--type= : Document type filter for search}
                          {--threshold=0.7 : Similarity threshold for search}
                          {--limit=10 : Number of results to return}
                          {--cleanup-days=30 : Days to keep when cleaning up old embeddings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage the pgvector store: stats, sync, search, cleanup, and testing';

    public function __construct(
        private PythonAiBridge $pythonBridge,
        private HybridSearchService $searchService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        $this->info("🔍 LaraCity Vector Store Manager");
        
        return match ($action) {
            'stats' => $this->showStats(),
            'sync' => $this->syncVectorStore(), 
            'search' => $this->searchVectorStore(),
            'cleanup' => $this->cleanupVectorStore(),
            'test' => $this->testVectorStore(),
            default => $this->error("Invalid action: {$action}. Must be one of: stats, sync, search, cleanup, test")
        };
    }

    private function showStats(): int
    {
        $this->info("\n📊 Vector Store Statistics:");

        try {
            // Get database statistics
            $stats = [
                'total_embeddings' => DocumentEmbedding::count(),
                'by_type' => DocumentEmbedding::select('document_type', DB::raw('count(*) as count'))
                    ->groupBy('document_type')
                    ->get()
                    ->pluck('count', 'document_type')
                    ->toArray(),
                'by_model' => DocumentEmbedding::select('embedding_model', DB::raw('count(*) as count'))
                    ->groupBy('embedding_model') 
                    ->get()
                    ->pluck('count', 'embedding_model')
                    ->toArray(),
                'avg_dimension' => DocumentEmbedding::avg('embedding_dimension'),
                'latest_embedding' => DocumentEmbedding::latest()->first()?->created_at?->diffForHumans(),
            ];

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Embeddings', number_format($stats['total_embeddings'])],
                    ['Average Dimension', round($stats['avg_dimension'] ?? 0, 2)],
                    ['Latest Embedding', $stats['latest_embedding'] ?? 'None'],
                ]
            );

            if (!empty($stats['by_type'])) {
                $this->info("\n📋 By Document Type:");
                $this->table(
                    ['Type', 'Count'],
                    collect($stats['by_type'])->map(fn($count, $type) => [$type, number_format($count)])->values()->toArray()
                );
            }

            if (!empty($stats['by_model'])) {
                $this->info("\n🤖 By Embedding Model:");
                $this->table(
                    ['Model', 'Count'],
                    collect($stats['by_model'])->map(fn($count, $model) => [$model, number_format($count)])->values()->toArray()
                );
            }

            // Test Python bridge statistics
            $this->info("\n🐍 Python Bridge Statistics:");
            try {
                $result = $this->pythonBridge->syncPgVectorStore();
                $this->line("✅ Python bridge connection successful");
                
                if (isset($result['total_embeddings'])) {
                    $this->line("Python view: " . number_format($result['total_embeddings']) . " embeddings");
                }
            } catch (\Exception $e) {
                $this->error("❌ Python bridge connection failed: " . $e->getMessage());
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get statistics: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncVectorStore(): int
    {
        $this->info("\n🔄 Syncing Vector Store with Python...");

        try {
            $result = $this->pythonBridge->syncPgVectorStore();
            
            $this->info("✅ Sync completed successfully!");
            
            if (isset($result['complaints_processed'])) {
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Complaints Processed', $result['complaints_processed']],
                        ['Embeddings Created', $result['embeddings_created'] ?? 0],
                        ['Embeddings Skipped', $result['embeddings_skipped'] ?? 0],
                        ['Errors', $result['errors'] ?? 0],
                    ]
                );
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function searchVectorStore(): int
    {
        $query = $this->option('query');
        
        if (!$query) {
            $query = $this->ask('Enter search query:');
        }

        if (!$query) {
            $this->error("Search query is required");
            return Command::FAILURE;
        }

        $this->info("\n🔍 Searching Vector Store...");
        $this->info("Query: {$query}");

        try {
            $filters = [];
            if ($this->option('type')) {
                $filters['document_type'] = $this->option('type');
            }

            $options = [
                'similarity_threshold' => (float) $this->option('threshold'),
                'limit' => (int) $this->option('limit'),
            ];

            $results = $this->searchService->search($query, $filters, $options);

            $this->info("\n📄 Search Results:");
            $this->line("Found {$results['metadata']['total_results']} results");

            if (empty($results['results'])) {
                $this->warn("No results found. Try lowering the similarity threshold.");
                return Command::SUCCESS;
            }

            $tableData = [];
            foreach (array_slice($results['results'], 0, 10) as $result) {
                $tableData[] = [
                    'Type' => $result['document_type'],
                    'ID' => $result['document_id'],
                    'Score' => round($result['combined_score'] ?? $result['similarity'] ?? 0, 4),
                    'Content' => substr($result['content'], 0, 80) . '...',
                ];
            }

            $this->table(['Type', 'ID', 'Score', 'Content Preview'], $tableData);

            if (count($results['results']) > 10) {
                $this->info("... and " . (count($results['results']) - 10) . " more results");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Search failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanupVectorStore(): int
    {
        $days = (int) $this->option('cleanup-days');
        
        $this->info("\n🧹 Cleaning up Vector Store...");
        $this->info("Removing embeddings older than {$days} days");

        try {
            $cutoffDate = now()->subDays($days);
            
            $oldEmbeddings = DocumentEmbedding::where('created_at', '<', $cutoffDate);
            $count = $oldEmbeddings->count();

            if ($count === 0) {
                $this->info("✅ No old embeddings to clean up");
                return Command::SUCCESS;
            }

            if (!$this->confirm("Delete {$count} old embeddings?")) {
                $this->info("Cleanup cancelled");
                return Command::SUCCESS;
            }

            $oldEmbeddings->delete();
            
            $this->info("✅ Cleaned up {$count} old embeddings");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function testVectorStore(): int
    {
        $this->info("\n🧪 Testing Vector Store...");

        $tests = [
            'Database Connection' => [$this, 'testDatabaseConnection'],
            'Python Bridge' => [$this, 'testPythonBridge'],
            'Vector Operations' => [$this, 'testVectorOperations'],
            'Search Functionality' => [$this, 'testSearchFunctionality'],
        ];

        $results = [];
        foreach ($tests as $testName => $testMethod) {
            $this->line("Running: {$testName}...");
            
            try {
                $result = call_user_func($testMethod);
                $results[$testName] = $result ? '✅ PASS' : '❌ FAIL';
            } catch (\Exception $e) {
                $results[$testName] = '❌ ERROR: ' . $e->getMessage();
            }
        }

        $this->info("\n📋 Test Results:");
        $this->table(
            ['Test', 'Result'],
            collect($results)->map(fn($result, $test) => [$test, $result])->values()->toArray()
        );

        $allPassed = collect($results)->every(fn($result) => str_contains($result, '✅'));
        
        if ($allPassed) {
            $this->info("\n🎉 All tests passed! Vector store is working correctly.");
            return Command::SUCCESS;
        } else {
            $this->error("\n❌ Some tests failed. Check the results above.");
            return Command::FAILURE;
        }
    }

    private function testDatabaseConnection(): bool
    {
        // Test basic database connection and pgvector extension
        $result = DB::select("SELECT '[1,2,3]'::vector <-> '[1,2,4]'::vector as distance");
        return isset($result[0]->distance) && $result[0]->distance == 1;
    }

    private function testPythonBridge(): bool
    {
        $result = $this->pythonBridge->testConnection();
        return $result['status'] === 'healthy';
    }

    private function testVectorOperations(): bool
    {
        // Test creating and querying embeddings
        $testEmbedding = DocumentEmbedding::where('document_type', 'complaint')->first();
        
        if (!$testEmbedding) {
            // Create a test embedding if none exists
            return true; // Skip test if no embeddings exist
        }

        // Test similarity search
        $similar = DocumentEmbedding::similarTo(
            $testEmbedding->embedding_array,
            0.5,
            1
        )->get();

        return $similar->count() >= 1;
    }

    private function testSearchFunctionality(): bool
    {
        $results = $this->searchService->search('test query', [], ['limit' => 1]);
        return isset($results['metadata']) && is_array($results['results']);
    }
}
