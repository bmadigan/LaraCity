<?php

use App\Services\HybridSearchService;
use App\Services\VectorEmbeddingService;
use App\Services\PythonAiBridge;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Models\DocumentEmbedding;

beforeEach(function () {
    $this->embeddingService = Mockery::mock(VectorEmbeddingService::class);
    $this->pythonBridge = Mockery::mock(PythonAiBridge::class);
    $this->service = new HybridSearchService($this->embeddingService, $this->pythonBridge);
});

test('search executes hybrid search with default options', function () {
    $query = 'water leak';
    
    // Mock embedding generation
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->with($query)
        ->once()
        ->andReturn([
            'embedding' => [0.1, 0.2, 0.3],
            'model' => 'test-model',
        ]);
    
    // Create test data
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Water System',
        'descriptor' => 'Water leak in basement',
        'borough' => 'MANHATTAN',
    ]);
    
    // Mock vector search results
    $vectorResults = collect([
        (object) [
            'id' => 1,
            'document_type' => 'complaint',
            'document_id' => $complaint->id,
            'content' => 'water leak content',
            'similarity' => 0.9,
            'metadata' => [],
        ]
    ]);
    
    DocumentEmbedding::shouldReceive('similarTo')
        ->once()
        ->andReturn($vectorResults);
    
    $result = $this->service->search($query);
    
    expect($result)->toBeArray()
        ->and($result['results'])->toBeArray()
        ->and($result['metadata'])->toBeArray()
        ->and($result['metadata']['query'])->toBe($query)
        ->and($result['metadata']['total_results'])->toBeInt();
});

test('search combines vector and metadata results with weighting', function () {
    $query = 'noise complaint';
    
    // Mock embedding generation
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->with($query)
        ->once()
        ->andReturn([
            'embedding' => [0.1, 0.2, 0.3],
            'model' => 'test-model',
        ]);
    
    // Create test complaints
    $complaint1 = Complaint::factory()->create([
        'complaint_type' => 'Noise - Residential',
        'descriptor' => 'Loud music',
        'borough' => 'MANHATTAN',
    ]);
    
    $complaint2 = Complaint::factory()->create([
        'complaint_type' => 'Other',
        'descriptor' => 'Noise from construction',
        'borough' => 'BROOKLYN',
    ]);
    
    // Mock vector results
    DocumentEmbedding::shouldReceive('similarTo')
        ->once()
        ->andReturn(collect([
            (object) [
                'id' => 1,
                'document_type' => 'complaint',
                'document_id' => $complaint1->id,
                'content' => 'noise content',
                'similarity' => 0.8,
                'metadata' => [],
            ]
        ]));
    
    $options = [
        'vector_weight' => 0.7,
        'metadata_weight' => 0.3,
        'limit' => 10,
    ];
    
    $result = $this->service->search($query, [], $options);
    
    expect($result['metadata']['vector_results'])->toBeInt()
        ->and($result['metadata']['metadata_results'])->toBeInt()
        ->and($result['metadata']['options_used'])->toBe($options);
});

test('search applies metadata filters correctly', function () {
    $query = 'water';
    $filters = [
        'borough' => 'MANHATTAN',
        'complaint_type' => 'Water System',
        'risk_level' => 'high',
    ];
    
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(['embedding' => [0.1], 'model' => 'test']);
    
    DocumentEmbedding::shouldReceive('similarTo')
        ->once()
        ->andReturn(collect());
    
    // Create test data with analysis for risk filtering
    $complaint = Complaint::factory()->create([
        'borough' => 'MANHATTAN',
        'complaint_type' => 'Water System',
    ]);
    
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
        'risk_score' => 0.8, // High risk
    ]);
    
    $result = $this->service->search($query, $filters);
    
    expect($result['metadata']['filters_applied'])->toBe($filters);
});

test('search handles empty vector results gracefully', function () {
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(null); // Simulate embedding failure
    
    $result = $this->service->search('test query');
    
    expect($result)->toBeArray()
        ->and($result['results'])->toBeArray();
});

test('fallbackSearch works when vector search fails', function () {
    $query = 'noise complaint';
    $filters = ['borough' => 'MANHATTAN'];
    $options = ['include_fallback' => true];
    
    // Mock the service to throw an exception during normal search
    $serviceMock = Mockery::mock(HybridSearchService::class, [$this->embeddingService, $this->pythonBridge])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    
    // Mock the vector search to fail
    $serviceMock->shouldReceive('vectorSimilaritySearch')
        ->andThrow(new Exception('Vector search failed'));
    
    // Mock the fallback metadata search
    $serviceMock->shouldReceive('metadataSearch')
        ->andReturn([]);
    
    $serviceMock->shouldReceive('enhanceWithComplaintData')
        ->andReturn([]);
    
    $result = $serviceMock->search($query, $filters, $options);
    
    expect($result)->toBeArray()
        ->and($result['metadata']['search_mode'])->toBe('fallback_metadata_only');
});

