<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Depois de uma requisição, o contexto da casa continua preenchido até o fim do teste, então
// consultas Eloquent diretas sairiam filtradas por ele. Por isso as verificações usam assertDatabaseHas.

it('creates the default categories with every household', function () {
    $house = householdOf(User::factory()->create());

    expect($house->categories()->where('kind', 'expense')->count())->toBe(13)
        ->and($house->categories()->where('kind', 'income')->count())->toBe(3);
});

it('lists the active categories, optionally by kind', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $house->categories()->where('name', 'Pets')->update(['archived_at' => now()]);
    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(15);
    $this->getJson('/api/v1/categories?kind=income')->assertJsonCount(3);
    $this->getJson('/api/v1/categories?kind=expense')
        ->assertJsonCount(12)
        ->assertJsonMissing(['name' => 'Pets'])
        ->assertJsonPath('0.name', 'Mercado')
        ->assertJsonPath('0.icon', 'cart');
});

it('creates, renames and archives a category', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $id = $this->postJson('/api/v1/categories', [
        'kind' => 'expense',
        'name' => 'Academia',
        'icon' => 'dumbbell',
        'color' => '#357A8F',
    ])->assertCreated()->json('id');

    $this->assertDatabaseHas('categories', ['id' => $id, 'household_id' => $house->id]);

    $this->patchJson("/api/v1/categories/{$id}", ['name' => 'Academia e esportes'])
        ->assertOk()
        ->assertJsonPath('name', 'Academia e esportes')
        ->assertJsonPath('kind', 'expense');

    $this->deleteJson("/api/v1/categories/{$id}")->assertNoContent();

    $this->assertDatabaseHas('categories', ['id' => $id]);
    $this->assertDatabaseMissing('categories', ['id' => $id, 'archived_at' => null]);
    $this->getJson('/api/v1/categories')->assertJsonMissing(['name' => 'Academia e esportes']);
});

it('only accepts icons from the known list', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/categories', [
        'kind' => 'expense',
        'name' => 'Viagem',
        'icon' => 'foguete',
        'color' => '#357A8F',
    ])->assertJsonValidationErrors('icon');

    $this->patchJson('/api/v1/categories/'.categoryOf($house, 'Lazer')->id, ['icon' => 'plane'])
        ->assertOk()
        ->assertJsonPath('icon', 'plane');
});

it('removes the budget of a category when it is archived', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $lazer = categoryOf($house, 'Lazer')->id;

    $this->putJson("/api/v1/budgets/{$lazer}", ['limit_cents' => 40000])->assertOk();
    $this->deleteJson("/api/v1/categories/{$lazer}")->assertNoContent();

    $this->assertDatabaseMissing('budgets', ['category_id' => $lazer]);
    $this->getJson('/api/v1/budgets')->assertJsonCount(0);
});

it('keeps the categories in the order the household chose', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $expenses = collect($this->getJson('/api/v1/categories?kind=expense')->json());
    // Pets e Delivery para o topo, o resto na ordem de antes.
    $first = $expenses->whereIn('name', ['Pets', 'Delivery'])->sortByDesc('name')->pluck('id');
    $order = $first->concat($expenses->pluck('id')->diff($first))->values()->all();

    $this->putJson('/api/v1/categories/order', ['kind' => 'expense', 'ids' => $order])->assertNoContent();

    $this->getJson('/api/v1/categories?kind=expense')
        ->assertJsonPath('0.name', 'Pets')
        ->assertJsonPath('1.name', 'Delivery')
        ->assertJsonPath('2.name', 'Mercado');

    // As receitas têm a própria ordem e não mudam.
    $this->getJson('/api/v1/categories?kind=income')->assertJsonPath('0.name', 'Salário');
});

it('puts a new category at the end of its kind', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/categories', [
        'kind' => 'expense',
        'name' => 'Viagem',
        'icon' => 'plane',
        'color' => '#357A8F',
    ])->assertCreated();

    $this->getJson('/api/v1/categories?kind=expense')
        ->assertJsonCount(14)
        ->assertJsonPath('13.name', 'Viagem');
});

it('rejects an order without exactly the active categories of the kind', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $otherHouse = householdOf(User::factory()->create());
    Sanctum::actingAs($leo);

    $ids = collect($this->getJson('/api/v1/categories?kind=expense')->json())->pluck('id');
    $salary = categoryOf($house, 'Salário', 'income')->id;
    $foreign = categoryOf($otherHouse, 'Mercado')->id;

    foreach ([
        $ids->slice(1)->values()->all(),                // falta uma
        [...$ids->all(), $salary],                       // uma de receita
        [...$ids->slice(1)->values()->all(), $foreign],  // uma de outra casa
    ] as $order) {
        $this->putJson('/api/v1/categories/order', ['kind' => 'expense', 'ids' => $order])
            ->assertJsonValidationErrors('ids');
    }
});

it('rejects a repeated name in the same kind, ignoring case', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $payload = ['kind' => 'expense', 'name' => 'mercado', 'icon' => 'cart', 'color' => '#4F7F4F'];

    $this->postJson('/api/v1/categories', $payload)->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/categories', [...$payload, 'kind' => 'income'])->assertCreated();
});

it('lets viewers read the categories but not change them', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    $this->getJson('/api/v1/categories')->assertOk();
    $this->postJson('/api/v1/categories', [
        'kind' => 'expense',
        'name' => 'Academia',
        'icon' => 'dumbbell',
        'color' => '#357A8F',
    ])->assertForbidden();
});

it('does not touch the categories of another household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo);
    $anaCategory = householdOf($ana)->categories()->first();
    Sanctum::actingAs($leo);

    $this->patchJson("/api/v1/categories/{$anaCategory->id}", ['name' => 'Hackeada'])->assertNotFound();
    $this->deleteJson("/api/v1/categories/{$anaCategory->id}")->assertNotFound();

    $this->assertDatabaseHas('categories', [
        'id' => $anaCategory->id,
        'name' => $anaCategory->name,
        'archived_at' => null,
    ]);
});
