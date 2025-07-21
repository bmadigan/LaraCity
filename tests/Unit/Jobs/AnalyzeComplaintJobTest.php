<?php

use App\Jobs\AnalyzeComplaintJob;
use App\Jobs\FlagComplaintJob;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Services\PythonAiBridge;
use App\Services\VectorEmbeddingService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    
    // Mock configuration
    config([
        'complaints.jobs.analyze_tries' => 3,
        'complaints.jobs.analyze_timeout' => 120,
        'complaints.escalate_threshold' => 0.7,
        'complaints.queues.ai_analysis' => 'ai-analysis',
        'complaints.queues.escalation' => 'escalation',
    ]);
});

test('job constructor sets correct configuration', function () {
    $complaint = Complaint::factory()->create();
    $job = new AnalyzeComplaintJob($complaint);
    
    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->complaint->id)->toBe($complaint->id);
});

test('job skips analysis if already exists', function () {
    $complaint = Complaint::factory()->create();
    
    // Create existing analysis
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaint->id,
    ]);
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    // Should not call any analysis methods
    $pythonBridge->shouldNotReceive('analyzeComplaint');
    $embeddingService->shouldNotReceive('generateEmbedding');
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Verify no additional analysis was created
    expect(ComplaintAnalysis::where('complaint_id', $complaint->id)->count())
        ->toBe(1);
});

test('job creates analysis from python bridge result', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Water System',
        'descriptor' => 'Broken pipe flooding basement',
        'borough' => 'MANHATTAN',
    ]);
    
    $analysisResult = [
        'summary' => 'High priority water infrastructure issue requiring immediate attention',
        'risk_score' => 0.85,
        'category' => 'Infrastructure',
        'tags' => ['water', 'infrastructure', 'urgent'],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')
        ->once()
        ->with(Mockery::on(function ($data) use ($complaint) {
            return $data['id'] === $complaint->id
                && $data['type'] === $complaint->complaint_type
                && $data['description'] === $complaint->descriptor;
        }))
        ->andReturn($analysisResult);
    
    // Mock embedding generation
    $embeddingService->shouldReceive('generateEmbedding')
        ->twice() // Once for complaint, once for analysis
        ->andReturn(Mockery::mock(\App\Models\DocumentEmbedding::class));
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Verify analysis was created
    $analysis = ComplaintAnalysis::where('complaint_id', $complaint->id)->first();
    expect($analysis)->not->toBeNull()
        ->and($analysis->summary)->toBe($analysisResult['summary'])
        ->and((float) $analysis->risk_score)->toBe($analysisResult['risk_score'])
        ->and($analysis->category)->toBe($analysisResult['category'])
        ->and($analysis->tags)->toBe($analysisResult['tags']);
});

