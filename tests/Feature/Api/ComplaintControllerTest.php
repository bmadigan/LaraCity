<?php

use App\Models\User;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('complaints index returns paginated list', function () {
    // Create test complaints
    Complaint::factory()->count(15)->create();
    
    $response = $this->getJson('/api/complaints');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'complaint_number',
                    'complaint_type',
                    'descriptor',
                    'borough',
                    'status',
                    'agency',
                    'submitted_at',
                ]
            ],
            'links',
            'meta'
        ]);
    
    expect($response->json('data'))->toHaveCount(15);
});

test('complaints index supports filtering by borough', function () {
    Complaint::factory()->count(3)->create(['borough' => 'MANHATTAN']);
    Complaint::factory()->count(2)->create(['borough' => 'BROOKLYN']);
    
    $response = $this->getJson('/api/complaints?borough=MANHATTAN');
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    
    foreach ($data as $complaint) {
        expect($complaint['borough'])->toBe('MANHATTAN');
    }
});

test('complaints index supports filtering by status', function () {
    Complaint::factory()->count(2)->create(['status' => Complaint::STATUS_OPEN]);
    Complaint::factory()->count(3)->create(['status' => Complaint::STATUS_CLOSED]);
    
    $response = $this->getJson('/api/complaints?status=' . Complaint::STATUS_OPEN);
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    
    foreach ($data as $complaint) {
        expect($complaint['status'])->toBe(Complaint::STATUS_OPEN);
    }
});

test('complaints index supports filtering by complaint type', function () {
    Complaint::factory()->count(2)->create(['complaint_type' => 'Noise - Residential']);
    Complaint::factory()->count(1)->create(['complaint_type' => 'Water System']);
    
    $response = $this->getJson('/api/complaints?type=Noise');
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    
    foreach ($data as $complaint) {
        expect($complaint['complaint_type'])->toContain('Noise');
    }
});

test('complaints index supports filtering by date range', function () {
    Complaint::factory()->create(['submitted_at' => '2024-01-01 10:00:00']);
    Complaint::factory()->create(['submitted_at' => '2024-01-15 10:00:00']);
    Complaint::factory()->create(['submitted_at' => '2024-02-01 10:00:00']);
    
    $response = $this->getJson('/api/complaints?date_from=2024-01-01&date_to=2024-01-31');
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
});

test('complaints index supports risk level filtering', function () {
    $complaint1 = Complaint::factory()->create();
    $complaint2 = Complaint::factory()->create();
    $complaint3 = Complaint::factory()->create();
    
    // High risk
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint1->id,
        'risk_score' => 0.8,
    ]);
    
    // Medium risk
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint2->id,
        'risk_score' => 0.5,
    ]);
    
    // Low risk
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint3->id,
        'risk_score' => 0.2,
    ]);
    
    $response = $this->getJson('/api/complaints?risk_level=high');
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
});

test('complaints index includes analysis data when available', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
        'summary' => 'Test analysis summary',
        'risk_score' => 0.7,
        'category' => 'Infrastructure',
    ]);
    
    $response = $this->getJson('/api/complaints');
    
    $response->assertStatus(200)
        ->assertJsonPath('data.0.analysis.summary', 'Test analysis summary')
        ->assertJsonPath('data.0.analysis.risk_score', 0.7)
        ->assertJsonPath('data.0.analysis.category', 'Infrastructure');
});

test('complaints index supports pagination', function () {
    Complaint::factory()->count(25)->create();
    
    $response = $this->getJson('/api/complaints?per_page=10');
    
    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 25);
    
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('links.next'))->not->toBeNull();
});

test('complaints index supports sorting', function () {
    $complaint1 = Complaint::factory()->create(['submitted_at' => '2024-01-01']);
    $complaint2 = Complaint::factory()->create(['submitted_at' => '2024-01-02']);
    $complaint3 = Complaint::factory()->create(['submitted_at' => '2024-01-03']);
    
    $response = $this->getJson('/api/complaints?sort_by=submitted_at&sort_direction=desc');
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data[0]['id'])->toBe($complaint3->id)
        ->and($data[1]['id'])->toBe($complaint2->id)
        ->and($data[2]['id'])->toBe($complaint1->id);
});

test('complaints show returns single complaint with analysis', function () {
    $complaint = Complaint::factory()->create();
    $analysis = ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
    ]);
    
    $response = $this->getJson("/api/complaints/{$complaint->id}");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'complaint_number',
                'complaint_type',
                'descriptor',
                'borough',
                'status',
                'analysis' => [
                    'summary',
                    'risk_score',
                    'category',
                    'tags',
                ]
            ]
        ]);
    
    expect($response->json('data.id'))->toBe($complaint->id);
});

