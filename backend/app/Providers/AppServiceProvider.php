<?php

namespace App\Providers;

use App\Assistant\Llm\FakeProvider;
use App\Assistant\Llm\GeminiProvider;
use App\Assistant\Llm\LlmProvider;
use App\Assistant\Privacy\VaultedProvider;
use App\Assistant\Tools\ToolRegistry;
use App\Assistant\Voice\FakeTranscriber;
use App\Assistant\Voice\GeminiTranscriber;
use App\Assistant\Voice\Transcriber;
use App\Support\HouseholdContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra os serviços da aplicação.
     */
    public function register(): void
    {
        // Uma instância por requisição (ou por job da fila), nunca compartilhada entre elas.
        $this->app->scoped(HouseholdContext::class);

        // Qual IA responde (ASSISTANT_PROVIDER no .env), por trás do cofre: documentos, cartões e
        // telefones viram marcadores antes de sair. Uma instância nova a cada turno (bind).
        $this->app->bind(LlmProvider::class, function () {
            $provider = match (config('assistant.provider')) {
                'fake' => new FakeProvider(config('assistant.fake.chunk_delay_ms')),
                'gemini' => new GeminiProvider(config('assistant.gemini')),
                default => throw new InvalidArgumentException('Provedor de IA desconhecido: '.config('assistant.provider')),
            };

            return config('assistant.vault') ? new VaultedProvider($provider) : $provider;
        });

        // Quem transcreve as mensagens de voz (ASSISTANT_TRANSCRIBER no .env).
        $this->app->bind(Transcriber::class, fn () => match (config('assistant.transcriber')) {
            'gemini' => new GeminiTranscriber(config('assistant.gemini')),
            'fake' => new FakeTranscriber(config('assistant.fake.transcript')),
            default => throw new InvalidArgumentException('Transcritor desconhecido: '.config('assistant.transcriber')),
        });

        $this->app->singleton(ToolRegistry::class, fn (Application $app) => new ToolRegistry(
            array_map(fn (string $tool) => $app->make($tool), config('assistant.tools')),
        ));
    }

    /**
     * Inicializa os serviços da aplicação.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        // Cada mensagem custa uma chamada de IA: evita rajadas (o limite diário fica no controller).
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
