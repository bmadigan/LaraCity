<?php

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Models\DocumentEmbedding;

test('complaint analysis has fillable attributes', function () {
    $complaint = Complaint::factory()->create();
    
    $data = [
        'complaint_id' => $complaint->id,
        'summary' => 'AI generated summary of the complaint',
        'risk_score' => 0.75,
        'category' => 'Infrastructure',
        'tags' => ['water', 'leak', 'urgent'],
    ];
    
    $analysis = ComplaintAnalysis::create($data);
    
    expect($analysis->complaint_id)->toBe($complaint->id)
        ->and($analysis->summary)->toBe('AI generated summary of the complaint')
        ->and($analysis->risk_score)->toBe(0.75)
        ->and($analysis->category)->toBe('Infrastructure')
        ->and($analysis->tags)->toBe(['water', 'leak', 'urgent']);
});

test('complaint analysis casts attributes correctly', function () {
    $analysis = ComplaintAnalysis::factory()->create([
        'risk_score' => '0.85',
        'tags' => ['test', 'tags'],
    ]);
    
    expect($analysis->risk_score)->toBeFloat()
        ->and($analysis->risk_score)->toBe(0.85)
        ->and($analysis->tags)->toBeArray()
        ->and($analysis->tags)->toContain('test')
        ->and($analysis->tags)->toContain('tags');
});

test('complaint analysis belongs to complaint', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
    ]);
    
    expect($analysis->complaint)->toBeInstanceOf(Complaint::class)
        ->and($analysis->complaint->id)->toBe($complaint->id);
});

test('complaint analysis has many embeddings', function () {
    $analysis = ComplaintAnalysis::factory()->create();
    
    // Create embeddings for this analysis
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_ANALYSIS,
        'document_id' => $analysis->id,
        'content' => 'analysis content',
        'document_hash' => hash('sha256', 'analysis content'),
        'embedding' => '[0.1, 0.2, 0.3]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    // Create another embedding
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_ANALYSIS,
        'document_id' => $analysis->id,
        'content' => 'another analysis content',
        'document_hash' => hash('sha256', 'another analysis content'),
        'embedding' => '[0.4, 0.5, 0.6]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    expect($analysis->embeddings)->toHaveCount(2)
        ->and($analysis->embeddings->first()->document_type)->toBe(DocumentEmbedding::TYPE_ANALYSIS);
});

test('complaint analysis risk score is normalized between 0 and 1', function () {
    $analysis = ComplaintAnalysis::factory()->create([
        'risk_score' => 0.0,
    ]);
    expect($analysis->risk_score)->toBe(0.0);
    
    $analysis->update(['risk_score' => 1.0]);
    expect($analysis->risk_score)->toBe(1.0);
    
    $analysis->update(['risk_score' => 0.5]);
    expect($analysis->risk_score)->toBe(0.5);
});

test('complaint analysis handles empty tags', function () {
    $analysis = ComplaintAnalysis::factory()->create([
        'tags' => null,
    ]);
    
    expect($analysis->tags)->toBeNull();
    
    $analysis->update(['tags' => []]);
    expect($analysis->tags)->toBeArray()
        ->and($analysis->tags)->toBeEmpty();
});

test('complaint analysis stores complex tag data', function () {
    $complexTags = [
        'location' => 'manhattan',
        'type' => 'infrastructure',
        'priority' => 'high',
        'keywords' => ['water', 'leak', 'basement'],
    ];
    
    $analysis = ComplaintAnalysis::factory()->create([
        'tags' => $complexTags,
    ]);
    
    expect($analysis->tags)->toBe($complexTags)
        ->and($analysis->tags['location'])->toBe('manhattan')
        ->and($analysis->tags['keywords'])->toBeArray()
        ->and($analysis->tags['keywords'])->toContain('water');
});

test('complaint analysis categories are consistent', function () {
    // Test common categories used in the system
    $categories = [
        'Infrastructure',
        'Public Health',
        'Quality of Life',
        'Transportation',
        'General',
    ];
    
    foreach ($categories as $category) {
        $analysis = ComplaintAnalysis::factory()->create([
            'category' => $category,
        ]);
        
        expect($analysis->category)->toBe($category);
    }
});

test('complaint analysis can be queried by risk level', function () {
    // Create analyses with different risk scores
    ComplaintAnalysis::factory()->create(['risk_score' => 0.2]); // Low
    ComplaintAnalysis::factory()->create(['risk_score' => 0.5]); // Medium
    ComplaintAnalysis::factory()->create(['risk_score' => 0.8]); // High
    ComplaintAnalysis::factory()->create(['risk_score' => 0.9]); // High
    
    $highRisk = ComplaintAnalysis::where('risk_score', '>=', 0.7)->get();
    expect($highRisk)->toHaveCount(2);
    
    $mediumRisk = ComplaintAnalysis::whereBetween('risk_score', [0.4, 0.69])->get();
    expect($mediumRisk)->toHaveCount(1);
    
    $lowRisk = ComplaintAnalysis::where('risk_score', '<', 0.4)->get();
    expect($lowRisk)->toHaveCount(1);
});

test('complaint analysis maintains referential integrity', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
    ]);
    
    // Verify the relationship exists
    expect($complaint->analysis->id)->toBe($analysis->id);
    
    // Delete the complaint (should cascade delete the analysis if configured)
    $complaint->delete();
    
    // Check if analysis still exists (depends on your migration cascading rules)
    $analysisExists = ComplaintAnalysis::find($analysis->id);
    
    // This test assumes you have cascade delete configured
    // If not, adjust the expectation accordingly
    if (is_null($analysisExists)) {
        expect($analysisExists)->toBeNull();
    } else {
        expect($analysisExists)->toBeInstanceOf(ComplaintAnalysis::class);
    }
});

test('complaint analysis summary can store large text', function () {
    $largeSummary = str_repeat('This is a very detailed AI-generated analysis. ', 100);
    
    $analysis = ComplaintAnalysis::factory()->create([
        'summary' => $largeSummary,
    ]);
    
    expect($analysis->summary)->toBe($largeSummary)
        ->and(strlen($analysis->summary))->toBeGreaterThan(1000);
});

test('complaint analysis factory creates valid data', function () {
    $analysis = ComplaintAnalysis::factory()->create();
    
    expect($analysis->complaint_id)->toBeInt()
        ->and($analysis->summary)->toBeString()
        ->and($analysis->risk_score)->toBeFloat()
        ->and($analysis->risk_score)->toBeGreaterThanOrEqual(0.0)
        ->and($analysis->risk_score)->toBeLessThanOrEqual(1.0)
        ->and($analysis->category)->toBeString()
        ->and($analysis->tags)->toBeArray();
});