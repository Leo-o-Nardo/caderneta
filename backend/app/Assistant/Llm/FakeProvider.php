<?php

namespace App\Assistant\Llm;

use App\Assistant\Llm\Fake\FakeIntents;
use App\Assistant\Llm\Fake\FakeReplies;
use Closure;

/**
 * A IA simulada: mesmo contrato da real, respostas por regras. Na primeira rodada escolhe a
 * ferramenta (FakeIntents); na seguinte, com o resultado em mãos, escreve a resposta
 * (FakeReplies) e manda palavra por palavra, como o streaming de verdade.
 */
final class FakeProvider implements LlmProvider
{
    public function __construct(
        // Pausa entre as palavras, para o streaming aparecer no app. Zero nos testes.
        private readonly int $chunkDelayMs = 0,
    ) {}

    public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse
    {
        $results = $request->toolResultsSinceLastUser();

        if ($results !== []) {
            return $this->stream(FakeReplies::fromTools($results), $onDelta);
        }

        $call = FakeIntents::match($request->lastUserText(), $request->context);

        if ($call !== null && $this->offers($request, $call->name)) {
            return LlmResponse::tools([$call]);
        }

        // Reconheceu a frase, mas a ferramenta não veio: é uma ação e quem fala é "só leitura".
        if ($call !== null) {
            return $this->stream(FakeReplies::notAllowed(), $onDelta);
        }

        return $this->stream(FakeReplies::smallTalk($request->lastUserText(), $request->context), $onDelta);
    }

    /** Só pede ferramentas que vieram na requisição, como a IA real. */
    private function offers(LlmRequest $request, string $name): bool
    {
        foreach ($request->tools as $tool) {
            if ($tool->name() === $name) {
                return true;
            }
        }

        return false;
    }

    private function stream(string $text, ?Closure $onDelta): LlmResponse
    {
        if ($onDelta !== null) {
            // Cada pedaço é uma palavra com o espaço que vem depois dela.
            foreach (preg_split('/(?<=\s)/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
                $onDelta($chunk);

                if ($this->chunkDelayMs > 0) {
                    usleep($this->chunkDelayMs * 1000);
                }
            }
        }

        return LlmResponse::text($text);
    }
}
