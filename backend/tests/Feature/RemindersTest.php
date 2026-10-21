<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

// Todos os testes começam em 07/10/2026, ao meio-dia de Brasília.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
});

/** Cria um lembrete pela API e devolve o id. */
function createReminder(array $data): int
{
    return test()->postJson('/api/v1/reminders', array_merge(['text' => 'Lembrete'], $data))
        ->assertCreated()
        ->json('id');
}

it('creates a reminder at 9am when only the date is given', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/reminders', ['text' => 'Pagar o IPVA', 'remind_at' => '2026-10-20'])
        ->assertCreated()
        ->assertJsonPath('remind_at', '2026-10-20T09:00:00-03:00')
        ->assertJsonPath('recurrence', 'none')
        ->assertJsonPath('visibility', 'private')
        ->assertJsonPath('is_done', false);

    // No banco fica em UTC: 9h em Brasília = 12h UTC.
    $this->assertDatabaseHas('reminders', ['text' => 'Pagar o IPVA', 'remind_at' => '2026-10-20 12:00:00']);
});

it('keeps the time when it is given', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/reminders', ['text' => 'Ligar para o dentista', 'remind_at' => '2026-10-20 18:30'])
        ->assertJsonPath('remind_at', '2026-10-20T18:30:00-03:00');
});

it('completes a one-time reminder', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);
    $id = createReminder(['remind_at' => '2026-10-20']);

    $this->postJson("/api/v1/reminders/{$id}/done")->assertOk()->assertJsonPath('is_done', true);

    $this->getJson('/api/v1/reminders')->assertJsonCount(0);
    $this->getJson('/api/v1/reminders?status=done')->assertJsonCount(1);
});

it('moves a repeating reminder to its next date', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);
    $id = createReminder(['text' => 'Trocar o filtro de água', 'remind_at' => '2026-10-20', 'recurrence' => 'monthly', 'every' => 6]);

    $this->postJson("/api/v1/reminders/{$id}/done")
        ->assertJsonPath('is_done', false)
        ->assertJsonPath('remind_at', '2027-04-20T09:00:00-03:00');
});

it('keeps the day of the month of a monthly reminder', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);
    $id = createReminder(['remind_at' => '2026-10-31', 'recurrence' => 'monthly']);

    $this->postJson("/api/v1/reminders/{$id}/done")->assertJsonPath('remind_at', '2026-11-30T09:00:00-03:00');

    $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00', 'America/Sao_Paulo'));
    $this->postJson("/api/v1/reminders/{$id}/done")->assertJsonPath('remind_at', '2026-12-31T09:00:00-03:00');
});

it('snoozes a reminder', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);
    $id = createReminder(['remind_at' => '2026-10-07 10:00']);

    $this->postJson("/api/v1/reminders/{$id}/snooze")->assertJsonPath('remind_at', '2026-10-08T12:00:00-03:00');
    $this->postJson("/api/v1/reminders/{$id}/snooze", ['until' => '2026-10-25'])->assertJsonPath('remind_at', '2026-10-25T09:00:00-03:00');
});

it('lists the upcoming reminders from the nearest to the farthest', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);
    createReminder(['text' => 'Depois', 'remind_at' => '2026-12-01']);
    createReminder(['text' => 'Antes', 'remind_at' => '2026-10-10']);

    $this->getJson('/api/v1/reminders')
        ->assertJsonPath('0.text', 'Antes')
        ->assertJsonPath('1.text', 'Depois');
});

it('shows household reminders to everyone and private ones only to whoever made them', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);
    $leo->refresh();

    Sanctum::actingAs($leo);
    $private = createReminder(['text' => 'Comprar presente da Ana', 'remind_at' => '2026-10-20']);
    createReminder(['text' => 'Vacina do Rex', 'remind_at' => '2026-10-25', 'visibility' => 'household']);

    Sanctum::actingAs($ana);
    $this->getJson('/api/v1/reminders')->assertJsonCount(1)->assertJsonPath('0.text', 'Vacina do Rex');
    $this->postJson("/api/v1/reminders/{$private}/done")->assertNotFound();
});

it('does not let a viewer create reminders', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($ana);

    $this->postJson('/api/v1/reminders', ['text' => 'Teste', 'remind_at' => '2026-10-20'])->assertForbidden();
});
