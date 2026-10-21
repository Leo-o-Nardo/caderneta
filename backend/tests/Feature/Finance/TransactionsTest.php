<?php

use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function expensePayload(Category $category, array $overrides = []): array
{
    return array_merge([
        'kind' => 'expense',
        'amount_cents' => 4500,
        'description' => 'Almoço',
        'occurred_on' => '2026-10-07',
        'category_id' => $category->id,
        'payment_type' => 'pix',
    ], $overrides);
}

it('creates an expense paid with pix', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Alimentação')))
        ->assertCreated()
        ->assertJsonPath('kind', 'expense')
        ->assertJsonPath('amount_cents', 4500)
        ->assertJsonPath('occurred_on', '2026-10-07')
        ->assertJsonPath('category.name', 'Alimentação')
        ->assertJsonPath('payment_type', 'pix')
        ->assertJsonPath('card', null)
        ->assertJsonPath('invoice_month', null)
        ->assertJsonPath('visibility', 'household')
        ->assertJsonPath('source', 'app')
        ->assertJsonPath('created_by.name', 'Leo')
        ->assertJsonPath('can_edit', true);
});

it('puts a credit card expense in the right invoice', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['closing_day' => 3, 'due_day' => 10]);
    Sanctum::actingAs($leo);

    $payload = expensePayload(categoryOf($house, 'Compras'), ['payment_type' => 'credit_card', 'card_id' => $card->id]);

    $this->postJson('/api/v1/transactions', [...$payload, 'occurred_on' => '2026-10-02'])
        ->assertJsonPath('invoice_month', '2026-10')
        ->assertJsonPath('card.id', $card->id);

    $this->postJson('/api/v1/transactions', [...$payload, 'occurred_on' => '2026-10-07'])
        ->assertJsonPath('invoice_month', '2026-11');
});

it('requires a card for credit card expenses', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Compras'), ['payment_type' => 'credit_card']))
        ->assertJsonValidationErrors(['card_id' => 'Escolha o cartão usado na compra.']);
});

it('creates an income without payment type', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $income = [
        'kind' => 'income',
        'amount_cents' => 540000,
        'description' => 'Salário',
        'occurred_on' => '2026-10-05',
        'category_id' => categoryOf($house, 'Salário', 'income')->id,
    ];

    $this->postJson('/api/v1/transactions', $income)
        ->assertCreated()
        ->assertJsonPath('payment_type', null);

    $this->postJson('/api/v1/transactions', [...$income, 'payment_type' => 'pix'])
        ->assertJsonValidationErrors('payment_type');
});

it('rejects a category of the wrong kind', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Salário', 'income')))
        ->assertJsonValidationErrors(['category_id' => 'Essa categoria não combina com o tipo do lançamento.']);
});

it('rejects a category or a card of another household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $leoHouse = householdOf($leo);
    $anaHouse = householdOf($ana);
    $anaCard = cardOf($anaHouse, $ana);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($anaHouse, 'Mercado')))
        ->assertJsonValidationErrors('category_id');

    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($leoHouse, 'Mercado'), [
        'payment_type' => 'credit_card',
        'card_id' => $anaCard->id,
    ]))->assertJsonValidationErrors('card_id');
});

it('hides private transactions from the other members', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($ana);
    $id = $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Compras'), [
        'description' => 'Presente do Leo',
        'visibility' => 'private',
    ]))->assertCreated()->json('id');

    $this->getJson('/api/v1/transactions')->assertJsonCount(1, 'data');

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/transactions')->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/transactions/{$id}")->assertNotFound();
});

it('lets members change only their own transactions and the owner change all of them', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();
    $category = categoryOf($house, 'Mercado');

    Sanctum::actingAs($leo);
    $leoId = $this->postJson('/api/v1/transactions', expensePayload($category))->json('id');

    Sanctum::actingAs($ana);
    $anaId = $this->postJson('/api/v1/transactions', expensePayload($category))->json('id');
    $this->patchJson("/api/v1/transactions/{$leoId}", ['amount_cents' => 1])->assertForbidden();
    $this->deleteJson("/api/v1/transactions/{$leoId}")->assertForbidden();
    $this->getJson("/api/v1/transactions/{$leoId}")->assertJsonPath('can_edit', false);

    Sanctum::actingAs($leo);
    $this->patchJson("/api/v1/transactions/{$anaId}", ['amount_cents' => 5000])
        ->assertOk()
        ->assertJsonPath('amount_cents', 5000);
});

