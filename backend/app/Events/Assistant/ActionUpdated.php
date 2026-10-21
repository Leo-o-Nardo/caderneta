<?php

namespace App\Events\Assistant;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Um card de ação mudou (confirmado, cancelado, expirado, desfeito). Vai pelo canal da pessoa,
 * então o outro aparelho dela também vê o card mudar.
 */
final class ActionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly ?int $messageId,
        public readonly array $card,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'action.updated';
    }

    public function broadcastWith(): array
    {
        return ['message_id' => $this->messageId, 'card' => $this->card];
    }
}
