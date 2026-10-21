<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('sets a budget and shows how much of it was spent', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->putJson('/api/v1/budgets/'.categoryOf($house, 'Mercado')->id, ['limit_cents' => 80000])->assertOk();
    spend($house, 'Mercado', 40000);
    spend($house, 'Mercado', 24000);
    spend($house, 'Mercado', 50000, '2026-11-02'); // outro mês, fica de fora

    $this->getJson('/api/v1/budgets?month=2026-10')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.category.name', 'Mercado')
        ->assertJsonPath('0.limit_cents', 80000)
        ->assertJsonPath('0.spent_cents', 64000)
        ->assertJsonPath('0.remaining_cents', 16000)
        ->assertJsonPath('0.percent', 80)
        ->assertJsonPath('0.status', 'warning');
});

it('marks budgets as ok, warning or over', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    foreach (['Mercado' => 80000, 'Delivery' => 30000, 'Lazer' => 40000] as $category => $limit) {
        $this->putJson('/api/v1/budgets/'.categoryOf($house, $category)->id, ['limit_cents' => $limit]);
    }
    spend($house, 'Mercado', 79999);
    spend($house, 'Delivery', 41280);
    spend($house, 'Lazer', 9500);

    $budgets = collect($this->getJson('/api/v1/budgets?month=2026-10')->json())->keyBy('category.name');

    expect($budgets['Mercado']['status'])->toBe('warning')
        ->and($budgets['Delivery']['status'])->toBe('over')
        ->and($budgets['Delivery']['remaining_cents'])->toBe(-11280)
        ->and($budgets['Delivery']['percent'])->toBe(137)
        ->and($budgets['Lazer']['status'])->toBe('ok')
        ->and($budgets['Lazer']['percent'])->toBe(23);
});

it('updates the budget of a category instead of creating another', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado')->id;

    $this->putJson("/api/v1/budgets/{$mercado}", ['limit_cents' => 80000]);
    $this->putJson("/api/v1/budgets/{$mercado}", ['limit_cents' => 90000])->assertJsonPath('limit_cents', 90000);

    $this->assertDatabaseCount('budgets', 1);
});

it('only accepts budgets for expense categories', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->putJson('/api/v1/budgets/'.categoryOf($house, 'Salário', 'income')->id, ['limit_cents' => 1000])
        ->assertJsonValidationErrors('category');
});

it('removes a budget', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado')->id;

    $this->putJson("/api/v1/budgets/{$mercado}", ['limit_cents' => 80000]);
    $this->deleteJson("/api/v1/budgets/{$mercado}")->assertNoContent();

    $this->getJson('/api/v1/budgets')->assertJsonCount(0);
});

it('shows a category budget with the average of the 3 previous months', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado')->id;

    $this->putJson("/api/v1/budgets/{$mercado}", ['limit_cents' => 80000]);
    spend($house, 'Mercado', 100000, '2026-06-15'); // antes da janela de 3 meses, fica de fora
    spend($house, 'Mercado', 30000, '2026-07-10');
    spend($house, 'Mercado', 60000, '2026-08-05');
    spend($house, 'Mercado', 90000, '2026-09-20');
    spend($house, 'Delivery', 5000, '2026-09-21'); // outra categoria
    spend($house, 'Mercado', 20000, '2026-10-03');

    $this->getJson("/api/v1/budgets/{$mercado}?month=2026-10")
        ->assertOk()
        ->assertJsonPath('category.name', 'Mercado')
        ->assertJsonPath('limit_cents', 80000)
        ->assertJsonPath('spent_cents', 20000)
        ->assertJsonPath('average_cents', 60000)
        ->assertJsonPath('average_months', 3);
});

it('averages only the months since the first expense of the household', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado')->id;

    spend($house, 'Mercado', 40000, '2026-09-12');
    spend($house, 'Mercado', 10000, '2026-10-02');

    // Começou a usar em setembro: a média é só de setembro, não de 3 meses com 2 vazios.
    $this->getJson("/api/v1/budgets/{$mercado}?month=2026-10")
        ->assertOk()
        ->assertJsonPath('limit_cents', null)
        ->assertJsonPath('spent_cents', 10000)
        ->assertJsonPath('average_cents', 40000)
        ->assertJsonPath('average_months', 1);

    // Em setembro ainda não havia nenhum mês fechado.
    $this->getJson("/api/v1/budgets/{$mercado}?month=2026-09")
        ->assertJsonPath('average_cents', null)
        ->assertJsonPath('average_months', 0);
});

it('does not show the budget of income or archived categories', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $pets = categoryOf($house, 'Pets');
    $pets->forceFill(['archived_at' => now()])->save();

    $this->getJson('/api/v1/budgets/'.categoryOf($house, 'Salário', 'income')->id)->assertJsonValidationErrors('category');
    $this->getJson("/api/v1/budgets/{$pets->id}")->assertNotFound();
});

it('counts the private expenses only for whoever made them', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($leo);
    $this->putJson('/api/v1/budgets/'.categoryOf($house, 'Compras')->id, ['limit_cents' => 50000]);

    Sanctum::actingAs($ana);
    spend($house, 'Compras', 20000, extra: ['visibility' => 'private']);
    $this->getJson('/api/v1/budgets?month=2026-10')->assertJsonPath('0.spent_cents', 20000);

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/budgets?month=2026-10')->assertJsonPath('0.spent_cents', 0);
});

it('does not let a viewer set budgets nor touch another household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'viewer']]);
    $otherHouse = householdOf(User::factory()->create());
    $leo->refresh();

    Sanctum::actingAs($ana);
    $this->putJson('/api/v1/budgets/'.categoryOf($house, 'Mercado')->id, ['limit_cents' => 1000])->assertForbidden();

    Sanctum::actingAs($leo);
    $this->putJson('/api/v1/budgets/'.categoryOf($otherHouse, 'Mercado')->id, ['limit_cents' => 1000])->assertNotFound();
});
