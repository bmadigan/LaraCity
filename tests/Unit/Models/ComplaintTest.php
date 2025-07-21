<?php

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Models\Action;
use App\Models\DocumentEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;

test('complaint has fillable attributes', function () {
    $data = [
        'complaint_number' => 'TEST123',
        'complaint_type' => 'Noise - Residential',
        'descriptor' => 'Loud Music/Party',
        'agency' => 'NYPD',
        'agency_name' => 'New York City Police Department',
        'status' => Complaint::STATUS_OPEN,
        'borough' => Complaint::BOROUGH_MANHATTAN,
        'incident_address' => '123 Test Street',
        'city' => 'NEW YORK',
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'submitted_at' => now(),
    ];
    
    $complaint = Complaint::create($data);
    
    expect($complaint->complaint_number)->toBe('TEST123')
        ->and($complaint->complaint_type)->toBe('Noise - Residential')
        ->and($complaint->descriptor)->toBe('Loud Music/Party')
        ->and($complaint->agency)->toBe('NYPD')
        ->and($complaint->status)->toBe(Complaint::STATUS_OPEN)
        ->and($complaint->borough)->toBe(Complaint::BOROUGH_MANHATTAN);
});

test('complaint casts attributes correctly', function () {
    $complaint = Complaint::factory()->create([
        'submitted_at' => '2024-01-01 12:00:00',
        'resolved_at' => '2024-01-02 12:00:00',
        'due_date' => '2024-01-03 12:00:00',
        'latitude' => '40.7128',
        'longitude' => '-74.0060',
    ]);
    
    expect($complaint->submitted_at)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($complaint->resolved_at)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($complaint->due_date)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($complaint->latitude)->toBeNumeric()
        ->and($complaint->longitude)->toBeNumeric()
        ->and((float) $complaint->latitude)->toBe(40.7128)
        ->and((float) $complaint->longitude)->toBe(-74.0060);
});

test('complaint has one analysis relationship', function () {
    // Clear ALL data to ensure clean state
    ComplaintAnalysis::query()->delete();
    Complaint::query()->delete();
    
    $complaint = Complaint::factory()->create();
    
    // Delete any auto-created analysis to avoid interference
    ComplaintAnalysis::where('complaint_id', $complaint->id)->delete();
    
    $analysis = ComplaintAnalysis::create([
        'complaint_id' => $complaint->id,
        'summary' => 'Test analysis summary',
        'risk_score' => 0.5,
        'category' => 'Test Category',
        'tags' => ['test', 'analysis'],
    ]);
    
    expect($complaint->analysis)->toBeInstanceOf(ComplaintAnalysis::class)
        ->and($complaint->analysis->id)->toBe($analysis->id);
});

test('complaint has many actions relationship', function () {
    $complaint = Complaint::factory()->create();
    $actions = Action::factory()->count(3)->create([
        'complaint_id' => $complaint->id,
    ]);
    
    expect($complaint->actions)->toHaveCount(3)
        ->and($complaint->actions->first())->toBeInstanceOf(Action::class);
});

