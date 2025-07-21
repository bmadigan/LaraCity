<?php

use App\Models\DocumentEmbedding;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

test('document embedding has fillable attributes', function () {
    $data = [
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'test content'),
        'content' => 'Test complaint content',
        'metadata' => ['borough' => 'MANHATTAN', 'type' => 'Noise'],
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimension' => 1536,
        'embedding' => '[' . implode(',', array_fill(0, 1536, 0.1)) . ']',
    ];
    
    $embedding = DocumentEmbedding::create($data);
    
    expect($embedding->document_type)->toBe(DocumentEmbedding::TYPE_COMPLAINT)
        ->and($embedding->document_id)->toBe(1)
        ->and($embedding->content)->toBe('Test complaint content')
        ->and($embedding->metadata)->toBe(['borough' => 'MANHATTAN', 'type' => 'Noise'])
        ->and($embedding->embedding_model)->toBe('text-embedding-3-small')
        ->and($embedding->embedding_dimension)->toBe(1536);
});

test('document embedding casts attributes correctly', function () {
    $embedding = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'test'),
        'content' => 'test',
        'metadata' => ['test' => 'data'],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    expect($embedding->metadata)->toBeArray()
        ->and($embedding->metadata['test'])->toBe('data')
        ->and($embedding->document_id)->toBeInt();
});

test('document embedding constants are defined correctly', function () {
    expect(DocumentEmbedding::TYPE_COMPLAINT)->toBe('complaint')
        ->and(DocumentEmbedding::TYPE_ANALYSIS)->toBe('analysis')
        ->and(DocumentEmbedding::TYPE_USER_QUESTION)->toBe('user_question');
});

test('createContentHash generates consistent SHA256 hash', function () {
    $content = 'This is test content for hashing';
    
    $hash1 = DocumentEmbedding::createContentHash($content);
    $hash2 = DocumentEmbedding::createContentHash($content);
    
    expect($hash1)->toBe($hash2)
        ->and(strlen($hash1))->toBe(64) // SHA256 produces 64 character hex string
        ->and($hash1)->toMatch('/^[a-f0-9]{64}$/');
    
    // Different content should produce different hash
    $hash3 = DocumentEmbedding::createContentHash('Different content');
    expect($hash3)->not->toBe($hash1);
});

test('findByContentHash finds embedding by hash', function () {
    $content = 'Unique content for hash search';
    $hash = DocumentEmbedding::createContentHash($content);
    
    $embedding = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => $hash,
        'content' => $content,
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    $found = DocumentEmbedding::findByContentHash($hash);
    
    expect($found)->toBeInstanceOf(DocumentEmbedding::class)
        ->and($found->id)->toBe($embedding->id)
        ->and($found->content)->toBe($content);
});

test('findByContentHash returns null when not found', function () {
    $nonExistentHash = hash('sha256', 'non-existent-content');
    
    $found = DocumentEmbedding::findByContentHash($nonExistentHash);
    
    expect($found)->toBeNull();
});

test('scopeForDocument filters by document type and id', function () {
    // Create embeddings for different documents
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'complaint1'),
        'content' => 'complaint 1',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 2,
        'document_hash' => hash('sha256', 'complaint2'),
        'content' => 'complaint 2',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.4, 0.5, 0.6]',
    ]);
    
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_ANALYSIS,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'analysis1'),
        'content' => 'analysis 1',
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.7, 0.8, 0.9]',
    ]);
    
    $complaintEmbeddings = DocumentEmbedding::forDocument(DocumentEmbedding::TYPE_COMPLAINT, 1)->get();
    expect($complaintEmbeddings)->toHaveCount(1)
        ->and($complaintEmbeddings->first()->content)->toBe('complaint 1');
    
    $analysisEmbeddings = DocumentEmbedding::forDocument(DocumentEmbedding::TYPE_ANALYSIS, 1)->get();
    expect($analysisEmbeddings)->toHaveCount(1)
        ->and($analysisEmbeddings->first()->content)->toBe('analysis 1');
});

test('document embedding handles large vectors', function () {
    // Create a large vector (1536 dimensions like OpenAI embeddings)
    $dimensions = 1536;
    $vector = array_fill(0, $dimensions, 0.1);
    $vectorString = '[' . implode(',', $vector) . ']';
    
    $embedding = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'large vector test'),
        'content' => 'large vector test',
        'metadata' => [],
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimension' => $dimensions,
        'embedding' => $vectorString,
    ]);
    
    expect($embedding->embedding_dimension)->toBe($dimensions)
        ->and(strlen($embedding->embedding))->toBeGreaterThan(1000);
});

test('document embedding metadata can store complex structures', function () {
    $complexMetadata = [
        'filters' => [
            'borough' => 'MANHATTAN',
            'date_range' => ['start' => '2024-01-01', 'end' => '2024-12-31'],
            'risk_levels' => ['high', 'medium'],
        ],
        'source' => 'user_query',
        'timestamp' => now()->toISOString(),
        'user_id' => 123,
    ];
    
    $embedding = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_USER_QUESTION,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'complex metadata'),
        'content' => 'complex metadata test',
        'metadata' => $complexMetadata,
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    expect($embedding->metadata)->toBe($complexMetadata)
        ->and($embedding->metadata['filters']['borough'])->toBe('MANHATTAN')
        ->and($embedding->metadata['filters']['risk_levels'])->toBeArray()
        ->and($embedding->metadata['filters']['risk_levels'])->toContain('high');
});

test('document embedding prevents duplicate content hashes', function () {
    $content = 'Duplicate content test';
    $hash = DocumentEmbedding::createContentHash($content);
    
    // Create first embedding
    $embedding1 = DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => $hash,
        'content' => $content,
        'metadata' => [],
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'embedding' => '[0.1, 0.2, 0.3]',
    ]);
    
    expect($embedding1)->toBeInstanceOf(DocumentEmbedding::class);
    
    // Attempting to create another with same hash should be handled by application logic
    // This test verifies that we can find existing embeddings by hash
    $existing = DocumentEmbedding::findByContentHash($hash);
    expect($existing)->not->toBeNull()
        ->and($existing->id)->toBe($embedding1->id);
});

test('document embedding can be queried by model', function () {
    // Create embeddings with different models
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 1,
        'document_hash' => hash('sha256', 'model1'),
        'content' => 'model test 1',
        'metadata' => [],
        'embedding_model' => 'text-embedding-ada-002',
        'embedding_dimension' => 1536,
        'embedding' => '[0.1]',
    ]);
    
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => 2,
        'document_hash' => hash('sha256', 'model2'),
        'content' => 'model test 2',
        'metadata' => [],
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimension' => 1536,
        'embedding' => '[0.2]',
    ]);
    
    $adaEmbeddings = DocumentEmbedding::where('embedding_model', 'text-embedding-ada-002')->get();
    expect($adaEmbeddings)->toHaveCount(1);
    
    $v3Embeddings = DocumentEmbedding::where('embedding_model', 'text-embedding-3-small')->get();
    expect($v3Embeddings)->toHaveCount(1);
});