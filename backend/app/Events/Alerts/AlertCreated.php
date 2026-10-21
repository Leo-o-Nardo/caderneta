<?php

namespace App\Events\Alerts;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Um aviso novo no Chat da pessoa: o app mostra a mensagem e soma 1 no número da aba Chat. */
final class AlertCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly Message $message,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'alert.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->resolve(),
            // A casa do aviso: o número da aba só conta os da casa que está aberta no app.
            'household_id' => $this->message->conversation->household_id,
        ];
    }
}
