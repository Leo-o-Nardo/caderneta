<?php

use App\Enums\HouseholdRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets the owner change the role of a member', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($leo);

    $this->patchJson("/api/v1/household/members/{$ana->id}", ['role' => 'viewer'])
        ->assertOk()
        ->assertJsonPath('id', $ana->id)
        ->assertJsonPath('role', 'viewer');

    expect($ana->roleIn($house))->toBe(HouseholdRole::Viewer);
});

it('does not let a member manage other members', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($ana);

    $this->patchJson("/api/v1/household/members/{$leo->id}", ['role' => 'viewer'])->assertForbidden();
    $this->deleteJson("/api/v1/household/members/{$leo->id}")->assertForbidden();
});

it('always keeps at least one owner', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson("/api/v1/household/members/{$leo->id}", ['role' => 'member'])
        ->assertJsonValidationErrors('role');

    $this->postJson('/api/v1/household/leave')
        ->assertJsonValidationErrors('household');
});

it('lets an owner leave when there is another owner', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'owner']]);
    $leo->refresh();
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/household/leave')->assertNoContent();

    expect($leo->roleIn($house))->toBeNull()
        ->and($ana->roleIn($house))->toBe(HouseholdRole::Owner);
});

it('removes a member, who then gets a household of their own', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($leo);

    $this->deleteJson("/api/v1/household/members/{$ana->id}")->assertNoContent();

    $ana->refresh();
    expect($ana->roleIn($house))->toBeNull()
        ->and($ana->households)->toHaveCount(1)
        ->and($ana->currentHousehold->name)->toBe('Minha casa');
});

it('does not let the owner remove themselves', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->deleteJson("/api/v1/household/members/{$leo->id}")
        ->assertJsonValidationErrors('member');
});

it('lets a member leave and go back to their other household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create(['name' => 'Ana']);
    householdOf($ana);
    householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($ana);

    $this->postJson('/api/v1/household/leave')->assertNoContent();

    expect($ana->fresh()->currentHousehold->name)->toBe('Casa de Ana');
});
