<?php

namespace App\Assistant\Privacy;

use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmProvider;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\LlmResponse;
use App\Assistant\Llm\ToolCall;
use Closure;

/**
 * Envolve qualquer IA com o cofre: o que vai (instruções, conversa, resultados das ferramentas)
 * sai com marcadores no lugar dos dados sensíveis; o que volta (texto, streaming, argumentos das
 * ferramentas) chega com os valores reais. Nem a IA nem o TurnRunner precisam saber do cofre.
 *
 * Uma instância por pergunta (o TurnRunner é criado a cada turno): o mesmo valor mantém o mesmo
 * marcador em todas as rodadas dela.
 */
final class VaultedProvider implements LlmProvider
{
    private readonly SecureDataVault $vault;

    /**
     * Os pedidos de ferramenta como a IA mandou (com marcadores), pelo id. Na rodada seguinte eles
     * voltam idênticos, como a assinatura de raciocínio do Gemini exige.
     *
     * @var array<string, ToolCall>
     */
    private array $originalCalls = [];

    /** @var array<string, ?string> o texto que a IA disse antes dos pedidos, pelo id do primeiro */
    private array $originalTexts = [];

    public function __construct(private readonly LlmProvider $inner)
    {
        $this->vault = new SecureDataVault;
    }

    public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse
    {
        $stream = $onDelta !== null ? new DeltaUnmasker($this->vault, $onDelta) : null;

        $response = $this->inner->generate(
            $this->mask($request),
            $stream !== null ? fn (string $chunk) => $stream->push($chunk) : null,
        );

        $stream?->flush();

        return $this->unmask($response);
    }

    private function mask(LlmRequest $request): LlmRequest
    {
        $messages = array_map(fn (LlmMessage $message) => match (true) {
            $message->role === 'user' => LlmMessage::user($this->vault->mask((string) $message->text)),
            $message->role === 'tool' => LlmMessage::toolResult(
                $this->originalCalls[$message->call->id] ?? $message->call,
                $this->vault->maskArray($message->result),
            ),
            $message->toolCalls !== [] => $this->maskCalls($message),
            default => LlmMessage::assistant($this->vault->mask((string) $message->text)),
        }, $request->messages);

        // O contexto estruturado não vai para a IA real (ela lê só as instruções); a simulada usa
        // os nomes dele (categorias, listas) para entender a frase.
        return new LlmRequest($this->vault->mask($request->system), $messages, $request->tools, $request->context);
    }

    /** Os pedidos de ferramenta de uma rodada anterior: os originais da IA, ou mascarados de novo. */
    private function maskCalls(LlmMessage $message): LlmMessage
    {
        $first = $message->toolCalls[0]->id;

        if (array_key_exists($first, $this->originalTexts)) {
            return LlmMessage::toolCalls(
                array_map(fn (ToolCall $call) => $this->originalCalls[$call->id] ?? $call, $message->toolCalls),
                $this->originalTexts[$first],
            );
        }

        return LlmMessage::toolCalls(
            array_map(fn (ToolCall $call) => new ToolCall($call->id, $call->name, $this->vault->maskArray($call->args), $call->signature), $message->toolCalls),
            $message->text !== null ? $this->vault->mask($message->text) : null,
        );
    }

    private function unmask(LlmResponse $response): LlmResponse
    {
        $text = $response->text !== null ? $this->vault->unmask($response->text) : null;

        if (! $response->wantsTools()) {
            return LlmResponse::text((string) $text, $response->usage, $response->model);
        }

        foreach ($response->toolCalls as $call) {
            $this->originalCalls[$call->id] = $call;
        }

        $this->originalTexts[$response->toolCalls[0]->id] = $response->text;

        // As ferramentas recebem os valores reais: "Pix para [TELEFONE_1]" é gravado com o telefone.
        $calls = array_map(
            fn (ToolCall $call) => new ToolCall($call->id, $call->name, $this->vault->unmaskArray($call->args), $call->signature),
            $response->toolCalls,
        );

        return LlmResponse::tools($calls, $text, $response->usage, $response->model);
    }
}
