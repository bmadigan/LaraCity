<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatResponseError implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public int $responseIndex,
        public string $error
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . explode('-', $this->sessionId)[1]),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat-response-error';
    }

    public function broadcastWith(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'responseIndex' => $this->responseIndex,
            'error' => $this->error,
        ];
    }
}