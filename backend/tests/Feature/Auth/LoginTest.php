<?php

use App\Models\User;

it('logs in and returns a token', function () {
    User::factory()->create(['email' => 'leo@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'LEO@example.com',
        'password' => 'password',
        'device_name' => 'pixel',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
});

it('rejects a wrong password', function () {
    User::factory()->create(['email' => 'leo@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'leo@example.com',
        'password' => 'wrong-password',
        'device_name' => 'pixel',
    ])->assertJsonValidationErrors('email');
});

it('requires a token for private routes', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('logs out only the current device', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);
});
