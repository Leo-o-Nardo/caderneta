<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('adds up the month by kind, category and member', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $ana = User::factory()->create(['name' => 'Ana']);
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($leo);
    spend($house, 'Salário', 500000, extra: ['kind' => 'income', 'payment_type' => null]);
    spend($house, 'Mercado', 30000);
    spend($house, 'Delivery', 12000);
    spend($house, 'Mercado', 99999, '2026-11-01'); // outro mês, fica de fora

    Sanctum::actingAs($ana);
    spend($house, 'Mercado', 5000);

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/summary?month=2026-10')
        ->assertOk()
        ->assertJsonPath('month', '2026-10')
        ->assertJsonPath('income_cents', 500000)
        ->assertJsonPath('expense_cents', 47000)
        ->assertJsonPath('balance_cents', 453000)
        ->assertJsonPath('by_category.0.name', 'Mercado')
        ->assertJsonPath('by_category.0.total_cents', 35000)
        ->assertJsonPath('by_category.1.name', 'Delivery')
        ->assertJsonPath('by_member.0.name', 'Leo')
        ->assertJsonPath('by_member.0.total_cents', 42000)
        ->assertJsonPath('by_member.1.name', 'Ana')
        ->assertJsonPath('by_member.1.total_cents', 5000);
});

it('counts the private expenses only for whoever made them', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($ana);
    spend($house, 'Compras', 8000, extra: ['visibility' => 'private']);
    spend($house, 'Mercado', 2000);

    $this->getJson('/api/v1/summary?month=2026-10')->assertJsonPath('expense_cents', 10000);

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/summary?month=2026-10')
        ->assertJsonPath('expense_cents', 2000)
        ->assertJsonCount(1, 'by_category');
});

it('lists the invoice of each card closing in the month', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $card = cardOf($house, $leo, ['name' => 'Nubank', 'closing_day' => 3, 'due_day' => 10]);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/installment-plans', [
        'description' => 'TV',
        'category_id' => categoryOf($house, 'Compras')->id,
        'card_id' => $card->id,
        'installments' => 10,
        'installment_cents' => 25000,
        'purchased_on' => '2026-10-07',
    ])->assertCreated();

    $this->getJson('/api/v1/summary?month=2026-11')
        ->assertJsonPath('invoices.0.card.name', 'Nubank')
        ->assertJsonPath('invoices.0.closes_on', '2026-11-03')
        ->assertJsonPath('invoices.0.total_cents', 25000);
});

it('includes the budgets of the month and the bills to pay', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->putJson('/api/v1/budgets/'.categoryOf($house, 'Mercado')->id, ['limit_cents' => 80000])->assertOk();
    spend($house, 'Mercado', 64000, '2026-10-05');
    $this->postJson('/api/v1/bills', [
        'name' => 'Luz',
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'due_day' => 8,
    ])->assertCreated();

    $this->getJson('/api/v1/summary?month=2026-10')
        ->assertJsonPath('budgets.0.category.name', 'Mercado')
        ->assertJsonPath('budgets.0.percent', 80)
        ->assertJsonPath('budgets.0.status', 'warning')
        ->assertJsonPath('upcoming_bills.0.bill.name', 'Luz')
        ->assertJsonPath('upcoming_bills.0.due_on', '2026-10-08')
        ->assertJsonPath('upcoming_bills.0.days_until_due', 1);
});
