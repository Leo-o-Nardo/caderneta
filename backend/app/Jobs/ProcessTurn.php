<?php

namespace App\Jobs;

use App\Assistant\Observers\BroadcastObserver;
use App\Assistant\TurnRunner;
use App\Enums\MessageStatus;
use App\Events\Assistant\AssistantFailed;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Processa a resposta do assistente fora da requisição: a mensagem volta para o app na hora (202)
 * e o texto chega depois, aos pedaços, pelo canal do usuário.
 */
final class ProcessTurn implements ShouldQueue
{
    use Queueable;

    // A IA pode ter gravado algo numa tentativa; repetir sozinho arriscaria duplicar.
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $replyId)
    {
        $this->onQueue('assistant');
    }

    public function handle(TurnRunner $runner): void
    {
        $reply = Message::query()->with('conversation.user', 'conversation.household')->find($this->replyId);

        // Já respondida (ou apagada): nada a fazer.
        if ($reply === null || $reply->status !== MessageStatus::Pending) {
            return;
        }

        $runner->run($reply, new BroadcastObserver($reply->conversation->user_id));
    }

    /** O worker matou o job (tempo esgotado): a resposta não pode ficar "pensando" para sempre. */
    public function failed(?Throwable $error): void
    {
        $reply = Message::query()->with('conversation')->find($this->replyId);

        if ($reply?->status === MessageStatus::Pending) {
            $reply->forceFill(['content' => __('assistant.failed'), 'status' => MessageStatus::Failed])->save();
            event(new AssistantFailed($reply->conversation->user_id, $reply));
        }
    }
}
