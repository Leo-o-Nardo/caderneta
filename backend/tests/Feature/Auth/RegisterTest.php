<?php

use App\Models\User;

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Leo',
        'email' => 'Leo@Example.com ',
        'password' => 'secret123',
        'device_name' => 'pixel',
    ], $overrides);
}

it('registers a user with a default household, waiting for the approval', function () {
    // Sem convite, a conta espera a liberação: nada de login ainda (ver AccessApprovalTest).
    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonMissingPath('token');

    $user = User::first();

    expect($user->email)->toBe('leo@example.com')
        ->and($user->timezone)->toBe('America/Sao_Paulo')
        ->and($user->accessStatus())->toBe('pending')
        ->and($user->households)->toHaveCount(1)
        ->and($user->households->first()->name)->toBe('Minha casa')
        ->and($user->households->first()->membership->role->value)->toBe('owner');
});

it('rejects an e-mail that is already registered', function () {
    User::factory()->create(['email' => 'leo@example.com']);

    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects a weak password', function () {
    $this->postJson('/api/v1/auth/register', registerPayload([
        'password' => 'abcdefgh',
    ]))->assertJsonValidationErrors('password');
});
