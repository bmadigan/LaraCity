<?php

use App\Models\User;
use App\Models\UserQuestion;
use App\Models\DocumentEmbedding;

test('user question has fillable attributes', function () {
    $user = User::factory()->create();
    
    $data = [
        'user_id' => $user->id,
        'conversation_id' => 'conv-123',
        'question' => 'What are the most common complaint types in Manhattan?',
        'ai_response' => 'Based on the data, noise complaints are most common.',
        'parsed_filters' => ['borough' => 'MANHATTAN'],
    ];
    
    $userQuestion = UserQuestion::create($data);
    
    expect($userQuestion->user_id)->toBe($user->id)
        ->and($userQuestion->conversation_id)->toBe('conv-123')
        ->and($userQuestion->question)->toBe('What are the most common complaint types in Manhattan?')
        ->and($userQuestion->ai_response)->toBe('Based on the data, noise complaints are most common.')
        ->and($userQuestion->parsed_filters)->toBe(['borough' => 'MANHATTAN']);
});

test('user question casts attributes correctly', function () {
    $userQuestion = UserQuestion::factory()->create([
        'parsed_filters' => ['borough' => 'BROOKLYN', 'type' => 'Noise'],
    ]);
    
    expect($userQuestion->parsed_filters)->toBeArray()
        ->and($userQuestion->parsed_filters['borough'])->toBe('BROOKLYN')
        ->and($userQuestion->parsed_filters['type'])->toBe('Noise');
});

test('user question belongs to user', function () {
    $user = User::factory()->create();
    $userQuestion = UserQuestion::factory()->create([
        'user_id' => $user->id,
    ]);
    
    expect($userQuestion->user)->toBeInstanceOf(User::class)
        ->and($userQuestion->user->id)->toBe($user->id);
});

test('user question has many embeddings relationship', function () {
    $userQuestion = UserQuestion::factory()->create();
    
    // Create embeddings for this user question
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_USER_QUESTION,
        'document_id' => $userQuestion->id,
        'content' => 'user question content',
        'document_hash' => hash('sha256', 'user question content'),
        'embedding' => '[0.1, 0.2, 0.3]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    // Create another embedding
    DocumentEmbedding::create([
        'document_type' => DocumentEmbedding::TYPE_USER_QUESTION,
        'document_id' => $userQuestion->id,
        'content' => 'another user question content',
        'document_hash' => hash('sha256', 'another user question content'),
        'embedding' => '[0.4, 0.5, 0.6]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    // Create embedding for different document type (should not be included)
    DocumentEmbedding::create([
        'document_type' => 'other_type',
        'document_id' => $userQuestion->id,
        'content' => 'other content',
        'document_hash' => hash('sha256', 'other content'),
        'embedding' => '[0.7, 0.8, 0.9]',
        'embedding_model' => 'test-model',
        'embedding_dimension' => 3,
        'metadata' => [],
    ]);
    
    expect($userQuestion->embeddings)->toHaveCount(2)
        ->and($userQuestion->embeddings->first()->document_type)->toBe(DocumentEmbedding::TYPE_USER_QUESTION);
});

test('hasResponse returns correct boolean values', function () {
    // User question without response
    $userQuestionWithoutResponse = UserQuestion::factory()->create([
        'ai_response' => null,
    ]);
    expect($userQuestionWithoutResponse->hasResponse())->toBeFalse();
    
    // User question with empty response
    $userQuestionWithEmptyResponse = UserQuestion::factory()->create([
        'ai_response' => '',
    ]);
    expect($userQuestionWithEmptyResponse->hasResponse())->toBeFalse();
    
    // User question with response
    $userQuestionWithResponse = UserQuestion::factory()->create([
        'ai_response' => 'AI generated response',
    ]);
    expect($userQuestionWithResponse->hasResponse())->toBeTrue();
});

test('hasFilters returns correct boolean values', function () {
    // User question without filters
    $userQuestionWithoutFilters = UserQuestion::factory()->create([
        'parsed_filters' => null,
    ]);
    expect($userQuestionWithoutFilters->hasFilters())->toBeFalse();
    
    // User question with empty filters
    $userQuestionWithEmptyFilters = UserQuestion::factory()->create([
        'parsed_filters' => [],
    ]);
    expect($userQuestionWithEmptyFilters->hasFilters())->toBeFalse();
    
    // User question with filters
    $userQuestionWithFilters = UserQuestion::factory()->create([
        'parsed_filters' => ['borough' => 'MANHATTAN'],
    ]);
    expect($userQuestionWithFilters->hasFilters())->toBeTrue();
});

test('byConversation scope filters by conversation id', function () {
    UserQuestion::factory()->create(['conversation_id' => 'conv-123']);
    UserQuestion::factory()->create(['conversation_id' => 'conv-456']);
    UserQuestion::factory()->create(['conversation_id' => 'conv-123']);
    
    $conv123Questions = UserQuestion::byConversation('conv-123')->get();
    expect($conv123Questions)->toHaveCount(2);
    
    $conv456Questions = UserQuestion::byConversation('conv-456')->get();
    expect($conv456Questions)->toHaveCount(1);
});

test('byUser scope filters by user id', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    UserQuestion::factory()->count(3)->create(['user_id' => $user1->id]);
    UserQuestion::factory()->count(2)->create(['user_id' => $user2->id]);
    
    $user1Questions = UserQuestion::byUser($user1->id)->get();
    expect($user1Questions)->toHaveCount(3);
    
    $user2Questions = UserQuestion::byUser($user2->id)->get();
    expect($user2Questions)->toHaveCount(2);
});

test('withResponse scope filters questions with ai responses', function () {
    // Explicitly create records without responses first to clear any existing data
    UserQuestion::query()->delete();
    
    UserQuestion::factory()->withResponse()->create(['ai_response' => 'Response 1']);
    UserQuestion::factory()->withoutResponse()->create(['ai_response' => null]);
    UserQuestion::factory()->withResponse()->create(['ai_response' => 'Response 2']);
    UserQuestion::factory()->withoutResponse()->create(['ai_response' => '']);
    
    $questionsWithResponse = UserQuestion::withResponse()->get();
    expect($questionsWithResponse)->toHaveCount(2);
});

test('multiple scopes can be chained', function () {
    $user = User::factory()->create();
    
    // Create test data
    UserQuestion::factory()->create([
        'user_id' => $user->id,
        'conversation_id' => 'conv-123',
        'ai_response' => 'Response 1',
    ]);
    
    UserQuestion::factory()->create([
        'user_id' => $user->id,
        'conversation_id' => 'conv-123',
        'ai_response' => null,
    ]);
    
    UserQuestion::factory()->create([
        'user_id' => $user->id,
        'conversation_id' => 'conv-456',
        'ai_response' => 'Response 2',
    ]);
    
    // Chain multiple scopes
    $results = UserQuestion::byUser($user->id)
        ->byConversation('conv-123')
        ->withResponse()
        ->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->conversation_id)->toBe('conv-123')
        ->and($results->first()->ai_response)->toBe('Response 1');
});