test('complaint has many embeddings relationship', function () {
    $complaint = Complaint::factory()->create();
    
    // Create embeddings for this complaint
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_COMPLAINT,
        'document_id' => $complaint->id,
        'content' => 'test content',
        'document_hash' => hash('sha256', 'test content'),
        'embedding' => '[0.1, 0.2, 0.3]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    // Create embedding for different document type (should not be included)
    DocumentEmbedding::create([
        'document_type' => 'other',
        'document_id' => $complaint->id,
        'content' => 'other content',
        'document_hash' => hash('sha256', 'other content'),
        'embedding' => '[0.4, 0.5, 0.6]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    expect($complaint->embeddings)->toHaveCount(1)
        ->and($complaint->embeddings->first()->document_type)->toBe(DocumentEmbedding::TYPE_COMPLAINT);
});

test('complaint status constants are defined correctly', function () {
    expect(Complaint::STATUS_OPEN)->toBe('Open')
        ->and(Complaint::STATUS_IN_PROGRESS)->toBe('InProgress')
        ->and(Complaint::STATUS_CLOSED)->toBe('Closed')
        ->and(Complaint::STATUS_ESCALATED)->toBe('Escalated');
});

test('complaint priority constants are defined correctly', function () {
    expect(Complaint::PRIORITY_LOW)->toBe('Low')
        ->and(Complaint::PRIORITY_MEDIUM)->toBe('Medium')
        ->and(Complaint::PRIORITY_HIGH)->toBe('High')
        ->and(Complaint::PRIORITY_CRITICAL)->toBe('Critical');
});

test('complaint borough constants are defined correctly', function () {
    expect(Complaint::BOROUGH_MANHATTAN)->toBe('MANHATTAN')
        ->and(Complaint::BOROUGH_BROOKLYN)->toBe('BROOKLYN')
        ->and(Complaint::BOROUGH_QUEENS)->toBe('QUEENS')
        ->and(Complaint::BOROUGH_BRONX)->toBe('BRONX')
        ->and(Complaint::BOROUGH_STATEN_ISLAND)->toBe('STATEN ISLAND');
});

test('getStatuses returns all status values', function () {
    $statuses = Complaint::getStatuses();
    
    expect($statuses)->toBeArray()
        ->and($statuses)->toContain(Complaint::STATUS_OPEN)
        ->and($statuses)->toContain(Complaint::STATUS_IN_PROGRESS)
        ->and($statuses)->toContain(Complaint::STATUS_CLOSED)
        ->and($statuses)->toContain(Complaint::STATUS_ESCALATED)
        ->and($statuses)->toHaveCount(4);
});

test('getPriorities returns all priority values', function () {
    $priorities = Complaint::getPriorities();
    
    expect($priorities)->toBeArray()
        ->and($priorities)->toContain(Complaint::PRIORITY_LOW)
        ->and($priorities)->toContain(Complaint::PRIORITY_MEDIUM)
        ->and($priorities)->toContain(Complaint::PRIORITY_HIGH)
        ->and($priorities)->toContain(Complaint::PRIORITY_CRITICAL)
        ->and($priorities)->toHaveCount(4);
});

test('getBoroughs returns all borough values', function () {
    $boroughs = Complaint::getBoroughs();
    
    expect($boroughs)->toBeArray()
        ->and($boroughs)->toContain(Complaint::BOROUGH_MANHATTAN)
        ->and($boroughs)->toContain(Complaint::BOROUGH_BROOKLYN)
        ->and($boroughs)->toContain(Complaint::BOROUGH_QUEENS)
        ->and($boroughs)->toContain(Complaint::BOROUGH_BRONX)
        ->and($boroughs)->toContain(Complaint::BOROUGH_STATEN_ISLAND)
        ->and($boroughs)->toHaveCount(5);
});

test('isHighRisk returns true when risk score is 0.7 or higher', function () {
    $complaint = Complaint::factory()->create();
    
    // No analysis
    expect($complaint->isHighRisk())->toBeFalse();
    
    // Low risk
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
        'risk_score' => 0.3,
    ]);
    $complaint->refresh();
    expect($complaint->isHighRisk())->toBeFalse();
    
    // Medium risk
    $complaint->analysis->update(['risk_score' => 0.6]);
    $complaint->refresh();
    expect($complaint->isHighRisk())->toBeFalse();
    
    // High risk (exactly 0.7)
    $complaint->analysis->update(['risk_score' => 0.7]);
    $complaint->refresh();
    expect($complaint->isHighRisk())->toBeTrue();
    
    // High risk (above 0.7)
    $complaint->analysis->update(['risk_score' => 0.9]);
    $complaint->refresh();
    expect($complaint->isHighRisk())->toBeTrue();
});

