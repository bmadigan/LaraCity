<?php

use App\Console\Commands\GenerateEmbeddingsCommand;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Models\DocumentEmbedding;
use App\Services\VectorEmbeddingService;

beforeEach(function () {
    $this->embeddingService = Mockery::mock(VectorEmbeddingService::class);
    app()->instance(VectorEmbeddingService::class, $this->embeddingService);
});

test('generate embeddings command processes all complaints when no options provided', function () {
    // Create test complaints
    $complaints = Complaint::factory()->count(3)->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(3)
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate')
        ->expectsOutput('Starting embedding generation for complaints...')
        ->expectsOutput('Processed 3 complaints')
        ->expectsOutput('Successfully generated 3 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command processes specific complaint by id', function () {
    $complaint1 = Complaint::factory()->create();
    $complaint2 = Complaint::factory()->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->once()
        ->with(Mockery::on(function ($complaint) use ($complaint1) {
            return $complaint->id === $complaint1->id;
        }))
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--complaint-id' => $complaint1->id])
        ->expectsOutput('Processing specific complaint ID: ' . $complaint1->id)
        ->expectsOutput('Successfully generated 1 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command handles non-existent complaint id', function () {
    $this->artisan('embeddings:generate', ['--complaint-id' => 999])
        ->expectsOutput('Complaint with ID 999 not found')
        ->assertExitCode(1);
});

test('generate embeddings command processes only missing embeddings', function () {
    // Create complaints
    $complaint1 = Complaint::factory()->create();
    $complaint2 = Complaint::factory()->create();
    $complaint3 = Complaint::factory()->create();
    
    // Create embedding for complaint1 (should be skipped)
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => $complaint1->id,
        'document_hash' => hash('sha256', 'existing'),
        'content' => 'existing content',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    // Should only process complaint2 and complaint3
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(2)
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--missing' => true])
        ->expectsOutput('Processing complaints missing embeddings...')
        ->expectsOutput('Found 2 complaints without embeddings')
        ->expectsOutput('Successfully generated 2 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command respects batch size', function () {
    Complaint::factory()->count(5)->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(5)
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--batch-size' => 2])
        ->expectsOutput('Batch size: 2')
        ->expectsOutput('Successfully generated 5 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command includes analysis embeddings when specified', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
    ]);
    
    // Should generate embeddings for both complaint and analysis
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->twice()
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--include-analysis' => true])
        ->expectsOutput('Including analysis embeddings')
        ->expectsOutput('Found 1 analyses to process')
        ->expectsOutput('Successfully generated 2 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command handles embedding generation failures', function () {
    Complaint::factory()->count(3)->create();
    
    // Mock mixed results (success, failure, success)
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(3)
        ->andReturn(
            Mockery::mock(DocumentEmbedding::class), // Success
            null, // Failure
            Mockery::mock(DocumentEmbedding::class)  // Success
        );
    
    $this->artisan('embeddings:generate')
        ->expectsOutput('Successfully generated 2 embeddings')
        ->expectsOutput('Failed to generate 1 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command shows progress for large batches', function () {
    Complaint::factory()->count(25)->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(25)
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate')
        ->expectsOutput('Processed 20 complaints...')
        ->expectsOutput('Successfully generated 25 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command handles empty result set', function () {
    // No complaints in database
    expect(Complaint::count())->toBe(0);
    
    $this->artisan('embeddings:generate')
        ->expectsOutput('No complaints found to process')
        ->assertExitCode(0);
});

test('generate embeddings command handles missing option with no missing embeddings', function () {
    $complaint = Complaint::factory()->create();
    
    // Create embedding for the complaint
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => $complaint->id,
        'document_hash' => hash('sha256', 'existing'),
        'content' => 'existing content',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    $this->artisan('embeddings:generate', ['--missing' => true])
        ->expectsOutput('Found 0 complaints without embeddings')
        ->expectsOutput('No complaints to process')
        ->assertExitCode(0);
});

test('generate embeddings command can force regenerate existing embeddings', function () {
    $complaint = Complaint::factory()->create();
    
    // Create existing embedding
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => $complaint->id,
        'document_hash' => hash('sha256', 'existing'),
        'content' => 'existing content',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--force' => true])
        ->expectsOutput('Force regenerating all embeddings')
        ->expectsOutput('Successfully generated 1 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command validates complaint id parameter', function () {
    $this->artisan('embeddings:generate', ['--complaint-id' => 'invalid'])
        ->expectsOutput('Invalid complaint ID: invalid')
        ->assertExitCode(1);
});

test('generate embeddings command validates batch size parameter', function () {
    $this->artisan('embeddings:generate', ['--batch-size' => 0])
        ->expectsOutput('Batch size must be greater than 0')
        ->assertExitCode(1);
    
    $this->artisan('embeddings:generate', ['--batch-size' => 'invalid'])
        ->expectsOutput('Invalid batch size: invalid')
        ->assertExitCode(1);
});

test('generate embeddings command processes analysis without complaints', function () {
    // Create analysis without embedding
    $analysis = ComplaintAnalysis::factory()->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->once()
        ->with(Mockery::type(ComplaintAnalysis::class))
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--include-analysis' => true, '--missing' => true])
        ->expectsOutput('Found 0 complaints without embeddings')
        ->expectsOutput('Found 1 analyses to process')
        ->expectsOutput('Successfully generated 1 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command handles service exceptions gracefully', function () {
    Complaint::factory()->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->once()
        ->andThrow(new Exception('Service unavailable'));
    
    $this->artisan('embeddings:generate')
        ->expectsOutput('Failed to generate 1 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command provides summary statistics', function () {
    Complaint::factory()->count(2)->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(2)
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $output = $this->artisan('embeddings:generate')
        ->expectsOutput('Starting embedding generation for complaints...')
        ->expectsOutput('Processed 2 complaints')
        ->expectsOutput('Successfully generated 2 embeddings')
        ->expectsOutput('Failed to generate 0 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command handles memory efficiently with large datasets', function () {
    // Create many complaints to test memory efficiency
    Complaint::factory()->count(100)->create();
    
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->times(100)
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--batch-size' => 10])
        ->expectsOutput('Successfully generated 100 embeddings')
        ->assertExitCode(0);
});

test('generate embeddings command can target specific document types', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create(['complaint_id' => $complaint->id]);
    
    // Should only process analysis when specified
    $this->embeddingService
        ->shouldReceive('generateEmbedding')
        ->once()
        ->with(Mockery::type(ComplaintAnalysis::class))
        ->andReturn(Mockery::mock(DocumentEmbedding::class));
    
    $this->artisan('embeddings:generate', ['--type' => 'analysis'])
        ->expectsOutput('Processing analysis documents only')
        ->expectsOutput('Successfully generated 1 embeddings')
        ->assertExitCode(0);
});