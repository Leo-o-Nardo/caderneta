<?php

namespace App\Assistant\Llm;

use Closure;

/**
 * Uma IA que conversa e pede ferramentas: o Gemini (fase 5) e a simulada (FakeProvider). O resto do
 * assistente só conhece este contrato, então trocar de IA é trocar a classe no .env.
 */
interface LlmProvider
{
    /**
     * Uma rodada: a IA devolve pedidos de ferramenta ou o texto final. O texto chega aos pedaços em
     * $onDelta (streaming) enquanto é gerado, e também inteiro na resposta.
     *
     * @param  (Closure(string): void)|null  $onDelta
     */
    public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse;
}