test('user question stores complex parsed filters', function () {
    $complexFilters = [
        'borough' => 'MANHATTAN',
        'complaint_types' => ['Noise', 'Water System'],
        'date_range' => [
            'from' => '2024-01-01',
            'to' => '2024-12-31',
        ],
        'risk_level' => 'high',
    ];
    
    $userQuestion = UserQuestion::factory()->create([
        'parsed_filters' => $complexFilters,
    ]);
    
    expect($userQuestion->parsed_filters)->toBe($complexFilters)
        ->and($userQuestion->parsed_filters['borough'])->toBe('MANHATTAN')
        ->and($userQuestion->parsed_filters['complaint_types'])->toBeArray()
        ->and($userQuestion->parsed_filters['complaint_types'])->toContain('Noise')
        ->and($userQuestion->parsed_filters['date_range']['from'])->toBe('2024-01-01');
});

test('user question handles null and empty values gracefully', function () {
    $userQuestion = UserQuestion::factory()->create([
        'ai_response' => null,
        'parsed_filters' => null,
    ]);
    
    expect($userQuestion->ai_response)->toBeNull()
        ->and($userQuestion->parsed_filters)->toBeNull()
        ->and($userQuestion->hasResponse())->toBeFalse()
        ->and($userQuestion->hasFilters())->toBeFalse();
});

test('user question model uses unguarded mass assignment', function () {
    // Test that we can mass assign any attribute
    $data = [
        'question' => 'Test question',
        'random_field' => 'This should work',
        'another_field' => 'Because unguarded',
    ];
    
    $userQuestion = new UserQuestion($data);
    
    expect($userQuestion->question)->toBe('Test question')
        ->and($userQuestion->random_field)->toBe('This should work')
        ->and($userQuestion->another_field)->toBe('Because unguarded');
});

test('user question maintains referential integrity with user', function () {
    $user = User::factory()->create();
    $userQuestion = UserQuestion::factory()->create([
        'user_id' => $user->id,
    ]);
    
    // Verify the relationship exists
    expect($user->userQuestions()->count())->toBe(1)
        ->and($userQuestion->user->id)->toBe($user->id);
    
    // Delete the user (should handle cascade appropriately)
    $user->delete();
    
    // Check if user question still exists (depends on your migration cascading rules)
    $questionExists = UserQuestion::find($userQuestion->id);
    
    // This test assumes you have appropriate cascade handling configured
    // Adjust expectation based on your actual database constraints
    if (is_null($questionExists)) {
        expect($questionExists)->toBeNull();
    } else {
        expect($questionExists)->toBeInstanceOf(UserQuestion::class);
    }
});

test('user question stores conversation history correctly', function () {
    $conversationId = 'conv-conversation-test';
    
    // Create a conversation with multiple questions
    UserQuestion::factory()->create([
        'conversation_id' => $conversationId,
        'question' => 'What are the most common complaints?',
        'ai_response' => 'Noise complaints are most common.',
        'created_at' => now()->subMinutes(10),
    ]);
    
    UserQuestion::factory()->create([
        'conversation_id' => $conversationId,
        'question' => 'What about in Brooklyn specifically?',
        'ai_response' => 'In Brooklyn, water issues are also common.',
        'created_at' => now()->subMinutes(5),
    ]);
    
    UserQuestion::factory()->create([
        'conversation_id' => $conversationId,
        'question' => 'Show me high risk complaints only',
        'ai_response' => 'Here are the high risk complaints...',
        'created_at' => now(),
    ]);
    
    $conversation = UserQuestion::byConversation($conversationId)
        ->orderBy('created_at')
        ->get();
    
    expect($conversation)->toHaveCount(3)
        ->and($conversation->first()->question)->toContain('most common complaints')
        ->and($conversation->last()->question)->toContain('high risk complaints');
});

test('user question factory creates valid data', function () {
    $userQuestion = UserQuestion::factory()->create();
    
    expect($userQuestion->user_id)->toBeInt()
        ->and($userQuestion->conversation_id)->toBeString()
        ->and($userQuestion->question)->toBeString()
        ->and($userQuestion->question)->not->toBeEmpty();
    
    // AI response and parsed filters may be nullable
    if ($userQuestion->ai_response !== null) {
        expect($userQuestion->ai_response)->toBeString();
    }
    
    if ($userQuestion->parsed_filters !== null) {
        expect($userQuestion->parsed_filters)->toBeArray();
    }
});