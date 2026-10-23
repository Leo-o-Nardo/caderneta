<?php

namespace App\Jobs;

use App\Alerts\ExpoPush;
use App\Models\Message;
use App\Models\PushToken;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Leva um aviso do Chat para os celulares da pessoa, como notificação. Roda pela fila, para as
 * rotinas de aviso não esperarem o serviço de push; se ele falhar, tenta de novo mais tarde.
 */
final class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $messageId) {}

    public function handle(ExpoPush $push): void
    {
        $message = Message::query()->with('conversation')->find($this->messageId);

        if ($message === null) {
            return;
        }

        $conversation = $message->conversation;
        $tokens = PushToken::query()->where('user_id', $conversation->user_id)->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        $kind = $message->metadata['alert'] ?? 'reminder';

        $push->send(
            $tokens,
            __("alerts.push_titles.{$kind}"),
            // A notificação mostra poucas linhas; o texto inteiro está no Chat.
            Str::limit($message->content, 180),
            // O app abre o Chat da casa do aviso (trocando de casa, se precisar).
            ['message_id' => $message->id, 'household_id' => $conversation->household_id, 'kind' => $kind],
        );
    }
}
