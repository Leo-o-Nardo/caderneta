<?php

namespace App\Assistant\Voice;

use App\Assistant\Llm\LlmException;
use App\Assistant\Llm\TokenUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Transcreve a gravação com o Gemini (generateContent, sem streaming: a resposta é curta). O
 * áudio vai junto no pedido (inlineData), com uma dica dos nomes da casa para ele escrever certo
 * "Nubank", "iFood" e o nome das listas. O raciocínio fica no mínimo: transcrever não precisa pensar.
 */
final class GeminiTranscriber implements Transcriber
{
    // Motivos de parada que querem dizer "o filtro de segurança barrou".
    private const BLOCKED = ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'];

    public function __construct(private readonly array $config)
    {
        if (blank($config['api_key'] ?? null)) {
            throw new InvalidArgumentException('Falta a GEMINI_API_KEY no .env para transcrever as mensagens de voz.');
        }
    }

    public function transcribe(string $path, string $mimeType, array $hints = []): Transcript
    {
        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode((string) file_get_contents($path))]],
                    ['text' => self::prompt($hints)],
                ],
            ]],
            'generationConfig' => [
                'maxOutputTokens' => 1024,
                'thinkingConfig' => ['thinkingLevel' => $this->config['transcription_thinking_level']],
            ],
        ];

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->send($body);
            } catch (LlmException $error) {
                if (! $error->retryable() || $attempt > (int) $this->config['retries']) {
                    throw $error;
                }

                usleep((int) $this->config['retry_delay_ms'] * 1000 * $attempt);
            }
        }
    }

    private function send(array $body): Transcript
    {
        $url = sprintf('%s/models/%s:generateContent', rtrim($this->config['base_url'], '/'), $this->config['model']);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->config['api_key']])
                ->connectTimeout((int) $this->config['connect_timeout'])
                ->timeout((int) $this->config['timeout'])
                ->post($url, $body);
        } catch (ConnectionException $error) {
            throw LlmException::busy('Sem conexão com o Gemini: '.$error->getMessage(), $error);
        }

        if ($response->failed()) {
            $message = sprintf('Gemini respondeu %d ao transcrever: %s', $response->status(), $response->json('error.message') ?? Str::limit($response->body(), 300));

            throw in_array($response->status(), [429, 500, 502, 503, 504], true)
                ? LlmException::busy($message)
                : LlmException::failed($message);
        }

        if ($block = $response->json('promptFeedback.blockReason')) {
            throw LlmException::blocked("Gemini barrou o áudio ({$block}).");
        }

        $finish = $response->json('candidates.0.finishReason');

        if (in_array($finish, self::BLOCKED, true)) {
            throw LlmException::blocked("Gemini barrou a transcrição ({$finish}).");
        }

        // Só as partes de texto que são resposta (resumos de raciocínio, se vierem, ficam de fora).
        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn (array $part) => $part['thought'] ?? false)
            ->pluck('text')
            ->implode('');

        return new Transcript(
            trim($text),
            new TokenUsage(
                input: (int) $response->json('usageMetadata.promptTokenCount', 0),
                output: (int) $response->json('usageMetadata.candidatesTokenCount', 0),
                thinking: (int) $response->json('usageMetadata.thoughtsTokenCount', 0),
                cached: (int) $response->json('usageMetadata.cachedContentTokenCount', 0),
            ),
            $response->json('modelVersion'),
        );
    }

    /** O pedido: transcrever do jeito que se digita no celular, sem responder nada. */
    private static function prompt(array $hints): string
    {
        return implode("\n", array_filter([
            'Transcreva exatamente o que a pessoa falou neste áudio, em português do Brasil.',
            'Escreva valores, datas e números com dígitos ("12", "45,90", "dia 20"), como alguém digitaria no celular.',
            'Não responda nem comente o que foi dito: devolva só a transcrição, sem aspas.',
            'Se não houver fala no áudio, devolva uma resposta vazia.',
            $hints !== [] ? 'Nomes que podem aparecer na fala: '.implode(', ', $hints).'.' : null,
        ]));
    }
}
