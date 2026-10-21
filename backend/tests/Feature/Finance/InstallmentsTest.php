<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function installmentPayload(Category $category, Card $card, array $overrides = []): array
{
    return array_merge([
        'description' => 'TV',
        'category_id' => $category->id,
        'card_id' => $card->id,
        'installments' => 3,
        'total_cents' => 10000,
        'purchased_on' => '2026-10-07',
    ], $overrides);
}

it('creates the installments with the right amounts, dates and invoices', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['closing_day' => 3, 'due_day' => 10]);
    Sanctum::actingAs($leo);

    $response = $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card))
        ->assertCreated()
        ->assertJsonPath('total_cents', 10000)
        ->assertJsonPath('installments', 3)
        ->assertJsonCount(3, 'transactions');

    $installments = collect($response->json('transactions'));

    expect($installments->pluck('amount_cents')->all())->toBe([3334, 3333, 3333])
        ->and($installments->pluck('occurred_on')->all())->toBe(['2026-10-07', '2026-11-07', '2026-12-07'])
        ->and($installments->pluck('invoice_month')->all())->toBe(['2026-11', '2026-12', '2027-01'])
        ->and($installments->pluck('installment.number')->all())->toBe([1, 2, 3])
        ->and($installments->pluck('installment.count')->unique()->all())->toBe([3]);
});

it('accepts the installment value instead of the total', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo);
    Sanctum::actingAs($leo);

    $payload = installmentPayload(categoryOf($house, 'Compras'), $card, ['installments' => 10, 'installment_cents' => 25000]);
    unset($payload['total_cents']);

    $this->postJson('/api/v1/installment-plans', $payload)
        ->assertCreated()
        ->assertJsonPath('total_cents', 250000)
        ->assertJsonPath('transactions.9.amount_cents', 25000);
});

it('does not accept the total and the installment value together', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card, [
        'installment_cents' => 3000,
    ]))->assertJsonValidationErrors('total_cents');
});

it('keeps the installment dates inside short months', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card, [
        'purchased_on' => '2027-01-31',
    ]))
        ->assertJsonPath('transactions.0.occurred_on', '2027-01-31')
        ->assertJsonPath('transactions.1.occurred_on', '2027-02-28')
        ->assertJsonPath('transactions.2.occurred_on', '2027-03-31');
});

it('does not change a single installment', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo);
    Sanctum::actingAs($leo);

    $installmentId = $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card))
        ->json('transactions.1.id');

    $this->patchJson("/api/v1/transactions/{$installmentId}", ['amount_cents' => 1])
        ->assertJsonValidationErrors(['transaction' => 'Edite ou apague pelo parcelamento.']);
    $this->deleteJson("/api/v1/transactions/{$installmentId}")
        ->assertJsonValidationErrors('transaction');
});

it('changes every installment through the plan', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo);
    Sanctum::actingAs($leo);

    $planId = $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card))->json('id');

    $this->patchJson("/api/v1/installment-plans/{$planId}", [
        'description' => 'TV da sala',
        'category_id' => categoryOf($house, 'Moradia')->id,
    ])
        ->assertOk()
        ->assertJsonPath('description', 'TV da sala')
        ->assertJsonPath('transactions.2.description', 'TV da sala')
        ->assertJsonPath('transactions.2.category.name', 'Moradia');
});

it('deletes every installment with the plan', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo);
    Sanctum::actingAs($leo);

    $planId = $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card))->json('id');

    $this->deleteJson("/api/v1/installment-plans/{$planId}")->assertNoContent();

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseCount('installment_plans', 0);
});

it('only lets the author or the owner change a plan', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();
    $card = cardOf($house, $leo);

    Sanctum::actingAs($leo);
    $planId = $this->postJson('/api/v1/installment-plans', installmentPayload(categoryOf($house, 'Compras'), $card))->json('id');

    Sanctum::actingAs($ana);
    $this->patchJson("/api/v1/installment-plans/{$planId}", ['description' => 'Minha'])->assertForbidden();
    $this->deleteJson("/api/v1/installment-plans/{$planId}")->assertForbidden();
});
