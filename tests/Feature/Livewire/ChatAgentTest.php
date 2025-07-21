<?php

use App\Livewire\Dashboard\ChatAgent;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Services\HybridSearchService;
use App\Services\PythonAiBridge;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = createUser([], true); // Create and login user
});

test('chat agent renders correctly', function () {
    Livewire::test(ChatAgent::class)
        ->assertStatus(200)
        ->assertSee('Hello! I\'m your LaraCity AI assistant');
});

test('chat agent initializes with welcome message', function () {
    $component = Livewire::test(ChatAgent::class);
    
    $messages = $component->get('messages');
    
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['role'])->toBe('assistant')
        ->and($messages[0]['content'])->toContain('LaraCity AI assistant');
});

test('chat agent generates unique session id', function () {
    $component1 = Livewire::test(ChatAgent::class);
    $component2 = Livewire::test(ChatAgent::class);
    
    expect($component1->get('sessionId'))
        ->not->toBe($component2->get('sessionId'));
});

test('sendMessage validates user input', function () {
    Livewire::test(ChatAgent::class)
        ->set('userMessage', '')
        ->call('sendMessage')
        ->assertHasErrors(['userMessage' => 'required']);
    
    Livewire::test(ChatAgent::class)
        ->set('userMessage', str_repeat('a', 1001))
        ->call('sendMessage')
        ->assertHasErrors(['userMessage' => 'max']);
});

test('sendMessage adds user message to conversation', function () {
    $message = 'What are the most common complaint types?';
    
    $component = Livewire::test(ChatAgent::class)
        ->set('userMessage', $message)
        ->call('sendMessage');
    
    $messages = $component->get('messages');
    
    // Should have welcome + user message + assistant response placeholder
    expect($messages)->toHaveCount(3)
        ->and($messages[1]['role'])->toBe('user')
        ->and($messages[1]['content'])->toBe($message)
        ->and($messages[2]['role'])->toBe('assistant');
});

test('sendMessage clears input and sets processing state', function () {
    Livewire::test(ChatAgent::class)
        ->set('userMessage', 'Test message')
        ->call('sendMessage')
        ->assertSet('userMessage', '')
        ->assertSet('isProcessing', false); // Processing completes by end of test
});

test('isStatisticalQuery identifies statistical queries correctly', function () {
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('isStatisticalQuery');
    $method->setAccessible(true);
    
    // Statistical queries
    expect($method->invoke($component, 'What are the most common complaint types?'))->toBeTrue();
    expect($method->invoke($component, 'How many complaints are there?'))->toBeTrue();
    expect($method->invoke($component, 'Show me statistics'))->toBeTrue();
    expect($method->invoke($component, 'What is the total count?'))->toBeTrue();
    expect($method->invoke($component, 'Percentage breakdown'))->toBeTrue();
    
    // Non-statistical queries
    expect($method->invoke($component, 'Find noise complaints'))->toBeFalse();
    expect($method->invoke($component, 'Hello there'))->toBeFalse();
    expect($method->invoke($component, 'Search for water leaks'))->toBeFalse();
});

test('isComplaintQuery identifies search queries correctly', function () {
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('isComplaintQuery');
    $method->setAccessible(true);
    
    // Complaint search queries
    expect($method->invoke($component, 'Search for noise complaints'))->toBeTrue();
    expect($method->invoke($component, 'Find water leaks'))->toBeTrue();
    expect($method->invoke($component, 'Show me complaints about graffiti'))->toBeTrue();
    expect($method->invoke($component, 'List complaints'))->toBeTrue();
    
    // Non-search queries
    expect($method->invoke($component, 'How many complaints?'))->toBeFalse();
    expect($method->invoke($component, 'Hello assistant'))->toBeFalse();
});

test('handleStatisticalQuery returns most common complaint types', function () {
    // Create test data
    Complaint::factory()->count(5)->create(['complaint_type' => 'Noise - Residential']);
    Complaint::factory()->count(3)->create(['complaint_type' => 'Water System']);
    Complaint::factory()->count(2)->create(['complaint_type' => 'Street Condition']);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleStatisticalQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'What are the most common complaint types?');
    
    expect($response)->toContain('Most Common Complaint Types')
        ->and($response)->toContain('Noise - Residential')
        ->and($response)->toContain('5 complaints')
        ->and($response)->toContain('Water System')
        ->and($response)->toContain('3 complaints')
        ->and($response)->toContain('Total Complaints: 10');
});

test('handleStatisticalQuery returns complaints by borough', function () {
    // Create test data
    Complaint::factory()->count(4)->create(['borough' => 'MANHATTAN']);
    Complaint::factory()->count(3)->create(['borough' => 'BROOKLYN']);
    Complaint::factory()->count(2)->create(['borough' => 'QUEENS']);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleStatisticalQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'How many complaints by borough?');
    
    expect($response)->toContain('Complaints by Borough')
        ->and($response)->toContain('MANHATTAN')
        ->and($response)->toContain('4 complaints')
        ->and($response)->toContain('BROOKLYN')
        ->and($response)->toContain('3 complaints');
});