test('calculateTextRelevance scores fields appropriately', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Noise - Residential',  // Should get 0.4
        'descriptor' => 'Loud music party',         // Should get 0.3
        'incident_address' => '123 Music Street',   // Should get 0.2
        'agency_name' => 'NYPD Music Division',     // Should get 0.1
    ]);
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('calculateTextRelevance');
    $method->setAccessible(true);
    
    $relevance = $method->invoke($this->service, $complaint, 'music');
    
    // Should get points from all fields: 0.4 + 0.3 + 0.2 + 0.1 = 1.0
    expect($relevance)->toBe(1.0);
    
    // Test partial match
    $partialRelevance = $method->invoke($this->service, $complaint, 'noise');
    expect($partialRelevance)->toBe(0.4); // Only complaint_type matches
});

test('formatComplaintForSearch creates structured content', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Water System',
        'descriptor' => 'Broken pipe',
        'borough' => 'BROOKLYN',
        'incident_address' => '123 Main St',
        'agency_name' => 'DEP',
        'status' => 'Open',
    ]);
    
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
        'summary' => 'Infrastructure issue requiring attention',
    ]);
    
    $complaint->load('analysis');
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('formatComplaintForSearch');
    $method->setAccessible(true);
    
    $formatted = $method->invoke($this->service, $complaint);
    
    expect($formatted)->toContain('TYPE: Water System')
        ->and($formatted)->toContain('DESCRIPTION: Broken pipe')
        ->and($formatted)->toContain('LOCATION: BROOKLYN, 123 Main St')
        ->and($formatted)->toContain('AGENCY: DEP')
        ->and($formatted)->toContain('STATUS: Open')
        ->and($formatted)->toContain('SUMMARY: Infrastructure issue requiring attention');
});

test('enhanceWithComplaintData loads full complaint models', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
        'summary' => 'Test analysis',
        'risk_score' => 0.7,
        'category' => 'Test Category',
        'tags' => ['test', 'tag'],
    ]);
    
    $results = [
        [
            'document_type' => 'complaint',
            'document_id' => $complaint->id,
        ]
    ];
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('enhanceWithComplaintData');
    $method->setAccessible(true);
    
    $enhanced = $method->invoke($this->service, $results);
    
    expect($enhanced[0]['complaint'])->toBeArray()
        ->and($enhanced[0]['complaint']['id'])->toBe($complaint->id)
        ->and($enhanced[0]['complaint']['type'])->toBe($complaint->complaint_type)
        ->and($enhanced[0]['complaint']['borough'])->toBe($complaint->borough)
        ->and($enhanced[0]['complaint']['analysis']['summary'])->toBe('Test analysis')
        ->and($enhanced[0]['complaint']['analysis']['risk_score'])->toBe(0.7);
});

test('search respects similarity threshold', function () {
    $query = 'test query';
    $options = ['similarity_threshold' => 0.8];
    
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(['embedding' => [0.1], 'model' => 'test']);
    
    DocumentEmbedding::shouldReceive('similarTo')
        ->with(anything(), 0.8, anything())
        ->once()
        ->andReturn(collect());
    
    $this->service->search($query, [], $options);
    
    // The expectation is verified by the Mockery shouldReceive call above
    expect(true)->toBeTrue();
});

test('search limits results correctly', function () {
    $query = 'test query';
    $options = ['limit' => 5];
    
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(['embedding' => [0.1], 'model' => 'test']);
    
    DocumentEmbedding::shouldReceive('similarTo')
        ->with(anything(), anything(), 5)
        ->once()
        ->andReturn(collect());
    
    $this->service->search($query, [], $options);
    
    // The expectation is verified by the Mockery shouldReceive call above
    expect(true)->toBeTrue();
});

test('combineResults merges vector and metadata results with scoring', function () {
    $vectorResults = [
        [
            'type' => 'vector',
            'document_type' => 'complaint',
            'document_id' => 1,
            'similarity' => 0.9,
            'source' => 'vector_search',
        ]
    ];
    
    $metadataResults = [
        [
            'type' => 'metadata',
            'document_type' => 'complaint',
            'document_id' => 1, // Same document
            'relevance' => 0.8,
            'source' => 'metadata_search',
        ],
        [
            'type' => 'metadata',
            'document_type' => 'complaint',
            'document_id' => 2, // Different document
            'relevance' => 0.6,
            'source' => 'metadata_search',
        ]
    ];
    
    $options = [
        'vector_weight' => 0.7,
        'metadata_weight' => 0.3,
        'limit' => 10,
    ];
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('combineResults');
    $method->setAccessible(true);
    
    $combined = $method->invoke($this->service, $vectorResults, $metadataResults, $options);
    
    expect($combined)->toHaveCount(2) // Two unique documents
        ->and($combined[0]['combined_score'])->toBeFloat()
        ->and($combined[0]['combined_score'])->toBeGreaterThan($combined[1]['combined_score']); // First should have higher score
});

test('search logs performance metrics', function () {
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(['embedding' => [0.1], 'model' => 'test']);
    
    DocumentEmbedding::shouldReceive('similarTo')
        ->once()
        ->andReturn(collect());
    
    $result = $this->service->search('test query');
    
    expect($result['metadata']['search_duration_ms'])->toBeFloat()
        ->and($result['metadata']['search_duration_ms'])->toBeGreaterThan(0);
});