test('complaints show returns 404 for non-existent complaint', function () {
    $response = $this->getJson('/api/complaints/999');
    
    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Complaint not found'
        ]);
});

test('complaints summary returns statistical overview', function () {
    // Create test data
    Complaint::factory()->count(5)->create(['borough' => 'MANHATTAN']);
    Complaint::factory()->count(3)->create(['borough' => 'BROOKLYN']);
    Complaint::factory()->count(2)->create(['complaint_type' => 'Noise - Residential']);
    
    $response = $this->getJson('/api/complaints/summary');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_complaints',
                'by_borough',
                'by_type',
                'by_status',
                'recent_complaints',
                'risk_distribution'
            ]
        ]);
    
    expect($response->json('data.total_complaints'))->toBe(8);
    expect($response->json('data.by_borough'))->toHaveKey('MANHATTAN');
    expect($response->json('data.by_borough'))->toHaveKey('BROOKLYN');
});

test('complaints summary includes risk distribution when analysis exists', function () {
    $complaints = Complaint::factory()->count(3)->create();
    
    // Create analyses with different risk scores
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaints[0]->id,
        'risk_score' => 0.8, // High
    ]);
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaints[1]->id,
        'risk_score' => 0.5, // Medium
    ]);
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaints[2]->id,
        'risk_score' => 0.2, // Low
    ]);
    
    $response = $this->getJson('/api/complaints/summary');
    
    $response->assertStatus(200);
    
    $riskDistribution = $response->json('data.risk_distribution');
    expect($riskDistribution['high'])->toBe(1)
        ->and($riskDistribution['medium'])->toBe(1)
        ->and($riskDistribution['low'])->toBe(1);
});

test('complaints api requires authentication', function () {
    // Logout user
    $this->withoutMiddleware();
    
    $response = $this->getJson('/api/complaints');
    
    $response->assertStatus(401);
});

test('complaints index handles invalid filter values gracefully', function () {
    Complaint::factory()->count(3)->create();
    
    // Invalid borough
    $response = $this->getJson('/api/complaints?borough=INVALID');
    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
    
    // Invalid date format
    $response = $this->getJson('/api/complaints?date_from=invalid-date');
    $response->assertStatus(200); // Should still work, just ignore invalid date
});

test('complaints index validates per_page parameter', function () {
    Complaint::factory()->count(5)->create();
    
    // Too large per_page
    $response = $this->getJson('/api/complaints?per_page=1000');
    $response->assertStatus(200);
    expect($response->json('meta.per_page'))->toBeLessThanOrEqual(100); // Should be capped
    
    // Invalid per_page
    $response = $this->getJson('/api/complaints?per_page=invalid');
    $response->assertStatus(200); // Should use default
});

test('complaints index supports multiple filters simultaneously', function () {
    // Create specific complaint that matches all filters
    $complaint = Complaint::factory()->create([
        'borough' => 'MANHATTAN',
        'status' => Complaint::STATUS_OPEN,
        'complaint_type' => 'Noise - Residential',
        'submitted_at' => '2024-01-15 10:00:00',
    ]);
    
    // Create other complaints that don't match
    Complaint::factory()->create(['borough' => 'BROOKLYN']);
    Complaint::factory()->create(['status' => Complaint::STATUS_CLOSED]);
    
    $response = $this->getJson('/api/complaints?' . http_build_query([
        'borough' => 'MANHATTAN',
        'status' => Complaint::STATUS_OPEN,
        'type' => 'Noise',
        'date_from' => '2024-01-01',
        'date_to' => '2024-01-31',
    ]));
    
    $response->assertStatus(200);
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($complaint->id);
});

test('complaints show includes related actions when available', function () {
    $complaint = Complaint::factory()->create();
    
    // Add actions if the relationship exists
    if (method_exists($complaint, 'actions')) {
        $action = \App\Models\Action::factory()->create([
            'complaint_id' => $complaint->id,
        ]);
    }
    
    $response = $this->getJson("/api/complaints/{$complaint->id}");
    
    $response->assertStatus(200);
    
    // Check if actions are included in response if the relationship exists
    if (isset($response->json('data')['actions'])) {
        expect($response->json('data.actions'))->toBeArray();
    }
});

test('complaints api returns consistent timestamp format', function () {
    $complaint = Complaint::factory()->create([
        'submitted_at' => '2024-01-15 10:30:45',
    ]);
    
    $response = $this->getJson("/api/complaints/{$complaint->id}");
    
    $response->assertStatus(200);
    
    $submittedAt = $response->json('data.submitted_at');
    expect($submittedAt)->toMatch('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d{6}Z/'); // ISO 8601 format
});