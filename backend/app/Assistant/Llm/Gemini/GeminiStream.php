<?php

namespace App\Assistant\Llm\Gemini;

use App\Assistant\Llm\LlmException;
use App\Assistant\Llm\LlmResponse;
use App\Assistant\Llm\TokenUsage;
use App\Assistant\Llm\ToolCall;
use Closure;

/**
 * Junta os pedaços de uma resposta do streamGenerateContent (um JSON por linha "data:"). O texto
 * vai para a tela assim que chega; os pedidos de ferramenta, a assinatura de cada um e os tokens
 * gastos ficam guardados até o fim.
 */
final class GeminiStream
{
    // Motivos de parada que querem dizer "o filtro de segurança barrou a resposta".
    private const BLOCKED = ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'];

    // Algum texto já foi para a tela: tentar de novo repetiria o começo da resposta.
    public bool $streamed = false;

    private string $text = '';

    /** @var list<ToolCall> */
    private array $calls = [];

    private array $usage = [];

    private ?string $finishReason = null;

    private ?string $blockReason = null;

    private ?string $model = null;

    /** @param  (Closure(string): void)|null  $onDelta */
    public function __construct(private readonly ?Closure $onDelta = null) {}

    /** Uma linha do SSE. Só as linhas "data: {...}" trazem conteúdo. */
    public function line(string $line): void
    {
        if (! str_starts_with($line, 'data:')) {
            return;
        }

        $chunk = json_decode(trim(substr($line, 5)), true);

        if (is_array($chunk)) {
            $this->feed($chunk);
        }
    }

    public function feed(array $chunk): void
    {
        if (isset($chunk['error'])) {
            throw LlmException::failed('Gemini: '.($chunk['error']['message'] ?? 'erro no meio da resposta'));
        }

        $this->model = $chunk['modelVersion'] ?? $this->model;
        $this->usage = $chunk['usageMetadata'] ?? $this->usage;
        $this->blockReason = $chunk['promptFeedback']['blockReason'] ?? $this->blockReason;

        $candidate = $chunk['candidates'][0] ?? null;

        if ($candidate === null) {
            return;
        }

        $this->finishReason = $candidate['finishReason'] ?? $this->finishReason;

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['functionCall'])) {
                $this->calls[] = new ToolCall(
                    id: $part['functionCall']['id'] ?? GeminiPayload::LOCAL_ID_PREFIX.(count($this->calls) + 1),
                    name: (string) ($part['functionCall']['name'] ?? ''),
                    args: is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [],
                    signature: $part['thoughtSignature'] ?? null,
                );

                continue;
            }

            $text = $part['text'] ?? '';

            // Partes vazias (só com a assinatura) e resumos de raciocínio não são resposta.
            if ($text === '' || ($part['thought'] ?? false)) {
                continue;
            }

            $this->text .= $text;
            $this->streamed = true;

            if ($this->onDelta !== null) {
                ($this->onDelta)($text);
            }
        }
    }

    /** A resposta completa, depois da última linha. Sem texto nem pedido de ferramenta, é erro. */
    public function response(): LlmResponse
    {
        if ($this->blockReason !== null) {
            throw LlmException::blocked("Gemini barrou a pergunta ({$this->blockReason}).");
        }

        $usage = new TokenUsage(
            input: (int) ($this->usage['promptTokenCount'] ?? 0),
            output: (int) ($this->usage['candidatesTokenCount'] ?? 0),
            thinking: (int) ($this->usage['thoughtsTokenCount'] ?? 0),
            cached: (int) ($this->usage['cachedContentTokenCount'] ?? 0),
        );

        if ($this->calls !== []) {
            return LlmResponse::tools($this->calls, $this->text !== '' ? $this->text : null, $usage, $this->model);
        }

        if ($this->text !== '') {
            return LlmResponse::text($this->text, $usage, $this->model);
        }

        throw match (true) {
            in_array($this->finishReason, self::BLOCKED, true) => LlmException::blocked("Gemini barrou a resposta ({$this->finishReason})."),
            in_array($this->finishReason, ['MALFORMED_FUNCTION_CALL', 'UNEXPECTED_TOOL_CALL'], true) => LlmException::failed("Gemini pediu uma ferramenta de um jeito inválido ({$this->finishReason})."),
            $this->finishReason === 'MAX_TOKENS' => LlmException::failed('Gemini parou no limite de tokens sem responder (o raciocínio gastou tudo).'),
            default => LlmException::failed('Gemini devolveu uma resposta vazia ('.($this->finishReason ?? 'sem motivo').').'),
        };
    }
}
