<?php

namespace App\Assistant\Observers;

use App\Assistant\TurnObserver;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\Message;
use Throwable;

/**
 * Manda o turno ao vivo pelo canal privado do usuário. Os pedaços de texto são juntados e saem
 * no máximo a cada ~100 ms: um evento por palavra sobrecarregaria a conexão à toa.
 */
final class BroadcastObserver implements TurnObserver
{
    private string $buffer = '';

    private int $seq = 0;

    private float $lastFlush = 0;

    // O Reverb não respondeu uma vez: neste turno, não tenta mais.
    private bool $offline = false;

    public function __construct(private readonly int $userId) {}

    public function status(Message $reply, string $text): void
    {
        $this->flush($reply);
        $this->send(new AssistantStatus($this->userId, $reply->id, $text));
    }

    public function delta(Message $reply, string $chunk): void
    {
        $this->buffer .= $chunk;

        if ((microtime(true) - $this->lastFlush) * 1000 >= config('assistant.stream_flush_ms')) {
            $this->flush($reply);
        }
    }

    public function completed(Message $reply): void
    {
        $this->flush($reply);
        $this->send(new AssistantCompleted($this->userId, $reply));
    }

    public function failed(Message $reply): void
    {
        $this->buffer = '';
        $this->send(new AssistantFailed($this->userId, $reply));
    }

    private function flush(Message $reply): void
    {
        if ($this->buffer !== '') {
            $this->send(new AssistantDelta($this->userId, $reply->id, ++$this->seq, $this->buffer));
            $this->buffer = '';
        }

        $this->lastFlush = microtime(true);
    }

    /**
     * Falhar ao transmitir não pode derrubar a resposta: ela é salva do mesmo jeito, e o app, sem
     * o ao vivo, busca a conversa sozinho até ela ficar pronta.
     */
    private function send(object $event): void
    {
        if ($this->offline) {
            return;
        }

        try {
            event($event);
        } catch (Throwable $error) {
            $this->offline = true;
            report($error);
        }
    }
}
