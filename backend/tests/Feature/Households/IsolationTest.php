<?php

use App\Models\HouseholdInvitation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Leo e Ana têm cada um a sua casa e não compartilham nada. O Leo é dono da própria casa,
// então todo 404 abaixo vem do isolamento dos dados, e não de falta de permissão.
beforeEach(function () {
    $this->leo = User::factory()->create();
    $this->ana = User::factory()->create();
    $this->leoHouse = householdOf($this->leo);
    $this->anaHouse = householdOf($this->ana);

    Sanctum::actingAs($this->leo);
});

it('only shows the members of the current household', function () {
    $this->getJson('/api/v1/household')
        ->assertOk()
        ->assertJsonPath('id', $this->leoHouse->id)
        ->assertJsonCount(1, 'members');
});

it('does not switch to a household the user does not belong to', function () {
    $this->putJson('/api/v1/households/current', ['household_id' => $this->anaHouse->id])
        ->assertNotFound();

    expect($this->leo->fresh()->current_household_id)->toBe($this->leoHouse->id);
});

it('does not change or remove members of another household', function () {
    $this->patchJson("/api/v1/household/members/{$this->ana->id}", ['role' => 'viewer'])->assertNotFound();
    $this->deleteJson("/api/v1/household/members/{$this->ana->id}")->assertNotFound();

    expect($this->ana->roleIn($this->anaHouse)->value)->toBe('owner');
});

it('does not list or revoke invitations of another household', function () {
    $invitation = HouseholdInvitation::factory()->for($this->anaHouse)->create(['invited_by' => $this->ana->id]);

    $this->getJson('/api/v1/household/invitations')->assertJsonCount(0);
    $this->deleteJson("/api/v1/household/invitations/{$invitation->id}")->assertNotFound();

    expect($invitation->fresh()->revoked_at)->toBeNull();
});
