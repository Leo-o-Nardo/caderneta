<?php

namespace App\Events\Assistant;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** A linha cinza enquanto uma ferramenta roda: "Consultando lançamentos…". */
final class AssistantStatus implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $messageId,
        public readonly string $text,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'assistant.status';
    }

    public function broadcastWith(): array
    {
        return ['message_id' => $this->messageId, 'text' => $this->text];
    }
}
