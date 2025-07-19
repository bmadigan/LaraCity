<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Complaint Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered complaint analysis and risk escalation
    |
    */

    'escalate_threshold' => env('COMPLAINT_ESCALATE_THRESHOLD', 0.7),

    'risk_levels' => [
        'low' => ['min' => 0.0, 'max' => 0.4],
        'medium' => ['min' => 0.4, 'max' => 0.7],
        'high' => ['min' => 0.7, 'max' => 1.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for AI processing jobs
    |
    */

    'queues' => [
        'ai_analysis' => env('COMPLAINT_AI_QUEUE', 'ai-analysis'),
        'escalation' => env('COMPLAINT_ESCALATION_QUEUE', 'escalation'),
        'notification' => env('COMPLAINT_NOTIFICATION_QUEUE', 'notification'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for job processing and retries
    |
    */

    'jobs' => [
        'analyze_timeout' => env('ANALYZE_JOB_TIMEOUT', 120),
        'analyze_tries' => env('ANALYZE_JOB_TRIES', 3),
        'escalation_timeout' => env('ESCALATION_JOB_TIMEOUT', 60),
        'escalation_tries' => env('ESCALATION_JOB_TRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Bridge Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for PHP -> Python AI communication
    |
    */

    'python' => [
        'script_path' => env('PYTHON_AI_SCRIPT', base_path('lacity-ai/langchain_runner.py')),
        'timeout' => env('PYTHON_BRIDGE_TIMEOUT', 90),
        'max_output_length' => env('PYTHON_MAX_OUTPUT', 10000),
    ],
];