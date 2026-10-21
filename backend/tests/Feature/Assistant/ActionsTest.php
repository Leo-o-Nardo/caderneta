<?php

use App\Assistant\Llm\LlmProvider;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\LlmResponse;
use App\Enums\HouseholdRole;
use App\Events\Assistant\ActionUpdated;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\User;
use App\Services\HouseholdService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Numa sexta-feira, 09/10/2026, ao meio-dia de Brasília. A fila roda na hora (QUEUE_CONNECTION=sync).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
    Event::fake([
        AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class, ActionUpdated::class,
    ]);
});

function sayToAssistant(string $text): TestResponse
{
    return test()->postJson('/api/v1/conversation/messages', ['text' => $text])->assertStatus(202);
}

/** O que o app manda de volta ao confirmar um card de lançamentos (os campos editáveis). */
function editableItems(array $card): array
{
    return ['items' => array_map(fn (array $item) => [
        'kind' => $item['kind'],
        'amount_cents' => $item['amount_cents'],
        'description' => $item['description'],
        'occurred_on' => $item['occurred_on'],
        'category_id' => $item['category']['id'],
        'payment_type' => $item['payment_type'],
        'card_id' => $item['card']['id'] ?? null,
    ], $card['items'])];
}

it('proposes the expenses and saves nothing before the confirmation', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    sayToAssistant('gastei 12 de café e 30 de uber')
        // O Leo não tem cartão: sem forma dita, a compra vai no Pix.
        ->assertJsonPath('reply.content', 'Vou lançar: Café R$ 12,00 (Alimentação) e Uber R$ 30,00 (Transporte), no Pix, hoje. Confere?')
        ->assertJsonPath('reply.cards.0.type', 'transactions_confirm')
        ->assertJsonPath('reply.cards.0.action.status', 'pending')
        ->assertJsonPath('reply.cards.0.items.1.category.name', 'Transporte')
        ->assertJsonPath('reply.cards.0.total_cents', 4200);

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseHas('pending_actions', ['tool' => 'create_transactions', 'status' => 'pending', 'user_id' => $leo->id]);
});

it('puts a purchase on the card of who speaks when the payment is not said', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    cardOf($house, $ana, ['name' => 'Inter', 'is_default' => true]);
    cardOf($house, $leo, ['name' => 'Nubank', 'is_default' => true]);
    Sanctum::actingAs($leo);

    sayToAssistant('gastei 12 de café')
        ->assertJsonPath('reply.cards.0.items.0.payment_type', 'credit_card')
        ->assertJsonPath('reply.cards.0.items.0.card.name', 'Nubank');

    // Dito "no pix", vale o que foi dito.
    sayToAssistant('gastei 30 de uber no pix')
        ->assertJsonPath('reply.cards.0.items.0.payment_type', 'pix')
        ->assertJsonPath('reply.cards.0.items.0.card', null);
});

it('confirms with the edits, checked by the same rules as the app form', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $reply = sayToAssistant('gastei 12 de café e 30 de uber');
    $card = $reply->json('reply.cards.0');
    $payload = editableItems($card);
    $payload['items'][1]['amount_cents'] = 3250; // a pessoa corrigiu o uber no card

    $this->postJson("/api/v1/actions/{$card['action']['id']}/confirm", ['payload' => $payload])
        ->assertOk()
        ->assertJsonPath('message_id', $reply->json('reply.id'))
        ->assertJsonPath('card.action.status', 'executed')
        ->assertJsonPath('card.items.1.amount_cents', 3250);

    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseHas('transactions', ['description' => 'Uber', 'amount_cents' => 3250, 'source' => 'assistant']);

    // O card guardado na mensagem mudou junto: quem reabrir o chat vê "confirmado".
    $this->getJson('/api/v1/conversation')->assertJsonPath('data.0.cards.0.action.status', 'executed');
    Event::assertDispatched(ActionUpdated::class, fn (ActionUpdated $event) => $event->card['action']['status'] === 'executed');
});

