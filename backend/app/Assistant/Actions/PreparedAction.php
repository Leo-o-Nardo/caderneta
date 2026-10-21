<?php

namespace App\Assistant\Actions;

/** Uma ação montada pela ferramenta e ainda não gravada: os dados prontos e o resumo para a IA contar. */
final readonly class PreparedAction
{
    public function __construct(
        public array $payload,
        // "Vou lançar: Café R$ 12,00 (Alimentação)…": a IA usa para escrever a resposta.
        public string $summary,
    ) {}
}
