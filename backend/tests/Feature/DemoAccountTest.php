<?php

use App\Models\Household;
use App\Models\User;

/*
| demo:account: a conta de revisão das lojas, liberada e com a casa de exemplo. Rodar de novo refaz
| tudo do zero.
*/

it('creates an approved account with the demo house, ready to log in', function () {
    $this->artisan('demo:account', ['email' => 'Revisao@Example.com', '--password' => 'revisao123'])
        ->expectsOutputToContain('Conta de demonstração pronta')
        ->assertSuccessful();

    $token = $this->postJson('/api/v1/auth/login', ['email' => 'revisao@example.com', 'password' => 'revisao123', 'device_name' => 'revisor'])
        ->assertOk()
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/cards')->assertJsonCount(2);
    $this->withToken($token)->getJson('/api/v1/lists')->assertJsonCount(3);
});

it('starts over when run again, keeping a single demo house', function () {
    $this->artisan('demo:account', ['email' => 'revisao@example.com', '--password' => 'revisao123'])->assertSuccessful();
    $first = User::where('email', 'revisao@example.com')->firstOrFail()->current_household_id;

    $this->artisan('demo:account', ['email' => 'revisao@example.com', '--password' => 'revisao123'])->assertSuccessful();

    expect(Household::find($first))->toBeNull()
        ->and(Household::count())->toBe(1)
        ->and(User::count())->toBe(2);
});

it('draws a password when none is given, and refuses a weak one', function () {
    $this->artisan('demo:account', ['email' => 'revisao@example.com'])->assertSuccessful();
    expect(User::where('email', 'revisao@example.com')->exists())->toBeTrue();

    $this->artisan('demo:account', ['email' => 'outra@example.com', '--password' => 'curta'])->assertFailed();
    expect(User::where('email', 'outra@example.com')->exists())->toBeFalse();
});
