<?php

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets the owner create an invitation', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    householdOf($leo);
    Sanctum::actingAs($leo);

    $response = $this->postJson('/api/v1/household/invitations', ['role' => 'member'])
        ->assertCreated()
        ->assertJsonPath('role', 'member')
        ->assertJsonPath('invited_by', 'Leo')
        ->assertJsonStructure(['id', 'code', 'url', 'expires_at']);

    expect($response->json('code'))->toMatch('/^[A-HJ-NP-Z2-9]{6}$/')
        ->and($response->json('url'))->toEndWith('/convite/'.$response->json('code'));
});

it('does not allow inviting someone as owner', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/household/invitations', ['role' => 'owner'])
        ->assertJsonValidationErrors('role');
});

it('does not let a viewer invite people', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    $this->postJson('/api/v1/household/invitations', ['role' => 'member'])->assertForbidden();
    $this->getJson('/api/v1/household/invitations')->assertForbidden();
});

it('limits the number of pending invitations', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    HouseholdInvitation::factory()->count(10)->for($house)->create(['invited_by' => $leo->id]);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/household/invitations', ['role' => 'member'])
        ->assertJsonValidationErrors('role');
});

it('shows an invitation before it is accepted', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $house = householdOf($leo);
    $invitation = HouseholdInvitation::factory()->for($house)->create(['invited_by' => $leo->id]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/invitations/'.strtolower($invitation->code))
        ->assertOk()
        ->assertJsonPath('household.name', 'Casa de Leo')
        ->assertJsonPath('household.members_count', 1)
        ->assertJsonPath('invited_by', 'Leo')
        ->assertJsonPath('role', 'member');
});

it('accepts an invitation and switches to the household', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $invitation = HouseholdInvitation::factory()->for($house)->create([
        'invited_by' => $leo->id,
        'role' => HouseholdRole::Viewer,
    ]);
    $ana = User::factory()->create();
    householdOf($ana);
    Sanctum::actingAs($ana);

    $this->postJson("/api/v1/invitations/{$invitation->code}/accept")
        ->assertOk()
        ->assertJsonPath('id', $house->id)
        ->assertJsonPath('role', 'viewer')
        ->assertJsonPath('is_current', true);

    expect($invitation->fresh()->accepted_by)->toBe($ana->id)
        ->and($ana->fresh()->households)->toHaveCount(2);
});

it('rejects invitations that can no longer be used', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $unusable = [
        HouseholdInvitation::factory()->for($house)->expired()->create(['invited_by' => $leo->id]),
        HouseholdInvitation::factory()->for($house)->revoked()->create(['invited_by' => $leo->id]),
        HouseholdInvitation::factory()->for($house)->accepted()->create(['invited_by' => $leo->id]),
    ];
    Sanctum::actingAs(User::factory()->create());

    foreach ($unusable as $invitation) {
        $this->getJson("/api/v1/invitations/{$invitation->code}")->assertNotFound();
        $this->postJson("/api/v1/invitations/{$invitation->code}/accept")->assertJsonValidationErrors('code');
    }
});

it('does not accept an invitation to a household the user already belongs to', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $invitation = HouseholdInvitation::factory()->for($house)->create(['invited_by' => $leo->id]);
    Sanctum::actingAs($leo);

    $this->postJson("/api/v1/invitations/{$invitation->code}/accept")
        ->assertJsonValidationErrors('code');
});

it('revokes a pending invitation', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $invitation = HouseholdInvitation::factory()->for($house)->create(['invited_by' => $leo->id]);
    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/household/invitations')->assertJsonCount(1);
    $this->deleteJson("/api/v1/household/invitations/{$invitation->id}")->assertNoContent();
    $this->getJson('/api/v1/household/invitations')->assertJsonCount(0);

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

it('signs up through an invitation straight into the household', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $invitation = HouseholdInvitation::factory()->for($house)->create(['invited_by' => $leo->id]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'secret123',
        'device_name' => 'pixel',
        'invite_code' => strtolower($invitation->code),
    ])
        ->assertCreated()
        ->assertJsonPath('user.current_household.id', $house->id)
        ->assertJsonPath('user.current_household.role', 'member');

    expect(User::where('email', 'ana@example.com')->first()->households)->toHaveCount(1);
});

it('does not create the account when the invite code is invalid', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'secret123',
        'device_name' => 'pixel',
        'invite_code' => 'ZZZZZZ',
    ])->assertJsonValidationErrors('invite_code');

    expect(User::where('email', 'ana@example.com')->exists())->toBeFalse();
});
