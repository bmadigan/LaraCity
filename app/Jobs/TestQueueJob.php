<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestQueueJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public string $message
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::info('TestQueueJob executed successfully', [
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ]);
        
        echo "✅ Queue job executed: {$this->message}" . PHP_EOL;
    }
}