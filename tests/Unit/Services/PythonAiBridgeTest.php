<?php

use App\Services\PythonAiBridge;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Mock configuration values
    config([
        'complaints.python.script_path' => '/fake/path/ai_analysis.py',
        'complaints.python.timeout' => 90,
        'complaints.python.max_output_length' => 10000,
        'services.openai.api_key' => 'test-key',
        'services.openai.organization' => 'test-org',
    ]);
});

test('python ai bridge constructor sets configuration correctly', function () {
    $bridge = new PythonAiBridge();
    
    // Access private properties using reflection for testing
    $reflection = new ReflectionClass($bridge);
    $scriptPath = $reflection->getProperty('scriptPath');
    $scriptPath->setAccessible(true);
    $timeout = $reflection->getProperty('timeout');
    $timeout->setAccessible(true);
    $maxOutputLength = $reflection->getProperty('maxOutputLength');
    $maxOutputLength->setAccessible(true);
    
    expect($scriptPath->getValue($bridge))->toBe('/fake/path/ai_analysis.py')
        ->and($timeout->getValue($bridge))->toBe(90)
        ->and($maxOutputLength->getValue($bridge))->toBe(10000);
});

test('analyzeComplaint returns valid analysis result', function () {
    $bridge = Mockery::mock(PythonAiBridge::class)->makePartial();
    
    $complaintData = [
        'id' => 1,
        'type' => 'Noise - Residential',
        'description' => 'Loud music from neighbor',
        'borough' => 'MANHATTAN',
    ];
    
    $mockAnalysisResult = [
        'summary' => 'AI analysis of noise complaint',
        'risk_score' => 0.6,
        'category' => 'Quality of Life',
        'tags' => ['noise', 'residential'],
    ];
    
    // Mock the Python execution
    $bridge->shouldReceive('callPythonScript')
        ->with('analyze_complaint', $complaintData)
        ->andReturn($mockAnalysisResult);
    
    $bridge->shouldAllowMockingProtectedMethods();
    
    $result = $bridge->analyzeComplaint($complaintData);
    
    expect($result)->toBe($mockAnalysisResult)
        ->and($result['summary'])->toBe('AI analysis of noise complaint')
        ->and($result['risk_score'])->toBe(0.6)
        ->and($result['category'])->toBe('Quality of Life')
        ->and($result['tags'])->toContain('noise');
});

