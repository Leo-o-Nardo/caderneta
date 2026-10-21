<?php

namespace App\Assistant\Tools;

/**
 * Uma ação que a IA pode pedir. O nome, a descrição e os parâmetros vão para a IA decidir quando
 * usar; o handle roda de verdade, já dentro da casa de quem perguntou.
 */
interface AssistantTool
{
    public function name(): string;

    /** O que a IA lê para decidir quando usar a ferramenta. */
    public function description(): string;

    /** Os argumentos em JSON Schema (o formato que o Gemini e os outros provedores aceitam). */
    public function parameters(): array;

    public function tier(): ToolTier;

    /** A linha cinza que o app mostra enquanto ela roda: "Consultando lançamentos…". */
    public function status(): string;

    public function handle(array $args, ToolContext $context): ToolResult;
}