test('scopeByBorough filters complaints by borough', function () {
    // Create complaints in different boroughs
    Complaint::factory()->create(['borough' => 'MANHATTAN']);
    Complaint::factory()->create(['borough' => 'BROOKLYN']);
    Complaint::factory()->create(['borough' => 'QUEENS']);
    
    // Test with uppercase
    $manhattan = Complaint::byBorough('MANHATTAN')->get();
    expect($manhattan)->toHaveCount(1)
        ->and($manhattan->first()->borough)->toBe('MANHATTAN');
    
    // Test with lowercase (should be converted to uppercase)
    $brooklyn = Complaint::byBorough('brooklyn')->get();
    expect($brooklyn)->toHaveCount(1)
        ->and($brooklyn->first()->borough)->toBe('BROOKLYN');
});

test('scopeByStatus filters complaints by status', function () {
    Complaint::factory()->create(['status' => Complaint::STATUS_OPEN]);
    Complaint::factory()->create(['status' => Complaint::STATUS_CLOSED]);
    Complaint::factory()->create(['status' => Complaint::STATUS_OPEN]);
    
    $open = Complaint::byStatus(Complaint::STATUS_OPEN)->get();
    expect($open)->toHaveCount(2);
    
    $closed = Complaint::byStatus(Complaint::STATUS_CLOSED)->get();
    expect($closed)->toHaveCount(1);
});

test('scopeByPriority filters complaints by priority', function () {
    Complaint::factory()->create(['priority' => Complaint::PRIORITY_HIGH]);
    Complaint::factory()->create(['priority' => Complaint::PRIORITY_LOW]);
    Complaint::factory()->create(['priority' => Complaint::PRIORITY_HIGH]);
    
    $high = Complaint::byPriority(Complaint::PRIORITY_HIGH)->get();
    expect($high)->toHaveCount(2);
    
    $low = Complaint::byPriority(Complaint::PRIORITY_LOW)->get();
    expect($low)->toHaveCount(1);
});

test('scopeByDateRange filters complaints by date range', function () {
    Complaint::factory()->create(['submitted_at' => '2024-01-01']);
    Complaint::factory()->create(['submitted_at' => '2024-01-15']);
    Complaint::factory()->create(['submitted_at' => '2024-02-01']);
    
    $january = Complaint::byDateRange('2024-01-01', '2024-01-31')->get();
    expect($january)->toHaveCount(2);
    
    $midJanuary = Complaint::byDateRange('2024-01-10', '2024-01-20')->get();
    expect($midJanuary)->toHaveCount(1);
});

test('complaint model uses unguarded mass assignment', function () {
    // Test that we can mass assign any attribute
    $data = [
        'complaint_number' => 'UNGUARDED123',
        'random_field' => 'This should work',
        'another_field' => 'Because unguarded',
    ];
    
    $complaint = new Complaint($data);
    
    expect($complaint->complaint_number)->toBe('UNGUARDED123')
        ->and($complaint->random_field)->toBe('This should work')
        ->and($complaint->another_field)->toBe('Because unguarded');
});

test('multiple scopes can be chained', function () {
    // Create test data
    Complaint::factory()->create([
        'borough' => 'MANHATTAN',
        'status' => Complaint::STATUS_OPEN,
        'submitted_at' => '2024-01-15',
    ]);
    
    Complaint::factory()->create([
        'borough' => 'MANHATTAN',
        'status' => Complaint::STATUS_CLOSED,
        'submitted_at' => '2024-01-15',
    ]);
    
    Complaint::factory()->create([
        'borough' => 'BROOKLYN',
        'status' => Complaint::STATUS_OPEN,
        'submitted_at' => '2024-01-15',
    ]);
    
    // Chain multiple scopes
    $results = Complaint::byBorough('MANHATTAN')
        ->byStatus(Complaint::STATUS_OPEN)
        ->byDateRange('2024-01-01', '2024-01-31')
        ->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->borough)->toBe('MANHATTAN')
        ->and($results->first()->status)->toBe(Complaint::STATUS_OPEN);
});