test('analyzeComplaint creates fallback analysis on failure', function () {
    $bridge = new PythonAiBridge();
    
    // Create a mock to force an exception
    $processMock = Mockery::mock(Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andThrow(new Exception('Process failed'));
    
    // Use partial mock to override process creation
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    $complaintData = [
        'id' => 1,
        'type' => 'Water System',
        'description' => 'Water leak in basement',
        'borough' => 'BROOKLYN',
    ];
    
    Log::shouldReceive('info', 'error')->andReturn(null);
    
    $result = $bridgeMock->analyzeComplaint($complaintData);
    
    expect($result)->toBeArray()
        ->and($result['fallback'])->toBeTrue()
        ->and($result['summary'])->toContain('Water System')
        ->and($result['risk_score'])->toBeFloat()
        ->and($result['category'])->toBeString();
});

test('generateEmbedding returns valid embedding data', function () {
    $bridge = Mockery::mock(PythonAiBridge::class)->makePartial();
    
    $text = 'Test complaint text for embedding';
    
    $mockEmbeddingResult = [
        'embedding' => array_fill(0, 1536, 0.1),
        'model' => 'text-embedding-3-small',
        'dimension' => 1536,
    ];
    
    $bridge->shouldReceive('callPythonScript')
        ->with('create_embeddings', ['texts' => [$text]])
        ->andReturn([
            'data' => [
                'embeddings' => [$mockEmbeddingResult['embedding']],
                'model' => $mockEmbeddingResult['model'],
            ]
        ]);
    
    $bridge->shouldAllowMockingProtectedMethods();
    
    $result = $bridge->generateEmbedding($text);
    
    expect($result)->toBeArray()
        ->and($result['embedding'])->toBeArray()
        ->and($result['model'])->toBe('text-embedding-3-small')
        ->and($result['dimension'])->toBe(1536)
        ->and(count($result['embedding']))->toBe(1536);
});

test('generateEmbedding handles json parsing from python output', function () {
    $bridge = new PythonAiBridge();
    
    // Create a mock process that returns mixed output (logs + JSON)
    $processMock = Mockery::mock(Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andReturn(null);
    $processMock->shouldReceive('isSuccessful')->andReturn(true);
    
    // Mock output with logs and JSON
    $mixedOutput = "INFO: Starting embedding generation\nWARNING: Some warning\n{\"data\":{\"embeddings\":[[0.1,0.2,0.3]],\"model\":\"test-model\"}}\nINFO: Embedding complete";
    $processMock->shouldReceive('getOutput')->andReturn($mixedOutput);
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    $result = $bridgeMock->generateEmbedding('test text');
    
    expect($result)->toBeArray()
        ->and($result['embedding'])->toBeArray()
        ->and($result['embedding'])->toHaveCount(3)
        ->and($result['model'])->toBe('test-model');
});

test('testConnection returns health check status', function () {
    $bridge = Mockery::mock(PythonAiBridge::class)->makePartial();
    
    $mockHealthResult = [
        'status' => 'healthy',
        'python_version' => '3.9.0',
        'dependencies' => ['openai', 'langchain'],
    ];
    
    $bridge->shouldReceive('callPythonScript')
        ->with('health_check', [])
        ->andReturn($mockHealthResult);
    
    $bridge->shouldAllowMockingProtectedMethods();
    
    $result = $bridge->testConnection();
    
    expect($result)->toBe($mockHealthResult)
        ->and($result['status'])->toBe('healthy');
});

test('testConnection handles failures gracefully', function () {
    $bridge = new PythonAiBridge();
    
    $processMock = Mockery::mock(Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andThrow(new Exception('Connection failed'));
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    Log::shouldReceive('info', 'error')->andReturn(null);
    
    $result = $bridgeMock->testConnection();
    
    expect($result)->toBeArray()
        ->and($result['status'])->toBe('error')
        ->and($result['message'])->toContain('Connection failed');
});

test('validateAnalysisResult sanitizes risk score', function () {
    $bridge = new PythonAiBridge();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($bridge);
    $method = $reflection->getMethod('validateAnalysisResult');
    $method->setAccessible(true);
    
    $complaintData = ['type' => 'Test'];
    
    // Test risk score clamping
    $result = $method->invoke($bridge, ['risk_score' => 1.5], $complaintData);
    expect($result['risk_score'])->toBe(1.0);
    
    $result = $method->invoke($bridge, ['risk_score' => -0.5], $complaintData);
    expect($result['risk_score'])->toBe(0.0);
    
    $result = $method->invoke($bridge, ['risk_score' => 0.75], $complaintData);
    expect($result['risk_score'])->toBe(0.75);
});

test('estimateRiskScore provides reasonable fallback scores', function () {
    $bridge = new PythonAiBridge();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($bridge);
    $method = $reflection->getMethod('estimateRiskScore');
    $method->setAccessible(true);
    
    // High risk types
    $gasLeak = $method->invoke($bridge, ['type' => 'Gas Leak']);
    expect($gasLeak)->toBe(0.85);
    
    $structural = $method->invoke($bridge, ['type' => 'Structural Damage']);
    expect($structural)->toBe(0.85);
    
    // Medium-high risk
    $water = $method->invoke($bridge, ['type' => 'Water System']);
    expect($water)->toBe(0.65);
    
    // Medium risk
    $street = $method->invoke($bridge, ['type' => 'Street Condition']);
    expect($street)->toBe(0.45);
    
    // Low risk
    $noise = $method->invoke($bridge, ['type' => 'Noise Complaint']);
    expect($noise)->toBe(0.25);
    
    // Unknown type defaults to low
    $unknown = $method->invoke($bridge, ['type' => 'Unknown Type']);
    expect($unknown)->toBe(0.25);
});

test('categorizeComplaint provides logical categories', function () {
    $bridge = new PythonAiBridge();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($bridge);
    $method = $reflection->getMethod('categorizeComplaint');
    $method->setAccessible(true);
    
    // Test various complaint types
    expect($method->invoke($bridge, ['type' => 'Noise - Residential']))->toBe('Quality of Life');
    expect($method->invoke($bridge, ['type' => 'Illegal Parking']))->toBe('Transportation');
    expect($method->invoke($bridge, ['type' => 'Water System']))->toBe('Infrastructure');
    expect($method->invoke($bridge, ['type' => 'Street Condition']))->toBe('Infrastructure');
    expect($method->invoke($bridge, ['type' => 'Sanitation']))->toBe('Public Health');
    expect($method->invoke($bridge, ['type' => 'Animal Issue']))->toBe('Public Health');
    expect($method->invoke($bridge, ['type' => 'Unknown Type']))->toBe('General');
});

test('generateFallbackTags creates relevant tags', function () {
    $bridge = new PythonAiBridge();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($bridge);
    $method = $reflection->getMethod('generateFallbackTags');
    $method->setAccessible(true);
    
    $complaintData = [
        'borough' => 'MANHATTAN',
        'agency' => 'NYPD',
        'type' => 'Noise - Water System Street',
    ];
    
    $tags = $method->invoke($bridge, $complaintData);
    
    expect($tags)->toBeArray()
        ->and($tags)->toContain('manhattan')
        ->and($tags)->toContain('nypd')
        ->and($tags)->toContain('noise')
        ->and($tags)->toContain('water')
        ->and($tags)->toContain('street');
    
    // Should be unique
    expect(array_unique($tags))->toHaveCount(count($tags));
});

test('constructor handles missing configuration gracefully', function () {
    // Clear config
    config([
        'complaints.python.script_path' => null,
        'complaints.python.timeout' => null,
        'complaints.python.max_output_length' => null,
    ]);
    
    $bridge = new PythonAiBridge();
    
    // Should use defaults
    $reflection = new ReflectionClass($bridge);
    $timeout = $reflection->getProperty('timeout');
    $timeout->setAccessible(true);
    $maxOutputLength = $reflection->getProperty('maxOutputLength');
    $maxOutputLength->setAccessible(true);
    
    expect($timeout->getValue($bridge))->toBe(90)
        ->and($maxOutputLength->getValue($bridge))->toBe(10000);
});

test('chat returns valid response for successful query', function () {
    $bridge = Mockery::mock(PythonAiBridge::class)->makePartial();
    
    $chatData = [
        'session_id' => 'test-session-123',
        'message' => 'What are the most common complaint types?',
        'complaint_data' => [
            ['type' => 'Noise', 'count' => 50],
            ['type' => 'Water', 'count' => 30],
        ],
    ];
    
    $mockChatResult = [
        'response' => 'Based on the data, Noise complaints (50) and Water issues (30) are the most common types.',
        'metadata' => [
            'model' => 'gpt-4',
            'tokens_used' => 150,
            'processing_time' => 1.2,
        ],
    ];
    
    // Mock the Python execution
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andReturn(null);
    $processMock->shouldReceive('isSuccessful')->andReturn(true);
    $processMock->shouldReceive('getOutput')->andReturn(json_encode($mockChatResult));
    
    $bridge->shouldReceive('createProcess')->andReturn($processMock);
    
    $result = $bridge->chat($chatData);
    
    expect($result)->toBeArray()
        ->and($result['response'])->toBe('Based on the data, Noise complaints (50) and Water issues (30) are the most common types.')
        ->and($result['fallback'])->toBeFalse()
        ->and($result['metadata'])->toBeArray()
        ->and($result['metadata']['model'])->toBe('gpt-4');
});

test('chat handles process failure with fallback response', function () {
    $bridge = new PythonAiBridge();
    
    $chatData = [
        'session_id' => 'test-session-456',
        'message' => 'Tell me about water complaints',
        'complaint_data' => [],
    ];
    
    // Mock process that fails
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andThrow(new \Symfony\Component\Process\Exception\ProcessFailedException($processMock));
    $processMock->shouldReceive('getErrorOutput')->andReturn('Python script error');
    $processMock->shouldReceive('getExitCode')->andReturn(1);
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    Log::shouldReceive('info', 'error')->andReturn(null);
    
    $result = $bridgeMock->chat($chatData);
    
    expect($result)->toBeArray()
        ->and($result['response'])->toContain('technical difficulties')
        ->and($result['fallback'])->toBeTrue()
        ->and($result['error'])->toBe('Process execution failed');
});

test('chat handles unexpected exception with fallback response', function () {
    $bridge = new PythonAiBridge();
    
    $chatData = [
        'session_id' => 'test-session-789',
        'message' => 'How are complaints distributed by borough?',
    ];
    
    // Mock process that throws unexpected exception
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andThrow(new \Exception('Unexpected system error'));
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    Log::shouldReceive('info', 'error')->andReturn(null);
    
    $result = $bridgeMock->chat($chatData);
    
    expect($result)->toBeArray()
        ->and($result['response'])->toContain('unable to process your request')
        ->and($result['fallback'])->toBeTrue()
        ->and($result['error'])->toContain('Unexpected error');
});

test('chat handles invalid json response gracefully', function () {
    $bridge = new PythonAiBridge();
    
    $chatData = [
        'session_id' => 'test-session-json',
        'message' => 'Simple question',
    ];
    
    // Mock process that returns invalid JSON
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andReturn(null);
    $processMock->shouldReceive('isSuccessful')->andReturn(true);
    $processMock->shouldReceive('getOutput')->andReturn('Invalid JSON response from Python');
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    Log::shouldReceive('info', 'error')->andReturn(null);
    
    $result = $bridgeMock->chat($chatData);
    
    expect($result)->toBeArray()
        ->and($result['response'])->toContain('unable to process your request')
        ->and($result['fallback'])->toBeTrue()
        ->and($result['error'])->toContain('Unexpected error');
});

test('chat parses mixed output with logs and json correctly', function () {
    $bridge = new PythonAiBridge();
    
    $chatData = [
        'session_id' => 'test-mixed-output',
        'message' => 'Test query with mixed output',
    ];
    
    $mockResponse = [
        'response' => 'This is the AI response after processing.',
        'metadata' => ['processing_time' => 0.8],
    ];
    
    // Mixed output with logs and JSON (similar to embedding generation)
    $mixedOutput = "INFO: Starting chat processing\nDEBUG: Loading context\n" . json_encode($mockResponse) . "\nINFO: Chat complete";
    
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andReturn(null);
    $processMock->shouldReceive('isSuccessful')->andReturn(true);
    $processMock->shouldReceive('getOutput')->andReturn($mixedOutput);
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    Log::shouldReceive('info', 'error')->andReturn(null);
    
    $result = $bridgeMock->chat($chatData);
    
    expect($result)->toBeArray()
        ->and($result['response'])->toBe('This is the AI response after processing.')
        ->and($result['fallback'])->toBeFalse()
        ->and($result['metadata']['processing_time'])->toBe(0.8);
});

test('chat accepts and processes various input formats', function () {
    $bridge = Mockery::mock(PythonAiBridge::class)->makePartial();
    
    // Test with minimal data
    $minimalChatData = [
        'message' => 'Simple question',
    ];
    
    // Test with full data
    $fullChatData = [
        'session_id' => 'full-session',
        'message' => 'Complex question with context',
        'complaint_data' => [
            ['id' => 1, 'type' => 'Noise'],
            ['id' => 2, 'type' => 'Water'],
        ],
        'user_context' => ['borough' => 'MANHATTAN'],
    ];
    
    $mockResponse = ['response' => 'Test response', 'metadata' => []];
    
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andReturn(null);
    $processMock->shouldReceive('isSuccessful')->andReturn(true);
    $processMock->shouldReceive('getOutput')->andReturn(json_encode($mockResponse));
    
    $bridge->shouldReceive('createProcess')->andReturn($processMock);
    
    // Test both input formats
    $minimalResult = $bridge->chat($minimalChatData);
    $fullResult = $bridge->chat($fullChatData);
    
    expect($minimalResult['response'])->toBe('Test response')
        ->and($minimalResult['fallback'])->toBeFalse()
        ->and($fullResult['response'])->toBe('Test response')
        ->and($fullResult['fallback'])->toBeFalse();
});

test('chat logs appropriate information for debugging', function () {
    $bridge = new PythonAiBridge();
    
    $chatData = [
        'session_id' => 'logging-test',
        'message' => 'Test message for logging',
        'complaint_data' => ['test' => 'data'],
    ];
    
    $processMock = Mockery::mock(\Symfony\Component\Process\Process::class);
    $processMock->shouldReceive('setTimeout')->andReturnSelf();
    $processMock->shouldReceive('setEnv')->andReturnSelf();
    $processMock->shouldReceive('run')->andReturn(null);
    $processMock->shouldReceive('isSuccessful')->andReturn(true);
    $processMock->shouldReceive('getOutput')->andReturn('{"response": "test"}');
    
    $bridgeMock = Mockery::mock(PythonAiBridge::class)->makePartial();
    $bridgeMock->shouldReceive('createProcess')->andReturn($processMock);
    
    // Verify logging calls
    Log::shouldReceive('info')
        ->with('Processing chat query via Python AI bridge', Mockery::on(function ($context) {
            return isset($context['session_id'])
                && isset($context['message_length'])
                && isset($context['has_context']);
        }))
        ->once();
    
    Log::shouldReceive('info')
        ->with('Chat query completed successfully', Mockery::type('array'))
        ->once();
    
    $bridgeMock->chat($chatData);
    
    // Verified by Mockery expectations
    expect(true)->toBeTrue();
});