it('refuses invalid edits and keeps the action waiting', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $otherHouse = householdOf(User::factory()->create());
    Sanctum::actingAs($leo);

    $card = sayToAssistant('gastei 12 de café e 30 de uber')->json('reply.cards.0');
    $url = "/api/v1/actions/{$card['action']['id']}/confirm";

    $foreign = editableItems($card);
    $foreign['items'][0]['category_id'] = categoryOf($otherHouse, 'Mercado')->id;
    $this->postJson($url, ['payload' => $foreign])->assertJsonValidationErrors('items.0.category_id');

    $income = editableItems($card);
    $income['items'][1]['category_id'] = categoryOf($house, 'Salário', 'income')->id;
    $this->postJson($url, ['payload' => $income])->assertJsonValidationErrors('items.1.category_id');

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseHas('pending_actions', ['ulid' => $card['action']['id'], 'status' => 'pending']);
});

it('saves only once when confirmed twice', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $id = sayToAssistant('gastei 12 de café e 30 de uber')->json('reply.cards.0.action.id');

    $this->postJson("/api/v1/actions/{$id}/confirm")->assertOk();
    $this->postJson("/api/v1/actions/{$id}/confirm")->assertJsonValidationErrors(['action' => 'já foi confirmada']);

    $this->assertDatabaseCount('transactions', 2);
});

it('refuses a confirmation after 30 minutes', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $id = sayToAssistant('gastei 12 de café')->json('reply.cards.0.action.id');
    $this->travel(31)->minutes();

    $this->postJson("/api/v1/actions/{$id}/confirm")->assertJsonValidationErrors(['action' => 'expirou']);

    $this->assertDatabaseHas('pending_actions', ['ulid' => $id, 'status' => 'expired']);
    $this->assertDatabaseCount('transactions', 0);
});

it('cancels without saving', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $id = sayToAssistant('gastei 12 de café')->json('reply.cards.0.action.id');

    $this->postJson("/api/v1/actions/{$id}/cancel")->assertOk()->assertJsonPath('card.action.status', 'cancelled');
    $this->postJson("/api/v1/actions/{$id}/confirm")->assertJsonValidationErrors(['action' => 'cancelada']);

    $this->assertDatabaseCount('transactions', 0);
});

it('only lets the person who asked touch the action, and only while they can write', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);

    Sanctum::actingAs($ana);
    $id = sayToAssistant('gastei 12 de café')->json('reply.cards.0.action.id');

    // Para outra pessoa, a ação não existe.
    Sanctum::actingAs($leo);
    $this->postJson("/api/v1/actions/{$id}/confirm")->assertNotFound();

    // A Ana virou "só leitura" antes de confirmar.
    app(HouseholdService::class)->changeRole($house, $ana, HouseholdRole::Viewer);
    Sanctum::actingAs($ana);
    $this->postJson("/api/v1/actions/{$id}/confirm")->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
});

it('does not give a viewer the tools that write', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    sayToAssistant('gastei 12 de café')
        ->assertJsonPath('reply.content', 'Você está como só leitura nesta casa: dá para perguntar, mas não lançar nem mudar nada.')
        ->assertJsonPath('reply.cards', []);

    $this->assertDatabaseCount('pending_actions', 0);
});

it('buys in installments on the card', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    cardOf($house, $leo, ['name' => 'Nubank', 'is_default' => true]);
    Sanctum::actingAs($leo);

    $reply = sayToAssistant('comprei uma tv de 2.500 em 10x no nubank')
        ->assertJsonPath('reply.cards.0.items.0.installments', 10)
        ->assertJsonPath('reply.cards.0.items.0.card.name', 'Nubank')
        ->assertJsonPath('reply.cards.0.items.0.category.name', 'Compras');

    $this->postJson("/api/v1/actions/{$reply->json('reply.cards.0.action.id')}/confirm")->assertOk();

    $this->assertDatabaseHas('installment_plans', ['description' => 'TV', 'total_cents' => 250000, 'installments' => 10, 'source' => 'assistant']);
    $this->assertDatabaseCount('transactions', 10);
});

