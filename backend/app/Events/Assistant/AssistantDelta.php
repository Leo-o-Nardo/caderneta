<?php

namespace App\Events\Assistant;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Mais um pedaço do texto da resposta. O número de sequência deixa o app pôr os pedaços na ordem
 * (e ignorar repetidos) se a conexão embaralhar alguma coisa.
 */
final class AssistantDelta implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $messageId,
        public readonly int $seq,
        public readonly string $text,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'assistant.delta';
    }

    public function broadcastWith(): array
    {
        return ['message_id' => $this->messageId, 'seq' => $this->seq, 'text' => $this->text];
    }
}
