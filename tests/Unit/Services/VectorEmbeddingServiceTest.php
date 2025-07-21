<?php

use App\Services\VectorEmbeddingService;
use App\Services\PythonAiBridge;
use App\Models\DocumentEmbedding;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;

beforeEach(function () {
    $this->pythonBridge = Mockery::mock(PythonAiBridge::class);
    $this->service = new VectorEmbeddingService($this->pythonBridge);
});

test('generateEmbedding creates new embedding for complaint', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Noise - Residential',
        'descriptor' => 'Loud music from apartment',
        'borough' => 'MANHATTAN',
    ]);
    
    $mockEmbeddingData = [
        'embedding' => array_fill(0, 1536, 0.1),
        'model' => 'text-embedding-3-small',
        'dimension' => 1536,
    ];
    
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn($mockEmbeddingData);
    
    $result = $this->service->generateEmbedding($complaint);
    
    expect($result)->toBeInstanceOf(DocumentEmbedding::class)
        ->and($result->document_type)->toBe(DocumentEmbedding::TYPE_COMPLAINT)
        ->and($result->document_id)->toBe($complaint->id)
        ->and($result->embedding_model)->toBe('text-embedding-3-small')
        ->and($result->embedding_dimension)->toBe(1536);
    
    // Verify content includes complaint details
    expect($result->content)->toContain('Noise - Residential')
        ->and($result->content)->toContain('Loud music from apartment')
        ->and($result->content)->toContain('MANHATTAN');
});

test('generateEmbedding reuses existing embedding for same content', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Test Type',
        'descriptor' => 'Test Description',
    ]);
    
    // Create existing embedding with same content
    $content = $this->service->formatComplaintContent($complaint);
    $hash = DocumentEmbedding::createContentHash($content);
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('formatComplaintContent');
    $method->setAccessible(true);
    $formattedContent = $method->invoke($this->service, $complaint);
    
    $existingEmbedding = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 999, // Different ID
        'document_hash' => DocumentEmbedding::createContentHash($formattedContent),
        'content' => $formattedContent,
        'metadata' => [],
        'embedding_model' => 'existing-model',
        'embedding_dimension' => 1536,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    // Should not call Python bridge since embedding exists
    $this->pythonBridge->shouldNotReceive('generateEmbedding');
    
    $result = $this->service->generateEmbedding($complaint);
    
    expect($result)->toBeInstanceOf(DocumentEmbedding::class)
        ->and($result->id)->toBe($existingEmbedding->id);
});

test('generateEmbedding handles custom content', function () {
    $complaint = Complaint::factory()->create();
    $customContent = 'Custom embedding content';
    
    $mockEmbeddingData = [
        'embedding' => [0.1, 0.2, 0.3],
        'model' => 'test-model',
        'dimension' => 3,
    ];
    
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->with($customContent)
        ->once()
        ->andReturn($mockEmbeddingData);
    
    $result = $this->service->generateEmbedding($complaint, $customContent);
    
    expect($result)->toBeInstanceOf(DocumentEmbedding::class)
        ->and($result->content)->toBe($customContent);
});

test('generateEmbedding returns null on failure', function () {
    $complaint = Complaint::factory()->create();
    
    // Mock failed embedding generation
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(null);
    
    $result = $this->service->generateEmbedding($complaint);
    
    expect($result)->toBeNull();
});

test('generateEmbedding handles empty content', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => '',
        'descriptor' => '',
        'borough' => '',
    ]);
    
    // Should not call Python bridge for empty content
    $this->pythonBridge->shouldNotReceive('generateEmbedding');
    
    $result = $this->service->generateEmbedding($complaint);
    
    expect($result)->toBeNull();
});

test('searchSimilar finds relevant documents', function () {
    $query = 'noise complaint';
    $documentType = DocumentEmbedding::TYPE_COMPLAINT;
    
    $mockEmbeddingData = [
        'embedding' => [0.1, 0.2, 0.3],
        'model' => 'test-model',
        'dimension' => 3,
    ];
    
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->with($query)
        ->once()
        ->andReturn($mockEmbeddingData);
    
    // Create test embeddings and documents
    $complaint = Complaint::factory()->create();
    $embedding = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => $complaint->id,
        'document_hash' => hash('sha256', 'test content'),
        'content' => 'noise complaint content',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    // Mock the similarity search
    $mockResults = collect([
        (object) [
            'id' => $embedding->id,
            'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
            'document_id' => $complaint->id,
            'content' => 'noise complaint content',
            'metadata' => [],
            'similarity' => 0.9,
        ]
    ]);
    
    // Mock DocumentEmbedding::searchSimilar static method
    DocumentEmbedding::shouldReceive('searchSimilar')
        ->once()
        ->andReturn($mockResults);
    
    $results = $this->service->searchSimilar($query, $documentType);
    
    expect($results)->toBeArray()
        ->and($results)->toHaveCount(1)
        ->and($results[0]['similarity'])->toBe(0.9)
        ->and($results[0]['document_type'])->toBe(DocumentEmbedding::TYPE_COMPLAINT)
        ->and($results[0]['document'])->toBeInstanceOf(Complaint::class);
});

test('searchSimilar returns empty array on embedding failure', function () {
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(null);
    
    $results = $this->service->searchSimilar('test query');
    
    expect($results)->toBeArray()
        ->and($results)->toBeEmpty();
});

