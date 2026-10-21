<?php

use App\Assistant\Llm\LlmProvider;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\LlmResponse;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Numa sexta-feira, 09/10/2026, ao meio-dia de Brasília. A fila roda na hora (QUEUE_CONNECTION=sync).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
    Event::fake([AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class]);
});

function ask(string $text): TestResponse
{
    return test()->postJson('/api/v1/conversation/messages', ['text' => $text]);
}

it('answers through a tool and streams the reply to the user channel', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    spend($house, 'Mercado', 40000, '2026-10-05');

    $reply = 'De 1º a 9 de outubro, foram R$ 400,00 com Mercado, em 1 lançamento.';

    ask('quanto gastei com mercado esse mês?')
        ->assertStatus(202)
        ->assertJsonPath('message.role', 'user')
        ->assertJsonPath('message.content', 'quanto gastei com mercado esse mês?')
        ->assertJsonPath('reply.role', 'assistant')
        ->assertJsonPath('reply.status', 'completed')
        ->assertJsonPath('reply.content', $reply);

    Event::assertDispatched(AssistantStatus::class, fn (AssistantStatus $event) => $event->userId === $leo->id
        && $event->text === 'Consultando lançamentos…');
    Event::assertDispatched(AssistantCompleted::class, fn (AssistantCompleted $event) => $event->message->content === $reply);
    Event::assertNotDispatched(AssistantFailed::class);

    // Os pedaços enviados, na ordem, formam exatamente a resposta salva.
    $streamed = collect(Event::dispatched(AssistantDelta::class))
        ->map(fn (array $arguments) => $arguments[0])
        ->sortBy('seq')
        ->map(fn (AssistantDelta $event) => $event->text)
        ->implode('');

    expect($streamed)->toBe($reply);
});

it('sends a chart card when the question has no category', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    spend($house, 'Mercado', 40000, '2026-10-05');
    spend($house, 'Delivery', 9000, '2026-10-06');

    ask('quanto gastei esse mês?')
        ->assertJsonPath('reply.cards.0.type', 'bar_chart')
        ->assertJsonPath('reply.cards.0.bars.0.label', 'Mercado')
        ->assertJsonPath('reply.cards.0.bars.1.label', 'Delivery');
});

it('lists the conversation from the newest message', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    ask('bom dia');
    ask('como estão os orçamentos?');

    $this->getJson('/api/v1/conversation')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.role', 'assistant')
        ->assertJsonPath('data.1.content', 'como estão os orçamentos?')
        ->assertJsonPath('data.2.content', fn (string $content) => str_starts_with($content, 'Oi, '))
        ->assertJsonPath('data.3.content', 'bom dia');
});

it('clears the conversation by archiving it, keeping everything that was said', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    ask('bom dia');
    $this->deleteJson('/api/v1/conversation')->assertNoContent();

    // Some da tela, mas fica guardada.
    $this->getJson('/api/v1/conversation')->assertJsonCount(0, 'data');
    $this->assertDatabaseCount('messages', 2);

    // A próxima mensagem começa outra conversa, e só ela aparece.
    ask('como está o mês?');
    $this->assertDatabaseCount('conversations', 2);
    $this->getJson('/api/v1/conversation')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.content', 'como está o mês?');
});

it('keeps a separate conversation in each household', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    ask('bom dia');
    $this->postJson('/api/v1/households', ['name' => 'Casa da praia'])->assertCreated();

    $this->getJson('/api/v1/conversation')->assertJsonCount(0, 'data');
});

it('validates the text and the daily limit', function () {
    config(['assistant.daily_limit' => 1]);
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    ask('')->assertJsonValidationErrors('text');
    ask(str_repeat('a', 2001))->assertJsonValidationErrors('text');
    ask('bom dia')->assertStatus(202);
    ask('bom dia de novo')->assertJsonValidationErrors(['text' => 'limite de 1 mensagens por hoje']);

    // Limpar a conversa não zera o limite do dia.
    $this->deleteJson('/api/v1/conversation')->assertNoContent();
    ask('bom dia de novo')->assertJsonValidationErrors(['text' => 'limite de 1 mensagens por hoje']);
});

it('lets a viewer ask questions', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    ask('como está o mês?')
        ->assertStatus(202)
        ->assertJsonPath('reply.status', 'completed')
        ->assertJsonPath('reply.content', fn (string $content) => str_starts_with($content, 'Em outubro até agora, entraram R$ 0,00'));
});

it('marks the reply as failed when the AI breaks', function () {
    Exceptions::fake();
    $this->app->bind(LlmProvider::class, fn () => new class implements LlmProvider
    {
        public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse
        {
            throw new RuntimeException('A IA caiu.');
        }
    });

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    ask('bom dia')
        ->assertStatus(202)
        ->assertJsonPath('reply.status', 'failed')
        ->assertJsonPath('reply.content', 'Não consegui responder agora. Tente de novo em instantes.');

    Exceptions::assertReported(RuntimeException::class);
    Event::assertDispatched(AssistantFailed::class);
    Event::assertNotDispatched(AssistantCompleted::class);
});
