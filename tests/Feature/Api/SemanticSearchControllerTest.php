<?php

use App\Models\User;
use App\Models\Complaint;
use App\Models\DocumentEmbedding;
use App\Services\HybridSearchService;
use App\Services\VectorEmbeddingService;
use App\Services\PythonAiBridge;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('semantic search endpoint performs hybrid search', function () {
    // Mock the search service
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->with('water leak', [], Mockery::type('array'))
        ->andReturn([
            'results' => [
                [
                    'complaint' => [
                        'id' => 1,
                        'complaint_number' => 'TEST123',
                        'type' => 'Water System',
                        'description' => 'Broken pipe',
                        'borough' => 'MANHATTAN',
                        'status' => 'Open',
                    ],
                    'similarity' => 0.85,
                ]
            ],
            'metadata' => [
                'query' => 'water leak',
                'total_results' => 1,
                'search_duration_ms' => 150.5,
            ]
        ]);
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'water leak',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'results' => [
                    '*' => [
                        'complaint',
                        'similarity',
                    ]
                ],
                'metadata' => [
                    'query',
                    'total_results',
                    'search_duration_ms',
                ]
            ]
        ]);
    
    expect($response->json('data.results'))->toHaveCount(1)
        ->and($response->json('data.metadata.query'))->toBe('water leak');
});

test('semantic search endpoint validates required query parameter', function () {
    $response = $this->postJson('/api/search/semantic', []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['query']);
});

test('semantic search endpoint validates query length', function () {
    $response = $this->postJson('/api/search/semantic', [
        'query' => str_repeat('a', 1001), // Too long
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['query']);
});

test('semantic search endpoint accepts filters', function () {
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->with('noise', [
            'borough' => 'MANHATTAN',
            'complaint_type' => 'Noise',
            'risk_level' => 'high',
        ], Mockery::type('array'))
        ->andReturn([
            'results' => [],
            'metadata' => [],
        ]);
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'noise',
        'filters' => [
            'borough' => 'MANHATTAN',
            'complaint_type' => 'Noise',
            'risk_level' => 'high',
        ],
    ]);
    
    $response->assertStatus(200);
});

test('semantic search endpoint accepts search options', function () {
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->with('test', [], [
            'limit' => 5,
            'similarity_threshold' => 0.8,
            'vector_weight' => 0.6,
        ])
        ->andReturn([
            'results' => [],
            'metadata' => [],
        ]);
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'test',
        'options' => [
            'limit' => 5,
            'similarity_threshold' => 0.8,
            'vector_weight' => 0.6,
        ],
    ]);
    
    $response->assertStatus(200);
});

test('similar endpoint finds similar complaints', function () {
    $complaint = Complaint::factory()->create();
    
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    $embeddingService->shouldReceive('searchSimilar')
        ->once()
        ->with(Mockery::type('string'), 'complaint', 0.7, 10)
        ->andReturn([
            [
                'embedding_id' => 1,
                'similarity' => 0.9,
                'document' => $complaint,
                'content' => 'Similar complaint content',
            ]
        ]);
    
    app()->instance(VectorEmbeddingService::class, $embeddingService);
    
    $response = $this->postJson('/api/search/similar', [
        'complaint_id' => $complaint->id,
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'embedding_id',
                    'similarity',
                    'document',
                    'content',
                ]
            ]
        ]);
});

test('similar endpoint validates complaint exists', function () {
    $response = $this->postJson('/api/search/similar', [
        'complaint_id' => 999,
    ]);
    
    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Complaint not found'
        ]);
});

test('similar endpoint accepts threshold and limit parameters', function () {
    $complaint = Complaint::factory()->create();
    
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    $embeddingService->shouldReceive('searchSimilar')
        ->once()
        ->with(Mockery::type('string'), 'complaint', 0.8, 5)
        ->andReturn([]);
    
    app()->instance(VectorEmbeddingService::class, $embeddingService);
    
    $response = $this->postJson('/api/search/similar', [
        'complaint_id' => $complaint->id,
        'threshold' => 0.8,
        'limit' => 5,
    ]);
    
    $response->assertStatus(200);
});

test('embed endpoint generates embeddings for text', function () {
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $pythonBridge->shouldReceive('generateEmbedding')
        ->once()
        ->with('test complaint text')
        ->andReturn([
            'embedding' => [0.1, 0.2, 0.3],
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);
    
    app()->instance(PythonAiBridge::class, $pythonBridge);
    
    $response = $this->postJson('/api/search/embed', [
        'text' => 'test complaint text',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'embedding',
                'model',
                'dimension',
            ]
        ]);
    
    expect($response->json('data.embedding'))->toBe([0.1, 0.2, 0.3])
        ->and($response->json('data.model'))->toBe('text-embedding-3-small');
});

test('embed endpoint validates required text parameter', function () {
    $response = $this->postJson('/api/search/embed', []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['text']);
});

test('embed endpoint handles embedding generation failure', function () {
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $pythonBridge->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(null);
    
    app()->instance(PythonAiBridge::class, $pythonBridge);
    
    $response = $this->postJson('/api/search/embed', [
        'text' => 'test text',
    ]);
    
    $response->assertStatus(500)
        ->assertJson([
            'message' => 'Failed to generate embedding'
        ]);
});

