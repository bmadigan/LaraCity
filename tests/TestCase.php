<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        // CRITICAL: Force testing environment to prevent production database access
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        
        parent::setUp();
        
        // Double-check we're using in-memory database
        $this->assertDatabaseConnection();
    }
    
    /**
     * Verify we're using in-memory SQLite for testing safety.
     */
    private function assertDatabaseConnection(): void
    {
        $connection = config('database.default');
        $database = config('database.connections.' . $connection . '.database');
        
        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \Exception(
                "CRITICAL: Tests must use in-memory SQLite database. " .
                "Current: {$connection} with database: {$database}. " .
                "This prevents accidental production data deletion."
            );
        }
    }
}
