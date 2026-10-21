<?php

namespace App\Assistant\Llm;

/**
 * Uma mensagem no formato neutro que o assistente usa com qualquer IA: texto de quem fala, os
 * pedidos de ferramenta da IA ou o resultado de uma ferramenta. Cada provedor traduz para o seu.
 */
final readonly class LlmMessage
{
    /**
     * @param  list<ToolCall>  $toolCalls
     */
    private function __construct(
        // user, assistant ou tool.
        public string $role,
        public ?string $text = null,
        public array $toolCalls = [],
        // Só em "tool": a chamada que este resultado responde.
        public ?ToolCall $call = null,
        public array $result = [],
    ) {}

    public static function user(string $text): self
    {
        return new self('user', $text);
    }

    public static function assistant(string $text): self
    {
        return new self('assistant', $text);
    }

    /**
     * Os pedidos de ferramenta da IA, com o que ela disse antes de pedir (se disse). Voltam para a
     * IA na rodada seguinte exatamente como vieram.
     *
     * @param  list<ToolCall>  $calls
     */
    public static function toolCalls(array $calls, ?string $text = null): self
    {
        return new self('assistant', $text, $calls);
    }

    public static function toolResult(ToolCall $call, array $result): self
    {
        return new self('tool', call: $call, result: $result);
    }
}
