<?php

use App\Events\Assistant\ActionUpdated;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| O cofre de ponta a ponta, com o Gemini simulado: o que sai para o Google vai com marcadores; o
| que a pessoa vê e o que fica gravado tem os valores reais.
*/

// Numa sexta-feira, 09/10/2026, ao meio-dia de Brasília. A fila roda na hora (QUEUE_CONNECTION=sync).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
    Event::fake([
        AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class, ActionUpdated::class,
    ]);
    config([
        'assistant.provider' => 'gemini',
        'assistant.gemini.api_key' => 'test-key',
        'assistant.gemini.model' => 'gemini-3.5-flash-lite',
        'assistant.gemini.retry_delay_ms' => 0,
        'assistant.vault' => true,
    ]);
    Http::preventStrayRequests();
});

it('sends no document to the Gemini and shows the real values in the answer', function () {
    // O marcador chega partido entre dois pedaços do streaming.
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiSse(
        geminiChunk([['text' => 'Anotei: CPF [CP']]),
        geminiChunk([['text' => 'F_1] e telefone [TELEFONE_1].']], 'STOP', ['promptTokenCount' => 900, 'candidatesTokenCount' => 12]),
    ))]);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/conversation/messages', ['text' => 'meu cpf é 529.982.247-25 e meu celular (11) 98765-4321'])
        ->assertStatus(202)
        ->assertJsonPath('reply.content', 'Anotei: CPF 529.982.247-25 e telefone (11) 98765-4321.');

    $sent = Http::recorded()[0][0];

    expect($sent['contents'][0]['parts'][0]['text'])->toBe('meu cpf é [CPF_1] e meu celular [TELEFONE_1]')
        ->and($sent->body())->not->toContain('529.982.247-25')
        ->and($sent->body())->not->toContain('98765-4321');

    // O texto ao vivo também chega com os valores reais, e nunca com um marcador ou pedaço dele.
    Event::assertDispatched(AssistantDelta::class, fn (AssistantDelta $event) => str_contains($event->text, '529.982.247-25'));
    Event::assertNotDispatched(AssistantDelta::class, fn (AssistantDelta $event) => preg_match('/\[[A-Z]/', $event->text) === 1);
});

it('gives the tools the real values and hides them again in the results', function () {
    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(geminiSse(geminiChunk([[
            'functionCall' => [
                'id' => 'fc_1',
                'name' => 'create_transactions',
                'args' => ['items' => [['amount' => 50, 'description' => 'Pix para [TELEFONE_1]', 'payment' => 'pix']]],
            ],
            'thoughtSignature' => 'assinatura-1',
        ]], 'STOP', ['promptTokenCount' => 1500, 'candidatesTokenCount' => 40])))
        ->push(geminiSse(geminiChunk(
            [['text' => 'Vou lançar o Pix para [TELEFONE_1]. Confere?']],
            'STOP',
            ['promptTokenCount' => 1700, 'candidatesTokenCount' => 15],
        )));

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    // A ferramenta recebeu o telefone de verdade: é ele que fica no card e no lançamento.
    $this->postJson('/api/v1/conversation/messages', ['text' => 'mandei 50 no pix para 11987654321'])
        ->assertStatus(202)
        ->assertJsonPath('reply.content', 'Vou lançar o Pix para 11987654321. Confere?')
        ->assertJsonPath('reply.cards.0.items.0.description', 'Pix para 11987654321');

    $second = Http::recorded()[1][0];

    // O pedido volta como a IA mandou (com o marcador); o resultado da ferramenta sai mascarado.
    expect($second['contents'][1]['parts'][0]['functionCall']['args']['items'][0]['description'])->toBe('Pix para [TELEFONE_1]')
        ->and($second['contents'][1]['parts'][0]['thoughtSignature'])->toBe('assinatura-1')
        ->and($second['contents'][2]['parts'][0]['functionResponse']['response']['summary'])->toContain('[TELEFONE_1]')
        ->and(Http::recorded()->every(fn (array $pair) => ! str_contains($pair[0]->body(), '11987654321')))->toBeTrue();
});

it('keeps the demo assistant from reading a phone number as an amount', function () {
    config(['assistant.provider' => 'fake']);

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/conversation/messages', ['text' => 'gastei 50 no pix para 11987654321'])
        ->assertStatus(202)
        ->assertJsonPath('reply.cards.0.items.0.amount_cents', 5000)
        ->assertJsonPath('reply.cards.0.items.0.description', '11987654321');
});