test('stats endpoint returns search statistics', function () {
    // Create test data
    Complaint::factory()->count(10)->create();
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'test'),
        'content' => 'test',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    $response = $this->getJson('/api/search/stats');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_complaints',
                'embedded_complaints',
                'embedding_coverage_percentage',
                'models_used',
                'average_embedding_dimension',
            ]
        ]);
    
    expect($response->json('data.total_complaints'))->toBe(10)
        ->and($response->json('data.embedded_complaints'))->toBe(1)
        ->and($response->json('data.embedding_coverage_percentage'))->toBe(10.0);
});

test('test endpoint checks system health', function () {
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $pythonBridge->shouldReceive('testConnection')
        ->once()
        ->andReturn([
            'status' => 'healthy',
            'python_version' => '3.9.0',
            'dependencies' => ['openai', 'langchain'],
        ]);
    
    app()->instance(PythonAiBridge::class, $pythonBridge);
    
    $response = $this->getJson('/api/search/test');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'search_system' => [
                    'status',
                    'components',
                ],
                'python_bridge' => [
                    'status',
                    'python_version',
                    'dependencies',
                ]
            ]
        ]);
    
    expect($response->json('data.python_bridge.status'))->toBe('healthy');
});

test('test endpoint handles python bridge failure', function () {
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $pythonBridge->shouldReceive('testConnection')
        ->once()
        ->andReturn([
            'status' => 'error',
            'message' => 'Connection failed',
        ]);
    
    app()->instance(PythonAiBridge::class, $pythonBridge);
    
    $response = $this->getJson('/api/search/test');
    
    $response->assertStatus(200);
    
    expect($response->json('data.python_bridge.status'))->toBe('error');
});

test('search endpoints require authentication', function () {
    // Remove authentication
    $this->withoutMiddleware();
    
    $endpoints = [
        ['POST', '/api/search/semantic', ['query' => 'test']],
        ['POST', '/api/search/similar', ['complaint_id' => 1]],
        ['POST', '/api/search/embed', ['text' => 'test']],
        ['GET', '/api/search/stats'],
        ['GET', '/api/search/test'],
    ];
    
    foreach ($endpoints as [$method, $url, $data]) {
        $response = $method === 'GET' 
            ? $this->getJson($url)
            : $this->postJson($url, $data ?? []);
        
        $response->assertStatus(401);
    }
});

test('semantic search handles large result sets efficiently', function () {
    // Create many test complaints
    $complaints = Complaint::factory()->count(50)->create();
    
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->andReturn([
            'results' => $complaints->take(20)->map(function ($complaint) {
                return [
                    'complaint' => $complaint->toArray(),
                    'similarity' => 0.8,
                ];
            })->toArray(),
            'metadata' => [
                'query' => 'test',
                'total_results' => 20,
                'search_duration_ms' => 250.0,
            ]
        ]);
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'test',
        'options' => ['limit' => 20],
    ]);
    
    $response->assertStatus(200);
    
    expect($response->json('data.results'))->toHaveCount(20);
});

test('semantic search validates filter values', function () {
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'test',
        'filters' => [
            'risk_level' => 'invalid_level', // Should only accept: high, medium, low
        ],
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['filters.risk_level']);
});

test('semantic search validates option values', function () {
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'test',
        'options' => [
            'limit' => 1000, // Too high
            'similarity_threshold' => 1.5, // > 1.0
            'vector_weight' => -0.1, // < 0.0
        ],
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['options.limit', 'options.similarity_threshold', 'options.vector_weight']);
});

test('similar endpoint handles complaint without embeddings', function () {
    $complaint = Complaint::factory()->create();
    
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    $embeddingService->shouldReceive('searchSimilar')
        ->once()
        ->andReturn([]); // No similar complaints found
    
    app()->instance(VectorEmbeddingService::class, $embeddingService);
    
    $response = $this->postJson('/api/search/similar', [
        'complaint_id' => $complaint->id,
    ]);
    
    $response->assertStatus(200)
        ->assertJson([
            'data' => []
        ]);
});

test('search endpoints handle service exceptions gracefully', function () {
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->andThrow(new Exception('Search service unavailable'));
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $response = $this->postJson('/api/search/semantic', [
        'query' => 'test',
    ]);
    
    $response->assertStatus(500)
        ->assertJson([
            'message' => 'Search failed'
        ]);
});

test('embed endpoint validates text length', function () {
    $response = $this->postJson('/api/search/embed', [
        'text' => str_repeat('a', 10001), // Too long
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['text']);
});

test('stats endpoint includes model distribution', function () {
    // Create embeddings with different models
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'test1'),
        'content' => 'test1',
        'metadata' => [],
        'embedding_model' => 'text-embedding-ada-002',
        'embedding_dimension' => 1536,
        'embedding' => '[0.1]',
    ]);
    
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 2,
        'document_hash' => hash('sha256', 'test2'),
        'content' => 'test2',
        'metadata' => [],
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimension' => 1536,
        'embedding' => '[0.2]',
    ]);
    
    $response = $this->getJson('/api/search/stats');
    
    $response->assertStatus(200);
    
    $modelsUsed = $response->json('data.models_used');
    expect($modelsUsed)->toContain('text-embedding-ada-002')
        ->and($modelsUsed)->toContain('text-embedding-3-small');
});