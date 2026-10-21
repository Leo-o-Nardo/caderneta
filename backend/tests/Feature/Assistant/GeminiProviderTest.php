<?php

use App\Assistant\AssistantContext;
use App\Assistant\Llm\Gemini\GeminiPayload;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Tools\ToolRegistry;
use App\Enums\HouseholdRole;
use App\Events\Assistant\ActionUpdated;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\LlmUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
| O GeminiProvider com respostas do Gemini simuladas pelo Http::fake: nada sai para a internet.
*/

// Numa sexta-feira, 09/10/2026, ao meio-dia de Brasília. A fila roda na hora (QUEUE_CONNECTION=sync).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
    Event::fake([
        AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class, ActionUpdated::class,
    ]);
    // Tudo fixo aqui: o teste não pode depender do modelo que está no .env de quem roda.
    config([
        'assistant.provider' => 'gemini',
        'assistant.gemini.api_key' => 'test-key',
        'assistant.gemini.model' => 'gemini-3.5-flash-lite',
        'assistant.gemini.thinking_level' => 'low',
        'assistant.gemini.retry_delay_ms' => 0,
    ]);
    // Mesmo com a chave de verdade no .env, nenhuma chamada sem resposta simulada sai daqui.
    Http::preventStrayRequests();
});

function geminiAsk(string $text): TestResponse
{
    return test()->postJson('/api/v1/conversation/messages', ['text' => $text])->assertStatus(202);
}

/** Confere, descendo pela árvore, que o schema só usa as chaves que o Gemini aceita. */
function assertGeminiSchema(stdClass $schema, string $path): void
{
    $allowed = ['type', 'description', 'properties', 'required', 'items', 'enum', 'format', 'nullable'];

    expect(array_diff(array_keys((array) $schema), $allowed))->toBe([], "Chave não aceita em {$path}");

    if (isset($schema->properties)) {
        expect($schema->properties)->toBeInstanceOf(stdClass::class, "\"properties\" precisa ser objeto em {$path}");

        foreach ((array) $schema->properties as $name => $property) {
            assertGeminiSchema($property, "{$path}.{$name}");
        }
    }

    if (isset($schema->items)) {
        assertGeminiSchema($schema->items, "{$path}[]");
    }
}

it('streams the answer of the Gemini and records what it cost', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiSse(
        geminiChunk([['text' => 'Oi, ']]),
        geminiChunk([['text' => 'Leo!']], 'STOP', ['promptTokenCount' => 1200, 'candidatesTokenCount' => 5, 'thoughtsTokenCount' => 45]),
    ), 200, ['Content-Type' => 'text/event-stream'])]);

    $leo = User::factory()->create(['name' => 'Leo']);
    householdOf($leo);
    Sanctum::actingAs($leo);

    geminiAsk('oi')
        ->assertJsonPath('reply.status', 'completed')
        ->assertJsonPath('reply.content', 'Oi, Leo!');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:streamGenerateContent?alt=sse'
        // A chave vai no cabeçalho, nunca na URL.
        && $request->hasHeader('x-goog-api-key', 'test-key')
        && str_contains($request['systemInstruction']['parts'][0]['text'], 'assistente financeiro')
        && $request['contents'] === [['role' => 'user', 'parts' => [['text' => 'oi']]]]
        && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'low'
        && count($request['tools'][0]['functionDeclarations']) === count(config('assistant.tools')));

    $usage = LlmUsage::query()->sole();

    expect($usage->provider)->toBe('gemini')
        ->and($usage->model)->toBe('gemini-3.5-flash-lite')
        ->and($usage->round)->toBe(1)
        ->and($usage->input_tokens)->toBe(1200)
        ->and($usage->output_tokens)->toBe(5)
        ->and($usage->thinking_tokens)->toBe(45)
        // 1.200 × 0,30 + (5 + 45) × 2,50 = 485 milionésimos de dólar.
        ->and($usage->cost_micros)->toBe(485)
        ->and($usage->error)->toBeNull();
});

