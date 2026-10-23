<?php

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| Excluir a conta, pelo app (DELETE /me, com a senha) ou pela página /excluir-conta (e-mail e senha).
| Sai o que é da pessoa; o que é da casa fica para quem continua nela.
*/

/** Entra como $user com um login de verdade do Sanctum, como o app faz. */
function loginAs(User $user): string
{
    $token = $user->createToken('Celular')->plainTextToken;
    app('auth')->forgetGuards();

    return $token;
}

it('deletes the account from the app, with the password', function () {
    $leo = User::factory()->create(['password' => 'secret123']);
    $house = householdOf($leo);
    $token = loginAs($leo);
    $leo->createToken('Outro celular');

    test()->withToken($token)->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

    expect(User::find($leo->id))->toBeNull()
        ->and(Household::find($house->id))->toBeNull()
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('asks for the right password', function () {
    $leo = User::factory()->create(['password' => 'secret123']);
    householdOf($leo);

    test()->withToken(loginAs($leo))->deleteJson('/api/v1/me', ['password' => 'errada123'])
        ->assertJsonValidationErrors(['password' => 'A senha informada está incorreta.']);

    expect(User::find($leo->id))->not->toBeNull();
});

it('hands a shared house to the oldest member who can edit', function () {
    $leo = User::factory()->create(['password' => 'secret123']);
    $bia = User::factory()->create();
    $ana = User::factory()->create();
    $caio = User::factory()->create();
    $house = householdOf($leo, [[$bia, 'viewer'], [$ana, 'member'], [$caio, 'member']]);
    $lunch = Transaction::factory()->for($house)->create([
        'created_by' => $leo->id,
        'category_id' => categoryOf($house, 'Mercado')->id,
    ]);

    test()->withToken(loginAs($leo))->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

    // A Bia é mais antiga, mas é "Só leitura": a casa fica com a Ana.
    expect($ana->roleIn($house))->toBe(HouseholdRole::Owner)
        ->and($caio->roleIn($house))->toBe(HouseholdRole::Member)
        ->and($bia->roleIn($house))->toBe(HouseholdRole::Viewer)
        ->and($house->members()->count())->toBe(3)
        // O lançamento é da casa: fica, sem o nome de quem lançou.
        ->and(Transaction::withoutGlobalScopes()->find($lunch->id)->created_by)->toBeNull();
});

it('keeps the other owner as the only owner', function () {
    $leo = User::factory()->create(['password' => 'secret123']);
    $ana = User::factory()->create();
    $caio = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'owner'], [$caio, 'member']]);

    test()->withToken(loginAs($leo))->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

    expect($ana->roleIn($house))->toBe(HouseholdRole::Owner)
        ->and($caio->roleIn($house))->toBe(HouseholdRole::Member);
});

it('deletes a house left with nobody who can edit, and gives the others a house of their own', function () {
    $leo = User::factory()->create(['password' => 'secret123']);
    $bia = User::factory()->create();
    $house = householdOf($leo, [[$bia, 'viewer']]);

    test()->withToken(loginAs($leo))->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

    $bia->refresh();
    expect(Household::find($house->id))->toBeNull()
        ->and($bia->households)->toHaveCount(1)
        ->and($bia->currentHousehold->name)->toBe('Minha casa');
});

it('just leaves a house where the person is a member', function () {
    $ana = User::factory()->create();
    $leo = User::factory()->create(['password' => 'secret123']);
    $house = householdOf($ana, [[$leo, 'member']]);

    test()->withToken(loginAs($leo))->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

    expect($ana->roleIn($house))->toBe(HouseholdRole::Owner)
        ->and($house->members()->count())->toBe(1);
});

it('deletes the account from the web page, without the app', function () {
    $leo = User::factory()->create(['email' => 'leo@example.com', 'password' => 'secret123']);
    householdOf($leo);

    $this->get('/excluir-conta')->assertOk()->assertSee('Excluir a conta');

    $this->from('/excluir-conta')
        ->post('/excluir-conta', ['email' => 'leo@example.com', 'password' => 'errada123'])
        ->assertRedirect('/excluir-conta')
        ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
    expect(User::find($leo->id))->not->toBeNull();

    $this->post('/excluir-conta', ['email' => 'leo@example.com', 'password' => 'secret123'])
        ->assertOk()
        ->assertSee('Conta excluída');
    expect(User::find($leo->id))->toBeNull();
});
