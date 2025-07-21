<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\User;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Services\PythonAiBridge;


/**
 * Create a test user and optionally log them in.
 */
function createUser(array $attributes = [], bool $login = false): User
{
    $user = User::factory()->create($attributes);
    
    if ($login) {
        test()->actingAs($user);
    }
    
    return $user;
}

/**
 * Create a test complaint with optional analysis.
 */
function createComplaint(array $attributes = [], bool $withAnalysis = false): Complaint
{
    $complaint = Complaint::factory()->create($attributes);
    
    if ($withAnalysis) {
        ComplaintAnalysis::factory()->create([
            'complaint_id' => $complaint->id,
        ]);
    }
    
    return $complaint;
}

/**
 * Mock the Python AI Bridge service.
 */
function mockPythonBridge(?array $analysisResponse = null, ?array $embeddingResponse = null): void
{
    $mock = Mockery::mock(PythonAiBridge::class);
    
    if ($analysisResponse !== null) {
        $mock->shouldReceive('analyzeComplaint')
            ->andReturn($analysisResponse);
    }
    
    if ($embeddingResponse !== null) {
        $mock->shouldReceive('generateEmbedding')
            ->andReturn($embeddingResponse);
    }
    
    test()->instance(PythonAiBridge::class, $mock);
}
