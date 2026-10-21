<?php

namespace App\Assistant\Llm;

use App\Assistant\AssistantContext;
use App\Assistant\Tools\AssistantTool;

/**
 * Tudo o que vai para a IA numa rodada: as instruções, a conversa até aqui e as ferramentas que
 * ela pode pedir. O contexto estruturado (data, categorias, cartões, listas) também vai junto: a
 * IA real lê ele pelo texto das instruções; a simulada, direto.
 */
final readonly class LlmRequest
{
    /**
     * @param  list<LlmMessage>  $messages
     * @param  list<AssistantTool>  $tools
     */
    public function __construct(
        public string $system,
        public array $messages,
        public array $tools,
        public AssistantContext $context,
    ) {}

    /** A mesma requisição com mais mensagens no fim (pedidos e resultados de ferramentas). */
    public function with(LlmMessage ...$messages): self
    {
        return new self($this->system, [...$this->messages, ...$messages], $this->tools, $this->context);
    }

    /** O que a pessoa acabou de escrever. */
    public function lastUserText(): string
    {
        foreach (array_reverse($this->messages) as $message) {
            if ($message->role === 'user') {
                return (string) $message->text;
            }
        }

        return '';
    }

    /**
     * Os resultados de ferramentas que chegaram depois da última fala da pessoa: se houver, a IA já
     * consultou o que precisava e agora escreve a resposta.
     *
     * @return list<LlmMessage>
     */
    public function toolResultsSinceLastUser(): array
    {
        $results = [];

        foreach (array_reverse($this->messages) as $message) {
            if ($message->role === 'user') {
                break;
            }

            if ($message->role === 'tool') {
                array_unshift($results, $message);
            }
        }

        return $results;
    }
}
