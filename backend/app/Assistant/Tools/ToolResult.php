<?php

namespace App\Assistant\Tools;

/**
 * O que a ferramenta devolve: os dados que voltam para a IA escrever a resposta e, opcionalmente,
 * os cards que o app desenha (gráfico, confirmação). Os cards não passam pela IA.
 */
final readonly class ToolResult
{
    public function __construct(
        public array $data,
        public array $cards = [],
    ) {}

    /**
     * Um problema que a IA deve contar para a pessoa ("não achei a categoria Viagens"). A mensagem
     * já sai pronta em português: a IA simulada repete ela como está.
     */
    public static function error(string $message): self
    {
        return new self(['error' => $message]);
    }

    public function failed(): bool
    {
        return isset($this->data['error']);
    }
}