it('pays a bill, asking for the amount when it varies', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $luz = $this->postJson('/api/v1/bills', [
        'name' => 'Luz',
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'due_day' => 15,
        'amount_varies' => true,
        'expected_cents' => 17000,
    ])->assertCreated()->json('id');

    $reply = sayToAssistant('paguei a luz')
        ->assertJsonPath('reply.cards.0.type', 'bill_confirm')
        ->assertJsonPath('reply.cards.0.amount_cents', null)
        ->assertJsonPath('reply.cards.0.estimate_cents', 17000)
        ->assertJsonPath('reply.cards.0.due_on', '2026-10-15')
        // Conta paga sem forma dita: Pix.
        ->assertJsonPath('reply.cards.0.payment_type', 'pix');
    $url = "/api/v1/actions/{$reply->json('reply.cards.0.action.id')}/confirm";

    // A estimativa nunca vira despesa sozinha: sem o valor, não confirma.
    $this->postJson($url)->assertJsonValidationErrors('amount_cents');

    $this->postJson($url, ['payload' => [
        'bill_id' => $luz,
        'month' => '2026-10',
        'amount_cents' => 18240,
        'paid_on' => '2026-10-09',
        'payment_type' => 'pix',
    ]])->assertOk()->assertJsonPath('card.action.status', 'executed');

    $this->assertDatabaseHas('transactions', ['description' => 'Luz', 'amount_cents' => 18240, 'payment_type' => 'pix', 'source' => 'assistant']);
    $this->assertDatabaseHas('bill_payments', ['recurring_bill_id' => $luz, 'status' => 'paid']);
});

it('shows the impact before deleting, and asks which one when there are several', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    spend($house, 'Transporte', 2500, '2026-10-08', extra: ['description' => 'Uber']);
    spend($house, 'Transporte', 1800, '2026-10-05', extra: ['description' => 'Uber']);

    sayToAssistant('apaga o uber')
        ->assertJsonPath('reply.content', 'Encontrei 2 lançamentos: Uber R$ 25,00 em 08/10 e Uber R$ 18,00 em 05/10. Qual deles?')
        ->assertJsonPath('reply.cards', []);

    $reply = sayToAssistant('apaga o uber de ontem')
        ->assertJsonPath('reply.content', 'Vou apagar Uber, R$ 25,00, ontem (Transporte). Posso apagar?')
        ->assertJsonPath('reply.cards.0.type', 'delete_confirm')
        ->assertJsonPath('reply.cards.0.impact', 'Sai R$ 25,00 dos gastos de outubro.');

    $this->assertDatabaseCount('transactions', 2);

    $this->postJson("/api/v1/actions/{$reply->json('reply.cards.0.action.id')}/confirm")->assertOk();

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseMissing('transactions', ['amount_cents' => 2500]);
});

it('adds to the list right away and undoes it, putting back what was already bought', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $list = listOf($house, 'Mercado');

    // O café já tinha sido pego: volta para a lista, e o "Desfazer" devolve como estava.
    $cafe = $this->postJson("/api/v1/lists/{$list->id}/items", ['items' => [['text' => 'Café']]])->assertCreated()->json('added.0.id');
    $this->patchJson("/api/v1/list-items/{$cafe}", ['checked' => true])->assertOk();

    $reply = sayToAssistant('coloca leite, café e pão na lista')
        ->assertJsonPath('reply.content', 'Coloquei Leite, Café e Pão na lista Mercado.')
        ->assertJsonPath('reply.cards.0.type', 'list_update')
        ->assertJsonPath('reply.cards.0.action.status', 'executed')
        ->assertJsonPath('reply.cards.0.added', ['Leite', 'Café', 'Pão']);

    $this->assertDatabaseHas('list_items', ['text' => 'Café', 'checked_at' => null]);

    $this->postJson("/api/v1/actions/{$reply->json('reply.cards.0.action.id')}/undo")
        ->assertOk()
        ->assertJsonPath('card.action.status', 'undone');

    $this->assertDatabaseCount('list_items', 1);
    $this->assertDatabaseMissing('list_items', ['text' => 'Café', 'checked_at' => null]);
});

