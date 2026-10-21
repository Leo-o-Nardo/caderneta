<?php

use App\Events\Assistant\ActionUpdated;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
| As ações da parte 4.5: corrigir lançamento e apagar lista (com card para confirmar) e cadastrar
| conta fixa, receita fixa, orçamento e lista (gravadas na hora, com "Desfazer").
*/

// Numa sexta-feira, 09/10/2026, ao meio-dia de Brasília. A fila roda na hora (QUEUE_CONNECTION=sync).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
    Event::fake([
        AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class, ActionUpdated::class,
    ]);
});

function askAssistant(string $text): TestResponse
{
    return test()->postJson('/api/v1/conversation/messages', ['text' => $text])->assertStatus(202);
}

/** O id da ação do primeiro card da resposta, para os botões (confirmar, desfazer). */
function actionIdOf(TestResponse $reply): string
{
    return $reply->json('reply.cards.0.action.id');
}

it('corrects the amount of a transaction only after the confirmation, showing before and after', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    spend($house, 'Alimentação', 1200, '2026-10-08', extra: ['description' => 'Café']);

    $reply = askAssistant('o café de ontem foi 15, não 12')
        ->assertJsonPath('reply.content', 'Vou corrigir Café (R$ 12,00, ontem): valor de R$ 12,00 para R$ 15,00. Confere?')
        ->assertJsonPath('reply.cards.0.type', 'transaction_update')
        ->assertJsonPath('reply.cards.0.action.status', 'pending')
        ->assertJsonPath('reply.cards.0.transaction.description', 'Café');

    // O card volta do banco (jsonb), que reordena as chaves: compara sem olhar a ordem delas.
    expect($reply->json('reply.cards.0.changes'))->toEqual([
        ['field' => 'amount', 'label' => 'Valor', 'before' => 'R$ 12,00', 'after' => 'R$ 15,00'],
    ]);

    // Nada muda antes de confirmar.
    $this->assertDatabaseHas('transactions', ['description' => 'Café', 'amount_cents' => 1200]);

    $this->postJson('/api/v1/actions/'.actionIdOf($reply).'/confirm')
        ->assertOk()
        ->assertJsonPath('card.action.status', 'executed')
        // Depois de confirmado, o card continua mostrando o que era antes.
        ->assertJsonPath('card.changes.0.before', 'R$ 12,00');

    $this->assertDatabaseHas('transactions', ['description' => 'Café', 'amount_cents' => 1500]);
});

it('corrects the category and the payment of a transaction', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $nubank = cardOf($house, $leo, ['name' => 'Nubank']);
    Sanctum::actingAs($leo);
    spend($house, 'Transporte', 2500, '2026-10-08', extra: ['description' => 'Uber']);

    // Os cards voltam do banco (jsonb), que reordena as chaves: compara sem olhar a ordem delas.
    $category = askAssistant('muda a categoria do uber para lazer');
    expect($category->json('reply.cards.0.changes.0'))
        ->toEqual(['field' => 'category', 'label' => 'Categoria', 'before' => 'Transporte', 'after' => 'Lazer']);
    $this->postJson('/api/v1/actions/'.actionIdOf($category).'/confirm')->assertOk();

    $payment = askAssistant('o uber foi no nubank');
    expect($payment->json('reply.cards.0.changes.0'))
        ->toEqual(['field' => 'payment', 'label' => 'Pagamento', 'before' => 'Pix', 'after' => 'Crédito (Nubank)']);
    $this->postJson('/api/v1/actions/'.actionIdOf($payment).'/confirm')->assertOk();

    $this->assertDatabaseHas('transactions', [
        'description' => 'Uber',
        'category_id' => categoryOf($house, 'Lazer')->id,
        'payment_type' => 'credit_card',
        'card_id' => $nubank->id,
    ]);
});