test('handleStatisticalQuery returns risk level distribution', function () {
    // Create complaints with analysis
    $complaints = Complaint::factory()->count(5)->create();
    
    // High risk
    ComplaintAnalysis::factory()->count(2)->create([
        'complaint_id' => $complaints[0]->id,
        'risk_score' => 0.8,
    ]);
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaints[1]->id,
        'risk_score' => 0.9,
    ]);
    
    // Medium risk
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaints[2]->id,
        'risk_score' => 0.5,
    ]);
    
    // Low risk
    ComplaintAnalysis::factory()->create([
        'complaint_id' => $complaints[3]->id,
        'risk_score' => 0.2,
    ]);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleStatisticalQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'Risk distribution breakdown');
    
    expect($response)->toContain('Risk Level Distribution')
        ->and($response)->toContain('High Risk')
        ->and($response)->toContain('Medium Risk')
        ->and($response)->toContain('Low Risk')
        ->and($response)->toContain('Average Risk Score');
});

test('handleComplaintQuery uses hybrid search service', function () {
    $complaint = Complaint::factory()->create([
        'complaint_type' => 'Noise - Residential',
        'descriptor' => 'Loud music from party',
    ]);
    
    // Mock the search service
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->with('noise complaints', [], [
            'limit' => 5,
            'similarity_threshold' => 0.6
        ])
        ->andReturn([
            'results' => [
                [
                    'complaint' => [
                        'complaint_number' => 'TEST123',
                        'type' => 'Noise - Residential',
                        'borough' => 'MANHATTAN',
                        'status' => 'Open',
                        'description' => 'Loud music from party',
                        'analysis' => [
                            'risk_score' => 0.6,
                        ]
                    ]
                ]
            ]
        ]);
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleComplaintQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'noise complaints');
    
    expect($response)->toContain('I found 1 relevant complaints')
        ->and($response)->toContain('TEST123')
        ->and($response)->toContain('Noise - Residential')
        ->and($response)->toContain('MANHATTAN');
});

test('handleComplaintQuery handles no results', function () {
    // Mock empty search results
    $searchService = Mockery::mock(HybridSearchService::class);
    $searchService->shouldReceive('search')
        ->once()
        ->andReturn(['results' => []]);
    
    app()->instance(HybridSearchService::class, $searchService);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleComplaintQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'nonexistent complaints');
    
    expect($response)->toContain('I couldn\'t find any complaints matching your query');
});

test('handleGeneralQuery uses python ai bridge', function () {
    // Create some recent complaints for context
    Complaint::factory()->count(3)->create();
    
    // Mock the AI bridge
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $pythonBridge->shouldReceive('chat')
        ->once()
        ->with(Mockery::on(function ($data) {
            return isset($data['message'])
                && isset($data['session_id'])
                && isset($data['complaint_data']);
        }))
        ->andReturn([
            'success' => true,
            'data' => [
                'response' => 'This is an AI-generated response about LaraCity complaints.'
            ]
        ]);
    
    app()->instance(PythonAiBridge::class, $pythonBridge);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleGeneralQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'Tell me about this system');
    
    expect($response)->toBe('This is an AI-generated response about LaraCity complaints.');
});

test('handleGeneralQuery provides fallback when ai fails', function () {
    // Mock failed AI bridge
    $pythonBridge = Mockery::mock(PythonAiBridge::class);
    $pythonBridge->shouldReceive('chat')
        ->once()
        ->andThrow(new Exception('AI service unavailable'));
    
    app()->instance(PythonAiBridge::class, $pythonBridge);
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('handleGeneralQuery');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, 'General question');
    
    expect($response)->toContain('I\'m here to help you with LaraCity complaints data');
});

test('clearChat resets conversation', function () {
    $component = Livewire::test(ChatAgent::class)
        ->set('userMessage', 'Test message')
        ->call('sendMessage');
    
    // Should have multiple messages
    expect($component->get('messages'))->toHaveCount(3);
    
    $originalSessionId = $component->get('sessionId');
    
    $component->call('clearChat');
    
    // Should reset to single welcome message
    expect($component->get('messages'))->toHaveCount(1)
        ->and($component->get('messages')[0]['content'])->toContain('Chat history cleared')
        ->and($component->get('sessionId'))->not->toBe($originalSessionId);
});

test('formatComplaintResults handles array complaint data', function () {
    $results = [
        'results' => [
            [
                'complaint' => [
                    'complaint_number' => 'NYC123',
                    'type' => 'Water System',
                    'borough' => 'MANHATTAN',
                    'status' => 'Open',
                    'description' => 'Broken pipe',
                    'analysis' => [
                        'risk_score' => 0.8,
                    ]
                ]
            ]
        ]
    ];
    
    $component = new ChatAgent();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('formatComplaintResults');
    $method->setAccessible(true);
    
    $response = $method->invoke($component, $results);
    
    expect($response)->toContain('I found 1 relevant complaints')
        ->and($response)->toContain('NYC123')
        ->and($response)->toContain('Water System')
        ->and($response)->toContain('Risk Level: High');
});

test('component handles processing state correctly', function () {
    Livewire::test(ChatAgent::class)
        ->assertSet('isProcessing', false)
        ->set('userMessage', 'Test message')
        ->call('sendMessage')
        ->assertSet('isProcessing', false); // Should be false after processing
});

test('component properties are correctly typed', function () {
    $component = new ChatAgent();
    
    expect($component->messages)->toBeArray()
        ->and($component->userMessage)->toBeString()
        ->and($component->isProcessing)->toBeBool()
        ->and($component->sessionId)->toBeString();
});

test('getMessagesProperty returns messages array', function () {
    $component = Livewire::test(ChatAgent::class);
    
    $messages = $component->call('getMessagesProperty');
    
    expect($messages)->toBeArray()
        ->and($messages)->toHaveCount(1);
});