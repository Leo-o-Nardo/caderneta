<?php

use App\Models\Household;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists the households of the user with the role in each one', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $ana = User::factory()->create(['name' => 'Ana']);
    householdOf($leo);
    householdOf($ana, [[$leo, 'viewer']]);

    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/households')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['name' => 'Casa de Leo', 'role' => 'owner', 'is_current' => false])
        ->assertJsonFragment(['name' => 'Casa de Ana', 'role' => 'viewer', 'is_current' => true]);
});

it('creates another household and makes it the current one', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/households', ['name' => 'Casa da praia'])
        ->assertCreated()
        ->assertJsonPath('name', 'Casa da praia')
        ->assertJsonPath('role', 'owner')
        ->assertJsonPath('is_current', true)
        ->assertJsonPath('members_count', 1);

    expect($leo->fresh()->currentHousehold->name)->toBe('Casa da praia');
});

it('switches the current household', function () {
    $leo = User::factory()->create();
    $first = householdOf($leo);
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->putJson('/api/v1/households/current', ['household_id' => $first->id])
        ->assertOk()
        ->assertJsonPath('id', $first->id)
        ->assertJsonPath('is_current', true);

    expect($leo->fresh()->current_household_id)->toBe($first->id);
});

it('shows the current household with its members', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $ana = User::factory()->create(['name' => 'Ana']);
    householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();
    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/household')
        ->assertOk()
        ->assertJsonPath('members_count', 2)
        ->assertJsonPath('members.0.name', 'Leo')
        ->assertJsonPath('members.0.role', 'owner')
        ->assertJsonPath('members.0.is_you', true)
        ->assertJsonPath('members.1.name', 'Ana')
        ->assertJsonPath('members.1.role', 'member');
});

it('only lets the owner rename the household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);

    Sanctum::actingAs($ana);
    $this->patchJson('/api/v1/household', ['name' => 'Nova'])->assertForbidden();

    Sanctum::actingAs($leo);
    $this->patchJson('/api/v1/household', ['name' => 'Nova'])
        ->assertOk()
        ->assertJsonPath('name', 'Nova');
});

it('deletes the household and gives each member a household of their own', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);

    Sanctum::actingAs($ana);
    $this->deleteJson('/api/v1/household')->assertForbidden();

    Sanctum::actingAs($leo);
    $this->deleteJson('/api/v1/household')->assertNoContent();

    expect(Household::find($house->id))->toBeNull()
        ->and($leo->fresh()->currentHousehold->name)->toBe('Minha casa')
        ->and($ana->fresh()->currentHousehold->name)->toBe('Minha casa')
        ->and($ana->fresh()->current_household_id)->not->toBe($leo->fresh()->current_household_id);
});

it('repairs the current household of a user who was removed from it', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create(['name' => 'Ana']);
    householdOf($ana);
    $house = householdOf($leo, [[$ana, 'member']]);

    // Removida direto no banco, então o current_household_id dela ficou desatualizado.
    $house->members()->detach($ana->id);
    Sanctum::actingAs($ana->fresh());

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('current_household.name', 'Casa de Ana');
});
