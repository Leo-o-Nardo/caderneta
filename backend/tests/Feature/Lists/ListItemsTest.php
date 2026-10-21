<?php

use App\Models\HouseholdList;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/** Adiciona itens pelo texto e devolve a resposta. */
function addItems(HouseholdList $list, array $texts)
{
    return test()->postJson("/api/v1/lists/{$list->id}/items", [
        'items' => array_map(fn (string $text) => ['text' => $text], $texts),
    ]);
}

it('adds several items at once', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $market = listOf($house, 'Mercado');

    $this->postJson("/api/v1/lists/{$market->id}/items", ['items' => [
        ['text' => 'Leite', 'quantity' => '2'],
        ['text' => 'Café'],
        ['text' => 'Sabão em pó'],
    ]])
        ->assertCreated()
        ->assertJsonCount(3, 'added')
        ->assertJsonCount(0, 'existing')
        ->assertJsonPath('added.0.quantity', '2')
        ->assertJsonPath('added.0.is_checked', false);
});

it('does not repeat an item that is still open', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $market = listOf($house, 'Mercado');

    addItems($market, ['Café']);

    addItems($market, ['cafe', '  CAFÉ ', 'Pão', 'pao'])
        ->assertCreated()
        ->assertJsonCount(1, 'added')
        ->assertJsonPath('added.0.text', 'Pão')
        ->assertJsonCount(1, 'existing')
        ->assertJsonPath('existing.0.text', 'Café');

    $this->assertDatabaseCount('list_items', 2);
});

it('reopens an item that was already picked', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $market = listOf($house, 'Mercado');

    $id = addItems($market, ['Leite'])->json('added.0.id');
    $this->patchJson("/api/v1/list-items/{$id}", ['checked' => true]);

    addItems($market, ['leite'])
        ->assertCreated()
        ->assertJsonPath('added.0.id', $id)
        ->assertJsonPath('added.0.is_checked', false);

    $this->assertDatabaseCount('list_items', 1);
});

it('checks an item with a tap and records who picked it', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create(['name' => 'Ana']);
    $house = householdOf($leo, [[$ana, 'member']]);
    $market = listOf($house, 'Mercado');

    Sanctum::actingAs($ana);
    $id = addItems($market, ['Leite'])->json('added.0.id');

    $this->patchJson("/api/v1/list-items/{$id}", ['checked' => true])
        ->assertOk()
        ->assertJsonPath('is_checked', true)
        ->assertJsonPath('checked_by.name', 'Ana');

    $this->patchJson("/api/v1/list-items/{$id}", ['checked' => false])
        ->assertJsonPath('is_checked', false)
        ->assertJsonPath('checked_by', null);
});

it('lists the open items first and the picked ones at the end', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $market = listOf($house, 'Mercado');

    $ids = collect(addItems($market, ['Arroz', 'Feijão', 'Banana'])->json('added'))->pluck('id');
    $this->patchJson("/api/v1/list-items/{$ids[0]}", ['checked' => true]);

    $this->getJson("/api/v1/lists/{$market->id}/items")
        ->assertJsonPath('0.text', 'Feijão')
        ->assertJsonPath('1.text', 'Banana')
        ->assertJsonPath('2.text', 'Arroz')
        ->assertJsonPath('2.is_checked', true);

    $this->getJson("/api/v1/lists/{$market->id}/items?status=open")->assertJsonCount(2);
});

it('checks every open item at once and clears the picked ones', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $market = listOf($house, 'Mercado');

    $ids = collect(addItems($market, ['Arroz', 'Feijão', 'Banana'])->json('added'))->pluck('id');

    $this->postJson("/api/v1/lists/{$market->id}/check", ['item_ids' => [$ids[0]]])->assertJsonPath('checked', 1);
    $this->postJson("/api/v1/lists/{$market->id}/check")->assertJsonPath('checked', 2);
    $this->deleteJson("/api/v1/lists/{$market->id}/checked")->assertJsonPath('deleted', 3);

    $this->getJson("/api/v1/lists/{$market->id}/items")->assertJsonCount(0);
});

it('reorders the items', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $market = listOf($house, 'Mercado');

    [$rice, $beans, $banana] = collect(addItems($market, ['Arroz', 'Feijão', 'Banana'])->json('added'))->pluck('id')->all();

    $this->putJson("/api/v1/lists/{$market->id}/order", ['ids' => [$banana, $rice, $beans]])->assertNoContent();

    $this->getJson("/api/v1/lists/{$market->id}/items")
        ->assertJsonPath('0.text', 'Banana')
        ->assertJsonPath('1.text', 'Arroz')
        ->assertJsonPath('2.text', 'Feijão');

    $this->putJson("/api/v1/lists/{$market->id}/order", ['ids' => [$banana, $rice]])
        ->assertJsonValidationErrors('ids');
});

it('does not touch the items of a private list of another member', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($ana);
    $listId = $this->postJson('/api/v1/lists', ['name' => 'Presentes', 'kind' => 'wishlist', 'visibility' => 'private'])->json('id');
    $itemId = $this->postJson("/api/v1/lists/{$listId}/items", ['items' => [['text' => 'Relógio']]])->json('added.0.id');

    Sanctum::actingAs($leo);
    $this->patchJson("/api/v1/list-items/{$itemId}", ['checked' => true])->assertNotFound();
    $this->deleteJson("/api/v1/list-items/{$itemId}")->assertNotFound();
});