it('does not correct what someone else launched, nor what is already right', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $bia = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member'], [$bia, 'member']]);

    Sanctum::actingAs($ana);
    spend($house, 'Alimentação', 1200, '2026-10-08', extra: ['description' => 'Café']);

    Sanctum::actingAs($bia);
    askAssistant('o café de ontem foi 15')
        ->assertJsonPath('reply.content', 'Só quem lançou (ou o dono da casa) pode corrigir esse lançamento.')
        ->assertJsonPath('reply.cards', []);

    Sanctum::actingAs($ana);
    askAssistant('o café de ontem foi 12')->assertJsonPath('reply.content', 'Café já está assim.');

    $this->assertDatabaseCount('pending_actions', 0);
});

it('registers a fixed bill right away and undoes it', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    // Hoje é dia 9: o dia 10 ainda vem neste mês.
    $reply = askAssistant('cadastra a internet de 99,90 todo dia 10')
        ->assertJsonPath('reply.content', 'Pronto: conta fixa Internet, R$ 99,90, vence todo dia 10 (Contas da casa).')
        ->assertJsonPath('reply.cards.0.type', 'bill_created')
        ->assertJsonPath('reply.cards.0.action.status', 'executed')
        ->assertJsonPath('reply.cards.0.bill.first_due_on', '2026-10-10');

    $this->assertDatabaseHas('recurring_bills', ['name' => 'Internet', 'expected_cents' => 9990, 'amount_varies' => false, 'due_day' => 10]);

    $this->postJson('/api/v1/actions/'.actionIdOf($reply).'/undo')->assertOk()->assertJsonPath('card.action.status', 'undone');

    $this->assertDatabaseMissing('recurring_bills', ['name' => 'Internet']);
});

it('registers a bill whose amount varies, and does not repeat a bill that exists', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    askAssistant('a água vence dia 20 e o valor muda')
        ->assertJsonPath('reply.content', 'Pronto: conta fixa Água, valor variável, vence todo dia 20 (Contas da casa).');

    $this->assertDatabaseHas('recurring_bills', ['name' => 'Água', 'expected_cents' => null, 'amount_varies' => true]);

    askAssistant('a água vence dia 20 e o valor muda')
        ->assertJsonPath('reply.content', 'Já existe a conta fixa Água. Para mudar o valor ou o dia, é pela Agenda.');

    $this->assertDatabaseCount('recurring_bills', 1);
});

it('registers the salary, launching today when the day is today, and undoes both', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $reply = askAssistant('recebo 5 mil de salário todo dia 9')
        ->assertJsonPath('reply.content', 'Pronto: receita fixa Salário, R$ 5.000,00 todo dia 9. Como o dia é hoje, a de hoje já entrou.')
        ->assertJsonPath('reply.cards.0.type', 'income_created')
        ->assertJsonPath('reply.cards.0.income.category.name', 'Salário')
        ->assertJsonPath('reply.cards.0.launched_today', true);

    $this->assertDatabaseHas('recurring_incomes', ['description' => 'Salário', 'amount_cents' => 500000, 'day_of_month' => 9]);
    $this->assertDatabaseHas('transactions', ['kind' => 'income', 'amount_cents' => 500000, 'occurred_on' => '2026-10-09', 'source' => 'recurring']);

    $this->postJson('/api/v1/actions/'.actionIdOf($reply).'/undo')->assertOk();

    $this->assertDatabaseCount('recurring_incomes', 0);
    $this->assertDatabaseCount('transactions', 0);

    // Dia que já passou: começa no mês que vem, sem lançar nada agora.
    askAssistant('recebo 2 mil de freela todo dia 5')
        ->assertJsonPath('reply.content', 'Pronto: receita fixa Freela, R$ 2.000,00 todo dia 5. A primeira cai em 05/11.')
        ->assertJsonPath('reply.cards.0.income.category.name', 'Renda extra')
        ->assertJsonPath('reply.cards.0.launched_today', false);

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseHas('recurring_incomes', ['description' => 'Freela', 'category_id' => categoryOf($house, 'Renda extra', 'income')->id]);
});