test('job generates embeddings for complaint and analysis', function () {
    $complaint = Complaint::factory()->create();
    
    $analysisResult = [
        'summary' => 'Test analysis summary',
        'risk_score' => 0.5,
        'category' => 'General',
        'tags' => [],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($analysisResult);
    
    // Should generate embedding for the complaint
    $embeddingService->shouldReceive('generateEmbedding')
        ->with(Mockery::type(Complaint::class))
        ->once()
        ->andReturn(Mockery::mock(\App\Models\DocumentEmbedding::class));
    
    // Should generate embedding for the analysis
    $embeddingService->shouldReceive('generateEmbedding')
        ->with(Mockery::type(ComplaintAnalysis::class))
        ->once()
        ->andReturn(Mockery::mock(\App\Models\DocumentEmbedding::class));
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Embeddings should have been generated
    expect(true)->toBeTrue(); // Verified by Mockery expectations
});

test('job handles embedding generation failures gracefully', function () {
    $complaint = Complaint::factory()->create();
    
    $analysisResult = [
        'summary' => 'Test analysis',
        'risk_score' => 0.3,
        'category' => 'General',
        'tags' => [],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($analysisResult);
    
    // Mock embedding failures
    $embeddingService->shouldReceive('generateEmbedding')
        ->twice()
        ->andThrow(new Exception('Embedding generation failed'));
    
    // Job should not fail despite embedding errors
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Analysis should still be created
    $analysis = ComplaintAnalysis::where('complaint_id', $complaint->id)->first();
    expect($analysis)->not->toBeNull();
});

test('job dispatches escalation for high risk complaints', function () {
    $complaint = Complaint::factory()->create();
    
    $highRiskAnalysis = [
        'summary' => 'Critical infrastructure failure',
        'risk_score' => 0.9, // Above 0.7 threshold
        'category' => 'Emergency',
        'tags' => ['critical', 'emergency'],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($highRiskAnalysis);
    $embeddingService->shouldReceive('generateEmbedding')->andReturn(null);
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Verify escalation job was dispatched
    Queue::assertPushed(FlagComplaintJob::class, function ($job) use ($complaint) {
        return $job->complaint->id === $complaint->id;
    });
});

test('job does not dispatch escalation for low risk complaints', function () {
    $complaint = Complaint::factory()->create();
    
    $lowRiskAnalysis = [
        'summary' => 'Minor quality of life issue',
        'risk_score' => 0.3, // Below 0.7 threshold
        'category' => 'Quality of Life',
        'tags' => ['minor'],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($lowRiskAnalysis);
    $embeddingService->shouldReceive('generateEmbedding')->andReturn(null);
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Verify no escalation job was dispatched
    Queue::assertNotPushed(FlagComplaintJob::class);
});

test('job uses correct escalation threshold from config', function () {
    // Set custom threshold
    config(['complaints.escalate_threshold' => 0.8]);
    
    $complaint = Complaint::factory()->create();
    
    $analysisResult = [
        'summary' => 'Medium risk issue',
        'risk_score' => 0.75, // Below custom 0.8 threshold
        'category' => 'Infrastructure',
        'tags' => ['medium'],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($analysisResult);
    $embeddingService->shouldReceive('generateEmbedding')->andReturn(null);
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Should not escalate with 0.75 score when threshold is 0.8
    Queue::assertNotPushed(FlagComplaintJob::class);
});

test('job handles analysis failure and throws exception', function () {
    $complaint = Complaint::factory()->create();
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')
        ->andThrow(new Exception('AI analysis failed'));
    
    $job = new AnalyzeComplaintJob($complaint);
    
    expect(fn() => $job->handle($pythonBridge, $embeddingService))
        ->toThrow(Exception::class, 'AI analysis failed');
    
    // No analysis should be created
    expect(ComplaintAnalysis::where('complaint_id', $complaint->id)->count())
        ->toBe(0);
});

test('job creates analysis with default values when AI returns minimal data', function () {
    $complaint = Complaint::factory()->create();
    
    // Minimal AI response
    $minimalResult = [
        'summary' => null,
        'risk_score' => null,
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($minimalResult);
    $embeddingService->shouldReceive('generateEmbedding')->andReturn(null);
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    $analysis = ComplaintAnalysis::where('complaint_id', $complaint->id)->first();
    expect($analysis)->not->toBeNull()
        ->and($analysis->summary)->toBe('Analysis completed via AI bridge')
        ->and((float) $analysis->risk_score)->toBe(0.0)
        ->and($analysis->category)->toBe('General')
        ->and($analysis->tags)->toBe([]);
});

test('job handles empty analysis summary correctly', function () {
    $complaint = Complaint::factory()->create();
    
    $analysisResult = [
        'summary' => '', // Empty summary
        'risk_score' => 0.4,
        'category' => 'General',
        'tags' => ['test'],
    ];
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')->andReturn($analysisResult);
    
    // Should only generate embedding for complaint (not analysis due to empty summary)
    $embeddingService->shouldReceive('generateEmbedding')
        ->with(Mockery::type(Complaint::class))
        ->once()
        ->andReturn(null);
    
    $embeddingService->shouldNotReceive('generateEmbedding')
        ->with(Mockery::type(ComplaintAnalysis::class));
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Analysis should still be created
    expect(ComplaintAnalysis::where('complaint_id', $complaint->id)->count())
        ->toBe(1);
});

test('failed method logs error information', function () {
    $complaint = Complaint::factory()->create();
    $job = new AnalyzeComplaintJob($complaint);
    
    $exception = new Exception('Test failure');
    
    // Mock Log facade
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('AnalyzeComplaintJob failed permanently', [
            'complaint_id' => $complaint->id,
            'exception' => 'Test failure',
        ]);
    
    $job->failed($exception);
    
    // Verified by Mockery expectation
    expect(true)->toBeTrue();
});

test('job is configured for correct queue', function () {
    $complaint = Complaint::factory()->create();
    $job = new AnalyzeComplaintJob($complaint);
    
    // Use reflection to access queue property
    $reflection = new ReflectionClass($job);
    $property = $reflection->getProperty('queue');
    $property->setAccessible(true);
    
    expect($property->getValue($job))->toBe('ai-analysis');
});

test('job prepares correct data structure for python bridge', function () {
    $complaint = Complaint::factory()->create([
        'complaint_number' => 'TEST123',
        'complaint_type' => 'Water System',
        'descriptor' => 'Broken pipe',
        'agency' => 'DEP',
        'borough' => 'MANHATTAN',
        'incident_address' => '123 Main St',
        'status' => 'Open',
        'submitted_at' => '2024-01-15 10:30:00',
    ]);
    
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $embeddingService = Mockery::mock(VectorEmbeddingService::class);
    
    $pythonBridge->shouldReceive('analyzeComplaint')
        ->once()
        ->with(Mockery::on(function ($data) use ($complaint) {
            return $data['id'] === $complaint->id
                && $data['complaint_number'] === 'TEST123'
                && $data['type'] === 'Water System'
                && $data['description'] === 'Broken pipe'
                && $data['agency'] === 'DEP'
                && $data['borough'] === 'MANHATTAN'
                && $data['address'] === '123 Main St'
                && $data['status'] === 'Open'
                && isset($data['submitted_at']);
        }))
        ->andReturn([
            'summary' => 'test',
            'risk_score' => 0.5,
            'category' => 'General',
            'tags' => [],
        ]);
    
    $embeddingService->shouldReceive('generateEmbedding')->andReturn(null);
    
    $job = new AnalyzeComplaintJob($complaint);
    $job->handle($pythonBridge, $embeddingService);
    
    // Verified by Mockery expectations
    expect(true)->toBeTrue();
});