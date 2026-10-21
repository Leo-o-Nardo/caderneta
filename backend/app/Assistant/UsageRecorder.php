<?php

namespace App\Assistant;

use App\Assistant\Llm\LlmException;
use App\Assistant\Llm\LlmResponse;
use App\Assistant\Llm\TokenUsage;
use App\Assistant\Voice\Transcript;
use App\Models\LlmUsage;
use App\Models\Message;
use Throwable;

/**
 * Grava o que cada chamada de IA gastou (tabela llm_usage): as rodadas do assistente ("chat") e as
 * transcrições de voz ("transcription"). A IA e o transcritor simulados não gastam nada e ficam de
 * fora. Falhar ao gravar nunca derruba a resposta: o registro é para acompanhar custo e erros.
 */
final class UsageRecorder
{
    public function record(Message $reply, int $round, LlmResponse $response, int $latencyMs): void
    {
        if ($response->usage !== null) {
            $this->tokens($reply, 'chat', $round, $response->usage, $response->model, $latencyMs);
        }
    }

    /** A rodada que não teve resposta: entra com o motivo, para acompanhar a frequência dos erros. */
    public function failed(Message $reply, int $round, LlmException $error, int $latencyMs): void
    {
        $this->save($reply, 'chat', $round, $latencyMs, [
            'model' => (string) config('assistant.gemini.model'),
            'error' => $error->reason,
        ]);
    }

    /** Uma mensagem de voz transcrita ($message é a mensagem da pessoa). */
    public function transcribed(Message $message, Transcript $transcript, int $latencyMs): void
    {
        if ($transcript->usage !== null) {
            $this->tokens($message, 'transcription', 1, $transcript->usage, $transcript->model, $latencyMs);
        }
    }

    public function transcriptionFailed(Message $message, LlmException $error, int $latencyMs): void
    {
        $this->save($message, 'transcription', 1, $latencyMs, [
            'model' => (string) config('assistant.gemini.model'),
            'error' => $error->reason,
        ]);
    }

    /**
     * O custo estimado em milionésimos de dólar. Os preços são por 1 milhão de tokens, então o custo
     * em milionésimos é tokens × preço. O raciocínio é cobrado como saída.
     */
    public static function cost(string $model, TokenUsage $usage): int
    {
        $prices = self::prices($model);

        return (int) round($usage->input * $prices['input'] + ($usage->output + $usage->thinking) * $prices['output']);
    }

    /**
     * Os preços do modelo: pelo nome exato ou pelo começo dele ("gemini-3.8-flash-001" usa os de
     * "gemini-3.8-flash"). O nome mais longo ganha, para o Flash-Lite não pegar o preço do Flash.
     *
     * @return array{input: float, output: float}
     */
    private static function prices(string $model): array
    {
        $table = config('assistant.prices', []);
        uksort($table, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($table as $name => $prices) {
            if (str_starts_with($model, $name)) {
                return $prices;
            }
        }

        return ['input' => 0.0, 'output' => 0.0];
    }

    private function tokens(Message $message, string $purpose, int $round, TokenUsage $usage, ?string $model, int $latencyMs): void
    {
        $model ??= (string) config('assistant.gemini.model');

        $this->save($message, $purpose, $round, $latencyMs, [
            'model' => $model,
            'input_tokens' => $usage->input,
            'output_tokens' => $usage->output,
            'thinking_tokens' => $usage->thinking,
            'cached_tokens' => $usage->cached,
            'cost_micros' => self::cost($model, $usage),
        ]);
    }

    private function save(Message $message, string $purpose, int $round, int $latencyMs, array $data): void
    {
        try {
            LlmUsage::query()->create([
                'user_id' => $message->conversation->user_id,
                'household_id' => $message->conversation->household_id,
                'message_id' => $message->id,
                // Quem atendeu: o provedor do assistente, ou o transcritor nas mensagens de voz.
                'provider' => (string) config($purpose === 'transcription' ? 'assistant.transcriber' : 'assistant.provider'),
                'purpose' => $purpose,
                'round' => $round,
                'latency_ms' => $latencyMs,
                ...$data,
            ]);
        } catch (Throwable $error) {
            report($error);
        }
    }
}
