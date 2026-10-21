<?php

namespace App\Assistant\Llm;

use RuntimeException;
use Throwable;

/**
 * A IA não respondeu. O motivo decide a mensagem para a pessoa e se vale tentar de novo:
 * "busy" (excesso de chamadas, serviço fora do ar), "blocked" (filtro de segurança) ou "failed"
 * (resposta inválida, chave ou modelo errado).
 */
final class LlmException extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function busy(string $message, ?Throwable $previous = null): self
    {
        return new self('busy', $message, $previous);
    }

    public static function blocked(string $message): self
    {
        return new self('blocked', $message);
    }

    public static function failed(string $message, ?Throwable $previous = null): self
    {
        return new self('failed', $message, $previous);
    }

    /** Vale tentar de novo daqui a pouco. */
    public function retryable(): bool
    {
        return $this->reason === 'busy';
    }

    /** O que a pessoa lê no chat (o detalhe técnico fica no log). */
    public function userMessage(): string
    {
        return __("assistant.llm.{$this->reason}");
    }
}
