<?php

use App\Models\Card;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

function cardPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nubank',
        'closing_day' => 3,
        'due_day' => 10,
        'limit_cents' => 500000,
        'color' => '#7E5490',
    ], $overrides);
}

it('creates a card for the user and makes the first one the default', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/cards', cardPayload())
        ->assertCreated()
        ->assertJsonPath('name', 'Nubank')
        ->assertJsonPath('is_mine', true)
        ->assertJsonPath('is_default', true)
        ->assertJsonPath('owner.name', 'Leo');

    $this->postJson('/api/v1/cards', cardPayload(['name' => 'Inter']))
        ->assertCreated()
        ->assertJsonPath('is_default', false);
});

it('switches the default card', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $first = $this->postJson('/api/v1/cards', cardPayload())->json('id');
    $second = $this->postJson('/api/v1/cards', cardPayload(['name' => 'Inter']))->json('id');

    $this->patchJson("/api/v1/cards/{$second}", ['is_default' => true])->assertJsonPath('is_default', true);

    $this->assertDatabaseHas('cards', ['id' => $first, 'is_default' => false]);
    $this->assertDatabaseHas('cards', ['id' => $second, 'is_default' => true]);
});

it('archives a card and hands the default to another one', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $first = $this->postJson('/api/v1/cards', cardPayload())->json('id');
    $second = $this->postJson('/api/v1/cards', cardPayload(['name' => 'Inter']))->json('id');

    $this->deleteJson("/api/v1/cards/{$first}")->assertNoContent();

    $this->getJson('/api/v1/cards')->assertJsonCount(1)->assertJsonPath('0.id', $second);
    $this->assertDatabaseHas('cards', ['id' => $second, 'is_default' => true]);
});

it('lists the cards of everyone in the household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create(['name' => 'Ana']);
    $house = householdOf($leo, [[$ana, 'member']]);
    Card::factory()->for($house)->for($ana)->create(['name' => 'Cartão da Ana']);
    $leo->refresh();
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/cards', cardPayload());

    $this->getJson('/api/v1/cards')
        ->assertJsonCount(2)
        ->assertJsonFragment(['name' => 'Cartão da Ana', 'is_mine' => false]);
});

it('only lets the owner of a card change it', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $anaCard = Card::factory()->for($house)->for($ana)->create();
    $leo->refresh();
    Sanctum::actingAs($leo);

    $this->patchJson("/api/v1/cards/{$anaCard->id}", ['name' => 'Meu agora'])->assertForbidden();
    $this->deleteJson("/api/v1/cards/{$anaCard->id}")->assertForbidden();
});

it('shows the invoice a purchase made today would go to', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/cards', cardPayload(['closing_day' => 3, 'due_day' => 10]))
        ->assertJsonPath('current_invoice', [
            'month' => '2026-11',
            'closes_on' => '2026-11-03',
            'due_on' => '2026-11-10',
            'total_cents' => 0,
        ]);
});

it('adds up the open invoice and the limit in use', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['closing_day' => 3, 'due_day' => 10, 'limit_cents' => 500000]);
    $category = categoryOf($house, 'Compras');
    Sanctum::actingAs($leo);

    $purchase = fn (string $date, int $cents) => $this->postJson('/api/v1/transactions', [
        'kind' => 'expense',
        'amount_cents' => $cents,
        'description' => 'Compra',
        'occurred_on' => $date,
        'category_id' => $category->id,
        'payment_type' => 'credit_card',
        'card_id' => $card->id,
    ])->assertCreated();

    $purchase('2026-09-01', 5000);  // fatura de setembro, já vencida: não usa mais o limite
    $purchase('2026-10-01', 7000);  // fatura de outubro: fechou dia 3, mas só vence dia 10
    $purchase('2026-10-05', 10000); // fatura de novembro, a aberta
    $this->postJson('/api/v1/installment-plans', [
        'description' => 'TV',
        'category_id' => $category->id,
        'card_id' => $card->id,
        'installments' => 3,
        'total_cents' => 30000,
        'purchased_on' => '2026-10-07', // novembro, dezembro e janeiro
    ])->assertCreated();

    $this->getJson('/api/v1/cards')
        ->assertJsonPath('0.current_invoice.month', '2026-11')
        ->assertJsonPath('0.current_invoice.total_cents', 20000)
        ->assertJsonPath('0.limit_used_cents', 47000);
});

it('validates the closing and due days', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/cards', cardPayload(['closing_day' => 0, 'due_day' => 32]))
        ->assertJsonValidationErrors(['closing_day', 'due_day']);
});

it('does not let a viewer add cards', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    $this->postJson('/api/v1/cards', cardPayload())->assertForbidden();
});

it('does not show or change the cards of another household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo);
    $anaCard = Card::factory()->for(householdOf($ana))->for($ana)->create();
    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/cards')->assertJsonCount(0);

    // 404 e não 403: o cartão nem é encontrado, porque a busca é limitada à casa do Leo.
    $this->patchJson("/api/v1/cards/{$anaCard->id}", ['name' => 'Hackeado'])->assertNotFound();
    $this->deleteJson("/api/v1/cards/{$anaCard->id}")->assertNotFound();
});
