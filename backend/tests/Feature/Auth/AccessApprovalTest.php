<?php

use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\AccessApproved;
use App\Notifications\AccessRejected;
use App\Notifications\AccessRequested;
use App\Notifications\MemberJoinedByInvite;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/*
| Quem se cadastra sozinho espera o administrador liberar (pelo link do e-mail ou pelo terminal);
| quem vem por convite entra direto.
*/

beforeEach(function () {
    config(['access.admin_email' => 'admin@example.com']);
    Notification::fake();
});

function signUp(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/auth/register', array_merge([
        'name' => 'Bia',
        'email' => 'bia@example.com',
        'password' => 'secret123',
        'device_name' => 'pixel',
    ], $overrides));
}

function signIn(string $password = 'secret123'): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['email' => 'bia@example.com', 'password' => $password, 'device_name' => 'pixel']);
}

/** O link de um dos botões da página (liberar ou recusar), assinado como a página gera. */
function decisionLink(User $user, string $decision): string
{
    return URL::temporarySignedRoute('access.decide', now()->addMinutes(30), ['user' => $user->id, 'decision' => $decision]);
}

it('keeps a new account waiting and asks the administrator', function () {
    signUp()
        ->assertStatus(202)
        ->assertJsonPath('message', 'Cadastro recebido! Assim que ele for liberado, você recebe um e-mail e já pode entrar.')
        ->assertJsonMissingPath('token');

    $bia = User::query()->where('email', 'bia@example.com')->first();

    expect($bia->accessStatus())->toBe('pending');
    Notification::assertSentOnDemand(
        AccessRequested::class,
        fn (AccessRequested $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'admin@example.com'
            && $notification->user->is($bia),
    );

    // Com a senha certa, mas ainda sem liberação: não entra.
    signIn()->assertJsonValidationErrors(['access_pending' => 'ainda está em análise']);
});

it('lets in right away who comes by an invite, and only tells the administrator', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $house = householdOf($leo);
    $invitation = HouseholdInvitation::factory()->for($house)->create(['invited_by' => $leo->id]);

    signUp(['invite_code' => $invitation->code])
        ->assertCreated()
        ->assertJsonStructure(['token'])
        ->assertJsonPath('user.current_household.id', $house->id);

    expect(User::query()->where('email', 'bia@example.com')->first()->accessStatus())->toBe('approved');
    Notification::assertSentOnDemand(MemberJoinedByInvite::class);
    Notification::assertSentOnDemandTimes(AccessRequested::class, 0);
});

it('opens the review page only with a valid signed link, and opening it changes nothing', function () {
    $bia = User::factory()->pending()->create(['name' => 'Bia', 'email' => 'bia@example.com']);
    $link = URL::temporarySignedRoute('access.review', now()->addDays(7), ['user' => $bia->id]);

    $this->get($link)->assertOk()->assertSee('Bia')->assertSee('Liberar acesso')->assertSee('Recusar');

    // O Gmail e os antivírus abrem os links dos e-mails: abrir não pode liberar ninguém.
    expect($bia->refresh()->accessStatus())->toBe('pending');

    $this->get("/acesso/{$bia->id}")->assertForbidden();
    $this->post("/acesso/{$bia->id}/liberar")->assertForbidden();

    $this->travel(8)->days();
    $this->get($link)->assertForbidden();
});

it('approves from the page button and tells the person, who can then sign in', function () {
    $bia = User::factory()->pending()->create(['email' => 'bia@example.com']);

    $this->post(decisionLink($bia, 'liberar'))->assertOk()->assertSee('Acesso liberado');

    expect($bia->refresh()->accessStatus())->toBe('approved');
    Notification::assertSentTo($bia, AccessApproved::class);

    // A senha das contas de teste é "password".
    signIn('password')->assertOk()->assertJsonStructure(['token']);
});

it('rejects politely, keeps the record and signs the person out of every device', function () {
    $bia = User::factory()->create(['email' => 'bia@example.com']);
    $bia->createToken('pixel');

    $this->post(decisionLink($bia, 'recusar'))->assertOk()->assertSee('Cadastro recusado');

    expect($bia->refresh()->accessStatus())->toBe('rejected')
        ->and($bia->tokens()->count())->toBe(0);
    $this->assertDatabaseHas('users', ['email' => 'bia@example.com']);
    Notification::assertSentTo($bia, AccessRejected::class);

    signIn('password')->assertJsonValidationErrors(['access_rejected' => 'não foi aprovado']);

    // Mudou de ideia: dá para liberar depois, pelo mesmo caminho.
    $this->post(decisionLink($bia, 'liberar'))->assertOk();
    expect($bia->refresh()->accessStatus())->toBe('approved');
});

it('decides from the terminal too', function () {
    User::factory()->pending()->create(['name' => 'Bia', 'email' => 'bia@example.com']);

    $this->artisan('users:pending')->expectsOutputToContain('bia@example.com')->assertSuccessful();
    $this->artisan('users:approve', ['email' => 'bia@example.com'])->assertSuccessful();

    expect(User::query()->where('email', 'bia@example.com')->first()->accessStatus())->toBe('approved');
});
