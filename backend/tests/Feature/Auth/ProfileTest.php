<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('updates the name of the user', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/me', ['name' => 'Leonardo Ferreira'])
        ->assertOk()
        ->assertJsonPath('name', 'Leonardo Ferreira')
        ->assertJsonPath('current_household.role', 'owner');

    expect($leo->fresh()->name)->toBe('Leonardo Ferreira');
});

it('validates the name and the timezone', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/me', ['name' => '', 'timezone' => 'Lua/Base'])
        ->assertJsonValidationErrors(['name', 'timezone']);
});

it('does not change the e-mail through the profile', function () {
    $leo = User::factory()->create(['email' => 'leo@example.com']);
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/me', ['email' => 'outro@example.com'])->assertOk();

    expect($leo->fresh()->email)->toBe('leo@example.com');
});
