<?php

namespace App\Assistant\Llm;

/** A IA pedindo para rodar uma ferramenta: qual e com quais argumentos. */
final readonly class ToolCall
{
    public function __construct(
        // Identifica a chamada, para a resposta da ferramenta voltar ligada a ela.
        public string $id,
        public string $name,
        public array $args,
        // Dado opaco do provedor que precisa voltar junto com o pedido na rodada seguinte (a
        // "assinatura de raciocínio" do Gemini). O resto do assistente só carrega, sem olhar.
        public ?string $signature = null,
    ) {}
}