it('runs the tool the Gemini asked for and sends the call back with its signature', function () {
    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(geminiSse(geminiChunk([
            ['text' => 'Vou conferir.'],
            [
                'functionCall' => ['id' => 'fc_1', 'name' => 'query_transactions', 'args' => ['from' => '2026-10-01', 'to' => '2026-10-09', 'kind' => 'expense']],
                'thoughtSignature' => 'assinatura-1',
            ],
        ], 'STOP', ['promptTokenCount' => 1500, 'candidatesTokenCount' => 30])))
        ->push(geminiSse(geminiChunk(
            [['text' => 'De 1º a 9 de outubro, foram R$ 42,00.']],
            'STOP',
            ['promptTokenCount' => 1700, 'candidatesTokenCount' => 20],
        )));

    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    spend($house, 'Mercado', 4200, '2026-10-05');

    // O que a IA disse antes de pedir a ferramenta já foi para a tela: fica na resposta.
    geminiAsk('quanto gastei esse mês?')
        ->assertJsonPath('reply.content', "Vou conferir.\n\nDe 1º a 9 de outubro, foram R$ 42,00.");

    $second = Http::recorded()[1][0];

    // O pedido volta exatamente como veio, com a assinatura na mesma parte.
    expect($second['contents'][1])->toBe(['role' => 'model', 'parts' => [
        ['text' => 'Vou conferir.'],
        [
            'functionCall' => ['id' => 'fc_1', 'name' => 'query_transactions', 'args' => ['from' => '2026-10-01', 'to' => '2026-10-09', 'kind' => 'expense']],
            'thoughtSignature' => 'assinatura-1',
        ],
    ]]);

    $result = $second['contents'][2]['parts'][0]['functionResponse'];

    expect($second['contents'][2]['role'])->toBe('user')
        ->and($result['id'])->toBe('fc_1')
        ->and($result['name'])->toBe('query_transactions')
        ->and($result['response']['total_cents'])->toBe(4200)
        ->and(LlmUsage::query()->orderBy('round')->pluck('round')->all())->toBe([1, 2]);
});

it('tries again once when the Gemini is busy, before anything reached the screen', function () {
    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(['error' => ['code' => 429, 'message' => 'Resource exhausted']], 429)
        ->push(geminiSse(geminiChunk([['text' => 'Oi!']], 'STOP', ['promptTokenCount' => 900, 'candidatesTokenCount' => 2])));

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    geminiAsk('oi')->assertJsonPath('reply.content', 'Oi!');

    Http::assertSentCount(2);
});

it('tells the person when the Gemini stays busy, and records the error', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 503, 'message' => 'Unavailable']], 503)]);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    geminiAsk('oi')
        ->assertJsonPath('reply.status', 'failed')
        ->assertJsonPath('reply.content', 'A IA está ocupada agora. Tente de novo em instantes.');

    // A primeira tentativa e mais uma.
    Http::assertSentCount(2);
    expect(LlmUsage::query()->sole()->error)->toBe('busy');
});

it('answers politely when the Gemini blocks the question', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiSse(
        ['promptFeedback' => ['blockReason' => 'SAFETY'], 'usageMetadata' => ['promptTokenCount' => 800]],
    ))]);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    geminiAsk('pergunta barrada')
        ->assertJsonPath('reply.status', 'failed')
        ->assertJsonPath('reply.content', 'Não consigo responder isso. Tente perguntar de outro jeito.');

    // Barrado não é passageiro: não tenta de novo.
    Http::assertSentCount(1);
});

it('keeps the card when the Gemini fails after proposing an action', function () {
    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(geminiSse(geminiChunk([[
            'functionCall' => [
                'id' => 'fc_1',
                'name' => 'create_transactions',
                'args' => ['items' => [['amount' => 12, 'description' => 'Café', 'category' => 'Alimentação', 'payment' => 'pix']]],
            ],
            'thoughtSignature' => 'assinatura-1',
        ]], 'STOP', ['promptTokenCount' => 1500, 'candidatesTokenCount' => 40])))
        ->push(['error' => ['message' => 'Unavailable']], 503)
        ->push(['error' => ['message' => 'Unavailable']], 503);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    geminiAsk('gastei 12 de café no pix')
        ->assertJsonPath('reply.status', 'completed')
        ->assertJsonPath('reply.content', 'Não consegui terminar a resposta, mas o que preparei está no card abaixo.')
        ->assertJsonPath('reply.cards.0.type', 'transactions_confirm')
        ->assertJsonPath('reply.cards.0.action.status', 'pending');
});

it('declares every tool in the format the Gemini accepts', function () {
    $tools = app(ToolRegistry::class)->availableFor(HouseholdRole::Owner);
    $context = new AssistantContext(CarbonImmutable::now(), 'Leo', 'Casa do Leo', HouseholdRole::Owner);
    $request = new LlmRequest('Instruções', [LlmMessage::user('oi')], $tools, $context);

    // Ida e volta pelo JSON, como o Gemini recebe: objetos viram stdClass, listas viram arrays.
    $body = json_decode(json_encode(GeminiPayload::build($request, config('assistant.gemini'))));
    $declarations = $body->tools[0]->functionDeclarations;

    expect($declarations)->toHaveCount(count($tools));

    foreach ($declarations as $declaration) {
        expect($declaration->name)->toBeString()
            ->and($declaration->description)->not->toBeEmpty()
            ->and($declaration->parameters->type)->toBe('object');

        assertGeminiSchema($declaration->parameters, $declaration->name);
    }
});
