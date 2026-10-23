<?php

use App\Alerts\AlertService;
use App\Events\Alerts\AlertCreated;
use App\Models\Household;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/*
| As notificações no celular: o app registra o aparelho no login de onde entrou, e cada aviso do
| Chat sai também como push pelo serviço da Expo (simulado aqui).
*/

beforeEach(function () {
    Event::fake([AlertCreated::class]);
});

/** A Expo simulada: aceita tudo, ou devolve o mesmo erro para cada notificação. */
function fakeExpo(?string $error = null): void
{
    Http::fake([
        'exp.host/*' => fn (Request $request) => Http::response(['data' => array_map(
            fn () => $error === null
                ? ['status' => 'ok', 'id' => 'recibo']
                : ['status' => 'error', 'message' => 'erro simulado', 'details' => ['error' => $error]],
            $request->data(),
        )]),
    ]);
}

/** Entra como $user num celular (um login de verdade do Sanctum) e registra o aparelho para push. */
function registerPhone(User $user, string $token = 'ExponentPushToken[celular-do-leo]'): string
{
    $login = $user->createToken('Celular')->plainTextToken;
    app('auth')->forgetGuards();

    test()->withToken($login)
        ->postJson('/api/v1/push-tokens', ['token' => $token, 'platform' => 'android'])
        ->assertNoContent();

    return $login;
}

/** Um aviso para $user, pelo mesmo caminho das rotinas de aviso. */
function alertNow(User $user, Household $house, string $kind = 'reminder', string $text = 'Pagar o IPVA'): void
{
    app(AlertService::class)->send($user, $house, $kind, "{$kind}:teste", $text, ['type' => 'reminder_due']);
}

it('registers the phone on the login it came from, once', function () {
    $leo = User::factory()->create();

    registerPhone($leo);
    registerPhone($leo);

    $phone = PushToken::query()->sole();

    expect($phone->user_id)->toBe($leo->id)
        ->and($phone->platform)->toBe('android')
        ->and($phone->personal_access_token_id)->toBe($leo->tokens()->latest('id')->first()->id);
});

it('moves the phone to whoever logs in on it', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();

    registerPhone($leo);
    registerPhone($ana);

    expect(PushToken::query()->sole()->user_id)->toBe($ana->id);
});

it('forgets the phone when the person logs out', function () {
    $leo = User::factory()->create();
    $login = registerPhone($leo);

    app('auth')->forgetGuards();
    $this->withToken($login)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(PushToken::query()->count())->toBe(0);
});

it('accepts only an Expo address and a known system', function () {
    $leo = User::factory()->create();
    $login = $leo->createToken('Celular')->plainTextToken;

    $this->withToken($login)
        ->postJson('/api/v1/push-tokens', ['token' => 'qualquer-coisa', 'platform' => 'windows'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'platform']);
});

it('sends each alert to the phones, with the house to open', function () {
    fakeExpo();
    $leo = User::factory()->create();
    $house = householdOf($leo);
    registerPhone($leo);

    alertNow($leo, $house);

    Http::assertSent(fn (Request $request) => $request->url() === config('services.expo.push_url')
        && $request[0]['to'] === 'ExponentPushToken[celular-do-leo]'
        && $request[0]['title'] === 'Lembrete'
        && $request[0]['body'] === 'Pagar o IPVA'
        && $request[0]['channelId'] === 'avisos'
        && data_get($request[0], 'data.household_id') === $house->id
        && data_get($request[0], 'data.kind') === 'reminder');
});

it('stays quiet without a phone, and for an alert the person turned off', function () {
    fakeExpo();
    $leo = User::factory()->create();
    $house = householdOf($leo);

    alertNow($leo, $house);
    Http::assertNothingSent();

    registerPhone($leo);
    $leo->update(['alert_settings' => ['budget' => false]]);
    alertNow($leo, $house, 'budget', 'Mercado passou de 80% do limite');

    Http::assertNothingSent();
});

it('drops a phone where the app was uninstalled', function () {
    fakeExpo('DeviceNotRegistered');
    $leo = User::factory()->create();
    $house = householdOf($leo);
    registerPhone($leo);

    alertNow($leo, $house);

    expect(PushToken::query()->count())->toBe(0);
});

it('sends a test notification from the terminal', function () {
    fakeExpo();
    $leo = User::factory()->create();

    $this->artisan('alerts:test-push', ['email' => $leo->email])->assertFailed();

    registerPhone($leo);

    $this->artisan('alerts:test-push', ['email' => $leo->email])
        ->expectsOutputToContain('aceitou 1 de 1')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request[0]['title'] === 'Caderneta');
});