it('sets a budget and undoes it back to the previous limit', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado');
    $this->putJson("/api/v1/budgets/{$mercado->id}", ['limit_cents' => 60000])->assertSuccessful();
    spend($house, 'Mercado', 43200, '2026-10-05');

    $reply = askAssistant('limite de 800 no mercado')
        ->assertJsonPath('reply.content', 'Pronto: orçamento de Mercado em R$ 800,00 por mês (antes era R$ 600,00). Este mês já foram R$ 432,00.')
        ->assertJsonPath('reply.cards.0.type', 'budget_set')
        ->assertJsonPath('reply.cards.0.limit_cents', 80000)
        ->assertJsonPath('reply.cards.0.previous_cents', 60000)
        ->assertJsonPath('reply.cards.0.spent_cents', 43200);

    $this->assertDatabaseHas('budgets', ['category_id' => $mercado->id, 'limit_cents' => 80000]);

    $this->postJson('/api/v1/actions/'.actionIdOf($reply).'/undo')->assertOk();

    $this->assertDatabaseHas('budgets', ['category_id' => $mercado->id, 'limit_cents' => 60000]);

    // Sem orçamento antes: desfazer tira o orçamento.
    $delivery = askAssistant('orçamento de 300 pro delivery')->assertJsonPath('reply.cards.0.previous_cents', null);
    $this->postJson('/api/v1/actions/'.actionIdOf($delivery).'/undo')->assertOk();

    $this->assertDatabaseMissing('budgets', ['category_id' => categoryOf($house, 'Delivery')->id]);
});

it('creates a list with items and undoes it', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $reply = askAssistant('cria uma lista de churrasco com carne, carvão e gelo')
        ->assertJsonPath('reply.content', 'Pronto: criei a lista Churrasco com Carne, Carvão e Gelo.')
        ->assertJsonPath('reply.cards.0.type', 'list_created')
        ->assertJsonPath('reply.cards.0.items', ['Carne', 'Carvão', 'Gelo']);

    $this->assertDatabaseHas('lists', ['name' => 'Churrasco', 'kind' => 'shopping']);
    $this->assertDatabaseHas('list_items', ['text' => 'Carvão']);

    $this->postJson('/api/v1/actions/'.actionIdOf($reply).'/undo')->assertOk();

    $this->assertDatabaseMissing('lists', ['name' => 'Churrasco']);
    $this->assertDatabaseMissing('list_items', ['text' => 'Carvão']);
});

it('shows what goes with a list before deleting it, and keeps the default list', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $list = $this->postJson('/api/v1/lists', ['name' => 'Churrasco', 'kind' => 'shopping'])->assertCreated()->json('id');
    $carne = $this->postJson("/api/v1/lists/{$list}/items", ['items' => [['text' => 'Carne'], ['text' => 'Gelo']]])
        ->assertCreated()
        ->json('added.0.id');
    $this->patchJson("/api/v1/list-items/{$carne}", ['checked' => true])->assertOk();

    $reply = askAssistant('apaga a lista de churrasco')
        ->assertJsonPath('reply.content', 'Vou apagar a lista Churrasco. 2 itens somem junto (1 em aberto). Posso apagar?')
        ->assertJsonPath('reply.cards.0.type', 'list_delete')
        ->assertJsonPath('reply.cards.0.impact', '2 itens somem junto (1 em aberto).');

    $this->assertDatabaseHas('lists', ['name' => 'Churrasco']);

    $this->postJson('/api/v1/actions/'.actionIdOf($reply).'/confirm')->assertOk();

    $this->assertDatabaseMissing('lists', ['name' => 'Churrasco']);

    askAssistant('apaga a lista de mercado')
        ->assertJsonPath('reply.content', 'A lista Mercado é a padrão (é nela que cai o "coloca na lista"). Escolha outra lista padrão na tela de listas antes de apagar.')
        ->assertJsonPath('reply.cards', []);
});

it('does not give a viewer the new tools either', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    askAssistant('limite de 800 no mercado')
        ->assertJsonPath('reply.content', 'Você está como só leitura nesta casa: dá para perguntar, mas não lançar nem mudar nada.');

    $this->assertDatabaseCount('budgets', 0);
});
