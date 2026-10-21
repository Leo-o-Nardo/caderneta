<?php

namespace App\Assistant\Llm;

/** O que a IA devolveu numa rodada: pedidos de ferramenta ou o texto final, com o que gastou. */
final readonly class LlmResponse
{
    /**
     * @param  list<ToolCall>  $toolCalls
     */
    private function __construct(
        public ?string $text,
        public array $toolCalls = [],
        // Null na IA simulada: ela não gasta nada e não entra no registro de uso.
        public ?TokenUsage $usage = null,
        // O modelo que respondeu de fato ("gemini-3.8-flash").
        public ?string $model = null,
    ) {}

    public static function text(string $text, ?TokenUsage $usage = null, ?string $model = null): self
    {
        return new self($text, usage: $usage, model: $model);
    }

    /**
     * Pedidos de ferramenta. $text é o que a IA disse antes de pedir ("vou conferir…"), quando diz.
     *
     * @param  list<ToolCall>  $calls
     */
    public static function tools(array $calls, ?string $text = null, ?TokenUsage $usage = null, ?string $model = null): self
    {
        return new self($text, $calls, $usage, $model);
    }

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
