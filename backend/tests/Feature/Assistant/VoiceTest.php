<?php

use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Events\Assistant\MessageTranscribed;
use App\Models\LlmUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
| Mensagens de voz: o áudio vira texto (Gemini simulado pelo Http::fake ou o transcritor simulado)
| e o assistente responde como se tivesse sido digitado. O áudio nunca fica guardado.
*/

// Numa sexta-feira, 09/10/2026, ao meio-dia de Brasília. A fila roda na hora (QUEUE_CONNECTION=sync).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
    Event::fake([AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class, MessageTranscribed::class]);
    Storage::fake('local');
    Http::preventStrayRequests();
});

/**
 * Manda uma gravação, como o app (multipart). O arquivo falso precisa de conteúdo de verdade: o
 * fake()->create() só finge o tamanho, e o áudio iria vazio para o Gemini.
 */
function sendVoice(array $overrides = []): TestResponse
{
    return test()->post('/api/v1/conversation/voice', array_merge([
        'audio' => UploadedFile::fake()->createWithContent('voz.m4a', str_repeat('audio', 1000))->mimeType('audio/mp4'),
        'duration_ms' => 4000,
        'source' => 'home',
    ], $overrides), ['Accept' => 'application/json']);
}

/** A resposta (sem streaming) do Gemini a um pedido de transcrição. */
function geminiTranscript(string $text): array
{
    return [
        'candidates' => [['content' => ['role' => 'model', 'parts' => $text === '' ? [] : [['text' => $text]]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 300, 'candidatesTokenCount' => 12],
        'modelVersion' => 'gemini-3.5-flash-lite',
    ];
}

/** Transcrição pelo Gemini (simulado), com o assistente simulado respondendo depois. */
function useGeminiTranscriber(): void
{
    config([
        'assistant.transcriber' => 'gemini',
        'assistant.gemini.api_key' => 'test-key',
        'assistant.gemini.model' => 'gemini-3.5-flash-lite',
        'assistant.gemini.retry_delay_ms' => 0,
    ]);
}

it('turns the voice into text and answers it like a typed message', function () {
    useGeminiTranscriber();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiTranscript('quanto gastei com mercado esse mês?'))]);

    $leo = User::factory()->create();
    $house = householdOf($leo);
    cardOf($house, $leo, ['name' => 'Nubank']);
    Sanctum::actingAs($leo);
    spend($house, 'Mercado', 40000, '2026-10-05');

    sendVoice()
        ->assertStatus(202)
        ->assertJsonPath('message.kind', 'audio')
        ->assertJsonPath('message.duration_ms', 4000)
        ->assertJsonPath('message.source', 'home')
        ->assertJsonPath('message.status', 'completed')
        ->assertJsonPath('message.content', 'quanto gastei com mercado esse mês?')
        ->assertJsonPath('reply.status', 'completed')
        ->assertJsonPath('reply.content', 'De 1º a 9 de outubro, foram R$ 400,00 com Mercado, em 1 lançamento.');

    // O áudio foi junto no pedido, com a dica dos nomes da casa; o pedido não pede para responder.
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/models/gemini-3.5-flash-lite:generateContent')
        && $request->hasHeader('x-goog-api-key', 'test-key')
        && $request['contents'][0]['parts'][0]['inlineData']['mimeType'] === 'audio/m4a'
        && base64_decode($request['contents'][0]['parts'][0]['inlineData']['data']) === str_repeat('audio', 1000)
        && str_contains($request['contents'][0]['parts'][1]['text'], 'Nubank')
        && str_contains($request['contents'][0]['parts'][1]['text'], 'Mercado')
        && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'minimal');

    // O áudio não fica no servidor.
    expect(Storage::disk('local')->allFiles('voice'))->toBe([]);

    Event::assertDispatched(MessageTranscribed::class, fn (MessageTranscribed $event) => $event->message->content === 'quanto gastei com mercado esse mês?');

    $usage = LlmUsage::query()->sole();

    expect($usage->purpose)->toBe('transcription')
        ->and($usage->provider)->toBe('gemini')
        ->and($usage->input_tokens)->toBe(300);
});

it('tells the person when the audio has no speech, without calling the assistant', function () {
    useGeminiTranscriber();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiTranscript(''))]);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    sendVoice()
        ->assertStatus(202)
        ->assertJsonPath('message.status', 'failed')
        ->assertJsonPath('message.content', null)
        ->assertJsonPath('reply.status', 'failed')
        ->assertJsonPath('reply.content', 'Não consegui entender o áudio. Tente de novo, falando perto do microfone, ou escreva.');

    expect(Storage::disk('local')->allFiles('voice'))->toBe([]);
    Event::assertDispatched(AssistantFailed::class);
    Event::assertNotDispatched(AssistantCompleted::class);
});

it('tells the person when the transcription service stays busy', function () {
    useGeminiTranscriber();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Unavailable']], 503)]);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    sendVoice()
        ->assertJsonPath('message.status', 'failed')
        ->assertJsonPath('reply.content', 'Não consegui transcrever o áudio agora. Tente de novo em instantes ou escreva.');

    // A primeira tentativa e mais uma; o áudio é apagado mesmo assim.
    Http::assertSentCount(2);
    expect(Storage::disk('local')->allFiles('voice'))->toBe([])
        ->and(LlmUsage::query()->sole()->error)->toBe('busy');
});

it('refuses audio that is too long, too big or not audio at all', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    sendVoice(['duration_ms' => 61000])->assertUnprocessable()->assertJsonValidationErrors('duration_ms');
    sendVoice(['duration_ms' => 200])->assertUnprocessable()->assertJsonValidationErrors('duration_ms');
    sendVoice(['audio' => UploadedFile::fake()->create('voz.m4a', 3000, 'audio/mp4')])->assertUnprocessable()->assertJsonValidationErrors('audio');
    sendVoice(['audio' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('audio');

    $this->assertDatabaseCount('messages', 0);
    expect(Storage::disk('local')->allFiles('voice'))->toBe([]);
});

it('counts voice messages in the daily limit', function () {
    config(['assistant.daily_limit' => 1]);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    // O transcritor simulado dos testes "ouve" sempre "quanto gastei esse mês?".
    sendVoice()->assertStatus(202)->assertJsonPath('message.content', 'quanto gastei esse mês?');

    sendVoice()->assertUnprocessable()->assertJsonValidationErrors(['audio' => 'limite de 1 mensagens']);
});