test('generateBatchEmbeddings processes multiple documents', function () {
    $complaints = Complaint::factory()->count(3)->create();
    
    // Mock successful embedding generation for each
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->times(3)
        ->andReturn([
            'embedding' => [0.1, 0.2, 0.3],
            'model' => 'test-model',
            'dimension' => 3,
        ]);
    
    $results = $this->service->generateBatchEmbeddings($complaints->toArray(), 2);
    
    expect($results)->toBeArray()
        ->and($results['processed'])->toBe(3)
        ->and($results['succeeded'])->toBe(3)
        ->and($results['failed'])->toBe(0)
        ->and($results['errors'])->toBeEmpty();
});

test('generateBatchEmbeddings handles mixed success and failure', function () {
    $complaints = Complaint::factory()->count(3)->create();
    
    // Mock mixed results
    $this->pythonBridge
        ->shouldReceive('generateEmbedding')
        ->times(3)
        ->andReturn(
            ['embedding' => [0.1], 'model' => 'test', 'dimension' => 1], // Success
            null, // Failure
            ['embedding' => [0.2], 'model' => 'test', 'dimension' => 1]  // Success
        );
    
    $results = $this->service->generateBatchEmbeddings($complaints->toArray());
    
    expect($results['processed'])->toBe(3)
        ->and($results['succeeded'])->toBe(2)
        ->and($results['failed'])->toBe(1)
        ->and($results['errors'])->toHaveCount(1);
});

test('extractDocumentData handles complaint model correctly', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Noise',
        'descriptor' => 'Loud music',
        'borough' => 'MANHATTAN',
        'agency' => 'NYPD',
        'status' => 'Open',
    ]);
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('extractDocumentData');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->service, $complaint);
    
    expect($result['type'])->toBe(DocumentEmbedding::TYPE_COMPLAINT)
        ->and($result['content'])->toContain('Noise')
        ->and($result['content'])->toContain('Loud music')
        ->and($result['content'])->toContain('MANHATTAN')
        ->and($result['metadata']['borough'])->toBe('MANHATTAN')
        ->and($result['metadata']['complaint_type'])->toBe('Noise');
});

test('extractDocumentData handles complaint analysis model correctly', function () {
    $analysis = ComplaintAnalysis::factory()->create([
        'summary' => 'AI generated summary',
        'risk_score' => 0.8,
        'category' => 'Infrastructure',
        'tags' => ['urgent', 'water'],
    ]);
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('extractDocumentData');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->service, $analysis);
    
    expect($result['type'])->toBe(DocumentEmbedding::TYPE_ANALYSIS)
        ->and($result['content'])->toBe('AI generated summary')
        ->and($result['metadata']['risk_score'])->toBe(0.8)
        ->and($result['metadata']['category'])->toBe('Infrastructure')
        ->and($result['metadata']['tags'])->toContain('urgent');
});

test('formatComplaintContent creates comprehensive text', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Water System',
        'descriptor' => 'Broken pipe',
        'borough' => 'BROOKLYN',
        'incident_address' => '123 Main St',
        'agency_name' => 'DEP',
        'agency' => 'DEP',
        'status' => 'Open',
    ]);
    
    // Add analysis
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
        'summary' => 'Water infrastructure issue',
        'category' => 'Infrastructure',
        'tags' => ['water', 'pipe'],
    ]);
    
    $complaint->load('analysis');
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('formatComplaintContent');
    $method->setAccessible(true);
    
    $content = $method->invoke($this->service, $complaint);
    
    expect($content)->toContain('COMPLAINT TYPE: Water System')
        ->and($content)->toContain('DESCRIPTION: Broken pipe')
        ->and($content)->toContain('LOCATION: BROOKLYN, 123 Main St')
        ->and($content)->toContain('AGENCY: DEP (DEP)')
        ->and($content)->toContain('STATUS: Open')
        ->and($content)->toContain('AI SUMMARY: Water infrastructure issue')
        ->and($content)->toContain('CATEGORY: Infrastructure')
        ->and($content)->toContain('TAGS: water, pipe');
});

test('loadRelatedDocument returns correct model type', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create();
    
    $complaintEmbedding = new DocumentEmbedding([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => $complaint->id,
    ]);
    
    $analysisEmbedding = new DocumentEmbedding([
        'document_type' => DocumentEmbedding::TYPE_ANALYSIS,
        'document_id' => $analysis->id,
    ]);
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('loadRelatedDocument');
    $method->setAccessible(true);
    
    $complaintResult = $method->invoke($this->service, $complaintEmbedding);
    expect($complaintResult)->toBeInstanceOf(Complaint::class)
        ->and($complaintResult->id)->toBe($complaint->id);
    
    $analysisResult = $method->invoke($this->service, $analysisEmbedding);
    expect($analysisResult)->toBeInstanceOf(ComplaintAnalysis::class)
        ->and($analysisResult->id)->toBe($analysis->id);
});

test('syncVectorStore delegates to python bridge', function () {
    $mockResult = [
        'success' => true,
        'synced_count' => 100,
    ];
    
    $this->pythonBridge
        ->shouldReceive('syncPgVectorStore')
        ->once()
        ->andReturn($mockResult);
    
    $result = $this->service->syncVectorStore();
    
    expect($result)->toBe($mockResult)
        ->and($result['success'])->toBeTrue()
        ->and($result['synced_count'])->toBe(100);
});

test('syncVectorStore handles failure gracefully', function () {
    $this->pythonBridge
        ->shouldReceive('syncPgVectorStore')
        ->once()
        ->andThrow(new Exception('Sync failed'));
    
    $result = $this->service->syncVectorStore();
    
    expect($result)->toBeArray()
        ->and($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Sync failed');
});