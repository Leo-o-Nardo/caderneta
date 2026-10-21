<?php

namespace App\Assistant\Llm;

/**
 * Quantos tokens uma rodada gastou. O raciocínio ("thinking") é cobrado como saída, então entra
 * separado para a conta do custo ficar clara.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $input = 0,
        public int $output = 0,
        public int $thinking = 0,
        // A parte da entrada que veio do cache do provedor (repetida de rodadas anteriores).
        public int $cached = 0,
    ) {}
}
