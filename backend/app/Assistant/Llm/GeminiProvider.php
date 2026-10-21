<?php

namespace App\Assistant\Llm;

use App\Assistant\Llm\Gemini\GeminiPayload;
use App\Assistant\Llm\Gemini\GeminiStream;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * O Gemini, pelo streamGenerateContent (SSE). Cada rodada manda a conversa inteira (nada fica
 * guardado do lado do Google entre as rodadas) e lê a resposta aos pedaços: o texto vai para a
 * tela na hora, os pedidos de ferramenta voltam com a assinatura de raciocínio.
 *
 * Excesso de chamadas ou serviço fora do ar: tenta de novo, mas só se nada foi para a tela ainda.
 */
final class GeminiProvider implements LlmProvider
{
    public function __construct(private readonly array $config)
    {
        if (blank($config['api_key'] ?? null)) {
            throw new InvalidArgumentException('Falta a GEMINI_API_KEY no .env (ou use ASSISTANT_PROVIDER=fake).');
        }
    }

    public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse
    {
        $body = GeminiPayload::build($request, $this->config);

        for ($attempt = 1; ; $attempt++) {
            $stream = new GeminiStream($onDelta);

            try {
                $this->send($body, $stream);

                return $stream->response();
            } catch (LlmException $error) {
                if (! $error->retryable() || $stream->streamed || $attempt > (int) $this->config['retries']) {
                    throw $error;
                }

                usleep((int) $this->config['retry_delay_ms'] * 1000 * $attempt);
            }
        }
    }

    private function send(array $body, GeminiStream $stream): void
    {
        $url = sprintf(
            '%s/models/%s:streamGenerateContent?alt=sse',
            rtrim($this->config['base_url'], '/'),
            $this->config['model'],
        );

        try {
            // A chave vai no cabeçalho, nunca na URL (URLs aparecem em logs).
            $response = Http::withHeaders(['x-goog-api-key' => $this->config['api_key']])
                ->connectTimeout((int) $this->config['connect_timeout'])
                ->timeout((int) $this->config['timeout'])
                ->withOptions(['stream' => true])
                ->post($url, $body);
        } catch (ConnectionException $error) {
            throw LlmException::busy('Sem conexão com o Gemini: '.$error->getMessage(), $error);
        }

        if ($response->failed()) {
            $message = sprintf('Gemini respondeu %d: %s', $response->status(), $response->json('error.message') ?? Str::limit($response->body(), 300));

            // 429 (muitas chamadas) e 5xx passam; 400, 403 e 404 são chave, modelo ou pedido errado.
            throw in_array($response->status(), [429, 500, 502, 503, 504], true)
                ? LlmException::busy($message)
                : LlmException::failed($message);
        }

        $this->read($response->toPsrResponse()->getBody(), $stream);
    }

    /** Lê o corpo linha por linha, conforme chega, e passa cada linha para o $stream. */
    private function read(StreamInterface $body, GeminiStream $stream): void
    {
        $buffer = '';

        try {
            while (! $body->eof()) {
                $buffer .= $body->read(8192);

                while (($end = strpos($buffer, "\n")) !== false) {
                    $stream->line(rtrim(substr($buffer, 0, $end), "\r"));
                    $buffer = substr($buffer, $end + 1);
                }
            }
        } catch (LlmException $error) {
            throw $error;
        } catch (RuntimeException $error) {
            throw LlmException::busy('A conexão com o Gemini caiu no meio da resposta.', $error);
        }

        $stream->line(rtrim($buffer, "\r\n"));
    }
}