it('moves the transaction to the right invoice when it is edited', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['closing_day' => 3, 'due_day' => 10]);
    Sanctum::actingAs($leo);

    $id = $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Compras'), [
        'payment_type' => 'credit_card',
        'card_id' => $card->id,
        'occurred_on' => '2026-10-02',
    ]))->json('id');

    $this->patchJson("/api/v1/transactions/{$id}", ['occurred_on' => '2026-10-05'])
        ->assertJsonPath('invoice_month', '2026-11');

    $this->patchJson("/api/v1/transactions/{$id}", ['payment_type' => 'pix'])
        ->assertJsonPath('card', null)
        ->assertJsonPath('invoice_month', null);
});

it('filters by month, category and text', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Transporte'), ['description' => 'Uber']));
    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Mercado'), ['description' => 'Feira']));
    $this->postJson('/api/v1/transactions', expensePayload(categoryOf($house, 'Mercado'), [
        'description' => 'Mercado do mês',
        'occurred_on' => '2026-09-30',
    ]));

    $this->getJson('/api/v1/transactions?month=2026-10')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/transactions?category_id='.categoryOf($house, 'Mercado')->id)->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/transactions?q=uber')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.description', 'Uber');
});

it('lists the newest transactions first', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $category = categoryOf($house, 'Mercado');

    $this->postJson('/api/v1/transactions', expensePayload($category, ['description' => 'Antigo', 'occurred_on' => '2026-10-01']));
    $this->postJson('/api/v1/transactions', expensePayload($category, ['description' => 'Novo', 'occurred_on' => '2026-10-09']));

    $this->getJson('/api/v1/transactions')
        ->assertJsonPath('data.0.description', 'Novo')
        ->assertJsonPath('data.1.description', 'Antigo');
});

it('filters by a date range', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $category = categoryOf($house, 'Mercado');

    foreach (['2026-10-06', '2026-10-07', '2026-10-07', '2026-10-08'] as $date) {
        $this->postJson('/api/v1/transactions', expensePayload($category, ['occurred_on' => $date]));
    }

    $this->getJson('/api/v1/transactions?from=2026-10-07&to=2026-10-07')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/transactions?from=2026-10-07')->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/transactions?from=2026-10-08&to=2026-10-07')
        ->assertJsonValidationErrors(['to' => 'A data final deve ser igual ou posterior a']);
});

it('pages through the transactions with a cursor', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $category = categoryOf($house, 'Mercado');

    foreach (range(1, 5) as $day) {
        $this->postJson('/api/v1/transactions', expensePayload($category, [
            'description' => "Dia {$day}",
            'occurred_on' => sprintf('2026-10-%02d', $day),
        ]));
    }

    $first = $this->getJson('/api/v1/transactions?per_page=2')
        ->assertJsonPath('data.0.description', 'Dia 5')
        ->assertJsonPath('data.1.description', 'Dia 4');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $this->getJson("/api/v1/transactions?per_page=2&cursor={$cursor}")
        ->assertJsonPath('data.0.description', 'Dia 3')
        ->assertJsonPath('data.1.description', 'Dia 2');
});

it('does not show transactions of another household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo);
    $anaHouse = householdOf($ana);

    Sanctum::actingAs($ana);
    $id = $this->postJson('/api/v1/transactions', expensePayload(categoryOf($anaHouse, 'Mercado')))->json('id');

    Sanctum::actingAs($leo);
    $this->getJson("/api/v1/transactions/{$id}")->assertNotFound();
    $this->patchJson("/api/v1/transactions/{$id}", ['amount_cents' => 1])->assertNotFound();
    $this->getJson('/api/v1/transactions')->assertJsonCount(0, 'data');
});
