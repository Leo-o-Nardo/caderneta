<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates the default lists with every household', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/lists')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.name', 'Mercado')
        ->assertJsonPath('0.kind', 'shopping')
        ->assertJsonPath('0.is_default', true)
        ->assertJsonPath('0.open_items_count', 0)
        ->assertJsonPath('1.name', 'Desejos')
        ->assertJsonPath('1.kind', 'wishlist')
        ->assertJsonPath('2.name', 'Casa')
        ->assertJsonPath('2.kind', 'todo');
});

it('creates a list with the icon of its kind at the end', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/lists', ['name' => 'Viagem', 'kind' => 'custom'])
        ->assertCreated()
        ->assertJsonPath('icon', 'list')
        ->assertJsonPath('is_default', false)
        ->assertJsonPath('visibility', 'household')
        ->assertJsonPath('position', 3);
});

it('switches the default list', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/lists/'.listOf($house, 'Casa')->id, ['is_default' => true])
        ->assertOk()
        ->assertJsonPath('is_default', true);

    $this->assertDatabaseHas('lists', ['id' => listOf($house, 'Mercado')->id, 'is_default' => false]);
});

it('does not delete the default list', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->deleteJson('/api/v1/lists/'.listOf($house, 'Mercado')->id)->assertJsonValidationErrors('list');
    $this->deleteJson('/api/v1/lists/'.listOf($house, 'Casa')->id)->assertNoContent();

    $this->getJson('/api/v1/lists')->assertJsonCount(2);
});

it('keeps a private list away from the other members', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($ana);
    $id = $this->postJson('/api/v1/lists', ['name' => 'Presentes', 'kind' => 'wishlist', 'visibility' => 'private'])
        ->assertCreated()
        ->json('id');
    $this->getJson('/api/v1/lists')->assertJsonCount(4);

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/lists')->assertJsonCount(3);
    $this->getJson("/api/v1/lists/{$id}/items")->assertNotFound();
    $this->postJson("/api/v1/lists/{$id}/items", ['items' => [['text' => 'Espiando']]])->assertNotFound();
});

it('does not make a private list the default one', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/lists', ['name' => 'Minha', 'kind' => 'custom', 'visibility' => 'private', 'is_default' => true])
        ->assertJsonValidationErrors('is_default');

    $this->patchJson('/api/v1/lists/'.listOf($house, 'Mercado')->id, ['visibility' => 'private'])
        ->assertJsonValidationErrors('visibility');

    $this->assertDatabaseCount('lists', 3);
});

it('counts the open items and adds up the wishlist', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $wishes = listOf($house, 'Desejos');

    $ids = collect($this->postJson("/api/v1/lists/{$wishes->id}/items", ['items' => [
        ['text' => 'Fone de ouvido', 'price_cents' => 34990],
        ['text' => 'Air fryer', 'price_cents' => 49900],
        ['text' => 'Livro'],
    ]])->json('added'))->pluck('id');

    $this->patchJson("/api/v1/list-items/{$ids[1]}", ['checked' => true]);

    $this->getJson('/api/v1/lists')
        ->assertJsonPath('1.name', 'Desejos')
        ->assertJsonPath('1.items_count', 3)
        ->assertJsonPath('1.open_items_count', 2)
        ->assertJsonPath('1.open_total_cents', 34990);
});

it('lets viewers read the lists but not change them', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'viewer']]);
    $market = listOf($house, 'Mercado');

    Sanctum::actingAs($ana);
    $this->getJson("/api/v1/lists/{$market->id}/items")->assertOk();
    $this->postJson("/api/v1/lists/{$market->id}/items", ['items' => [['text' => 'Leite']]])->assertForbidden();
    $this->postJson('/api/v1/lists', ['name' => 'Viagem', 'kind' => 'custom'])->assertForbidden();
});

it('does not touch the lists of another household', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    $otherMarket = listOf(householdOf(User::factory()->create()), 'Mercado');
    $leo->refresh();
    Sanctum::actingAs($leo);

    $this->getJson("/api/v1/lists/{$otherMarket->id}/items")->assertNotFound();
    $this->postJson("/api/v1/lists/{$otherMarket->id}/items", ['items' => [['text' => 'Leite']]])->assertNotFound();
    $this->deleteJson("/api/v1/lists/{$otherMarket->id}")->assertNotFound();
});
