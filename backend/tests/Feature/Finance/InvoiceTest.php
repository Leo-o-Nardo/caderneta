<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('adds up the purchases and installments of an invoice', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['closing_day' => 3, 'due_day' => 10]);
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
    ]);

    $purchase('2026-10-01', 7000);  // fatura de outubro
    $purchase('2026-10-05', 10000); // fatura de novembro
    $this->postJson('/api/v1/installment-plans', [
        'description' => 'TV',
        'category_id' => $category->id,
        'card_id' => $card->id,
        'installments' => 3,
        'total_cents' => 30000,
        'purchased_on' => '2026-10-07', // 1ª parcela na fatura de novembro
    ]);

    $this->getJson("/api/v1/cards/{$card->id}/invoice?month=2026-11")
        ->assertOk()
        ->assertJsonPath('month', '2026-11')
        ->assertJsonPath('closes_on', '2026-11-03')
        ->assertJsonPath('due_on', '2026-11-10')
        ->assertJsonPath('total_cents', 20000)
        ->assertJsonPath('installments_cents', 10000)
        ->assertJsonCount(2, 'items');
});

it('shows the invoice open today and tells whether an invoice is closed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['closing_day' => 3, 'due_day' => 10]);
    Sanctum::actingAs($leo);

    $this->getJson("/api/v1/cards/{$card->id}/invoice")
        ->assertJsonPath('month', '2026-11')
        ->assertJsonPath('is_closed', false);

    $this->getJson("/api/v1/cards/{$card->id}/invoice?month=2026-10")
        ->assertJsonPath('is_closed', true);
});

it('leaves the private purchases of other members out of the invoice', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();
    $anaCard = cardOf($house, $ana, ['closing_day' => 3, 'due_day' => 10]);

    Sanctum::actingAs($ana);
    $this->postJson('/api/v1/transactions', [
        'kind' => 'expense',
        'amount_cents' => 8000,
        'description' => 'Presente',
        'occurred_on' => '2026-10-05',
        'category_id' => categoryOf($house, 'Compras')->id,
        'payment_type' => 'credit_card',
        'card_id' => $anaCard->id,
        'visibility' => 'private',
    ])->assertCreated();

    $this->getJson("/api/v1/cards/{$anaCard->id}/invoice?month=2026-11")->assertJsonPath('total_cents', 8000);

    Sanctum::actingAs($leo);
    $this->getJson("/api/v1/cards/{$anaCard->id}/invoice?month=2026-11")->assertJsonPath('total_cents', 0);
});