it('does not undo after two minutes, nor undo what needed a confirmation', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $list = sayToAssistant('coloca leite na lista')->json('reply.cards.0.action.id');
    $expense = sayToAssistant('gastei 12 de café')->json('reply.cards.0.action.id');
    $this->postJson("/api/v1/actions/{$expense}/confirm")->assertOk();

    $this->postJson("/api/v1/actions/{$expense}/undo")->assertJsonValidationErrors(['action' => 'não pode ser desfeita']);

    $this->travel(3)->minutes();
    $this->postJson("/api/v1/actions/{$list}/undo")->assertJsonValidationErrors(['action' => 'tempo para desfazer']);

    $this->assertDatabaseHas('list_items', ['text' => 'Leite']);
});

it('checks the items that were picked and undoes it', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $list = listOf($house, 'Mercado');
    $this->postJson("/api/v1/lists/{$list->id}/items", ['items' => [['text' => 'Leite'], ['text' => 'Pão']]])->assertSuccessful();

    $reply = sayToAssistant('peguei o leite e o ovo')
        ->assertJsonPath('reply.content', 'Marquei Leite como pego na lista Mercado. Não achei Ovo em aberto.')
        ->assertJsonPath('reply.cards.0.checked', ['Leite'])
        ->assertJsonPath('reply.cards.0.not_found', ['Ovo']);

    $this->assertDatabaseMissing('list_items', ['text' => 'Leite', 'checked_at' => null]);
    $this->assertDatabaseHas('list_items', ['text' => 'Pão', 'checked_at' => null]);

    $this->postJson("/api/v1/actions/{$reply->json('reply.cards.0.action.id')}/undo")->assertOk();

    $this->assertDatabaseHas('list_items', ['text' => 'Leite', 'checked_at' => null]);
});

it('creates a reminder and undoes it', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $reply = sayToAssistant('me lembra de pagar o IPVA dia 20')
        ->assertJsonPath('reply.content', 'Pronto: lembrete "Pagar o IPVA" para 20/10 às 09:00.')
        ->assertJsonPath('reply.cards.0.type', 'reminder_created')
        ->assertJsonPath('reply.cards.0.reminder.remind_at', '2026-10-20T09:00:00-03:00');

    $this->assertDatabaseHas('reminders', ['text' => 'Pagar o IPVA', 'created_by' => $leo->id]);

    $this->postJson("/api/v1/actions/{$reply->json('reply.cards.0.action.id')}/undo")->assertOk();

    $this->assertDatabaseMissing('reminders', ['text' => 'Pagar o IPVA']);
});

it('tells the AI, in the next turn, what the person did with the card', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $id = sayToAssistant('gastei 12 de café')->json('reply.cards.0.action.id');
    $this->postJson("/api/v1/actions/{$id}/confirm")->assertOk();

    // Uma IA espiã: guarda o histórico que recebeu e responde qualquer coisa.
    $spy = new class implements LlmProvider
    {
        public array $seen = [];

        public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse
        {
            $this->seen = $request->messages;

            return LlmResponse::text('Ok.');
        }
    };
    $this->app->instance(LlmProvider::class, $spy);

    sayToAssistant('valeu');

    expect(collect($spy->seen)->pluck('text')->implode("\n"))
        ->toContain('[ação: transactions_confirm confirmada e gravada]');
});
