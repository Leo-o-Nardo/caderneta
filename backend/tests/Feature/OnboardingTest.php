<?php

use App\Models\Household;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/** O status de um passo na resposta do GET /onboarding. */
function stepStatus(array $state, string $key): ?string
{
    return collect($state['steps'])->firstWhere('key', $key)['status'] ?? null;
}

function stepDetail(array $state, string $key): ?string
{
    return collect($state['steps'])->firstWhere('key', $key)['detail'] ?? null;
}

it('starts with nothing seen and every step to do', function () {
    $leo = User::factory()->create(['name' => 'Leo Ferreira']);
    $house = householdOf($leo);
    $house->forceFill(['name' => 'Minha casa'])->save();
    Sanctum::actingAs($leo);

    $state = $this->getJson('/api/v1/onboarding')->assertOk()->json();

    expect($state['welcome_seen'])->toBeFalse()
        ->and($state['joined'])->toBeFalse()
        ->and($state['hidden'])->toBeFalse()
        ->and($state['tour'])->toBe(['step' => null, 'completed' => false])
        ->and(collect($state['steps'])->pluck('key')->all())->toBe([
            'household_name', 'first_transaction', 'cards', 'recurring_income', 'recurring_bills', 'categories', 'invite',
        ])
        ->and(collect($state['steps'])->pluck('status')->unique()->all())->toBe(['pending'])
        ->and($state['done'])->toBe(0)
        ->and($state['total'])->toBe(7);
});

it('marks the steps done from the real records', function () {
    $leo = User::factory()->create(['name' => 'Leo Ferreira']);
    $ana = User::factory()->create(['name' => 'Ana Souza']);
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($leo);

    spend($house, 'Mercado', 2350);
    cardOf($house, $leo, ['name' => 'Nubank']);
    cardOf($house, $leo, ['name' => 'Inter']);
    // O cartão de outra pessoa não conta para o passo "Seus cartões".
    cardOf($house, $ana, ['name' => 'Itaú']);
    $this->postJson('/api/v1/recurring-incomes', [
        'description' => 'Salário',
        'amount_cents' => 500000,
        'category_id' => categoryOf($house, 'Salário', 'income')->id,
        'day_of_month' => 5,
    ])->assertCreated();
    // Nomes que começam sem acento: a ordem não depende do idioma do banco.
    foreach (['Luz', 'Gás', 'Internet', 'Condomínio'] as $bill) {
        $this->postJson('/api/v1/bills', [
            'name' => $bill,
            'category_id' => categoryOf($house, 'Contas da casa')->id,
            'due_day' => 10,
            'expected_cents' => 15000,
        ])->assertCreated();
    }

    $state = $this->getJson('/api/v1/onboarding')->assertOk()->json();

    expect(stepStatus($state, 'household_name'))->toBe('done')
        ->and(stepDetail($state, 'household_name'))->toBe('Casa de Leo Ferreira')
        ->and(stepDetail($state, 'first_transaction'))->toBe('Mercado')
        ->and(stepDetail($state, 'cards'))->toBe('Inter e Nubank')
        ->and(stepDetail($state, 'recurring_income'))->toBe('Salário')
        ->and(stepDetail($state, 'recurring_bills'))->toBe('Condomínio, Gás e mais 2')
        ->and(stepDetail($state, 'invite'))->toBe('Ana')
        ->and(stepStatus($state, 'categories'))->toBe('pending')
        ->and($state['done'])->toBe(6);
});

it('goes back to pending when the record is gone', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $card = cardOf($house, $leo);

    expect(stepStatus($this->getJson('/api/v1/onboarding')->json(), 'cards'))->toBe('done');

    $this->deleteJson("/api/v1/cards/{$card->id}")->assertSuccessful();

    expect(stepStatus($this->getJson('/api/v1/onboarding')->json(), 'cards'))->toBe('pending');
});

it('counts an open invitation as done', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/household/invitations', ['role' => 'member'])->assertCreated();

    expect(stepDetail($this->getJson('/api/v1/onboarding')->json(), 'invite'))->toBe('Convite aberto');
});

it('saves the welcome, the skipped steps, the reviewed categories and the hidden block', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/onboarding', ['welcome_seen' => true])->assertOk()->assertJsonPath('welcome_seen', true);
    $this->patchJson('/api/v1/onboarding', ['skip' => 'cards'])->assertOk();
    $this->patchJson('/api/v1/onboarding', ['skip' => 'invite'])->assertOk();
    $this->patchJson('/api/v1/onboarding', ['categories_reviewed' => true])->assertOk();
    $state = $this->patchJson('/api/v1/onboarding', ['hidden' => true])->assertOk()->json();

    expect(stepStatus($state, 'cards'))->toBe('skipped')
        ->and(stepStatus($state, 'invite'))->toBe('skipped')
        ->and(stepStatus($state, 'categories'))->toBe('done')
        ->and(stepDetail($state, 'categories'))->toBe('Conferidas')
        ->and($state['skipped'])->toBe(2)
        ->and($state['hidden'])->toBeTrue();

    $state = $this->patchJson('/api/v1/onboarding', ['unskip' => 'cards'])->assertOk()->json();

    expect(stepStatus($state, 'cards'))->toBe('pending')
        ->and(stepStatus($state, 'invite'))->toBe('skipped');
});

it('shows a skipped step as done once it is done', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/onboarding', ['skip' => 'cards'])->assertOk();
    cardOf($house, $leo, ['name' => 'Nubank']);

    expect(stepStatus($this->getJson('/api/v1/onboarding')->json(), 'cards'))->toBe('done');
});

it('refuses to skip a step that cannot be skipped or that the role does not see', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);

    Sanctum::actingAs($leo);
    $this->patchJson('/api/v1/onboarding', ['skip' => 'first_transaction'])->assertJsonValidationErrors('skip');
    $this->patchJson('/api/v1/onboarding', ['skip' => 'qualquer'])->assertJsonValidationErrors('skip');

    // O membro não vê os passos do dono (nome da casa e convite).
    Sanctum::actingAs($ana);
    $this->patchJson('/api/v1/onboarding', ['skip' => 'invite'])->assertJsonValidationErrors('skip');
});

it('keeps the tour step and clears it when the tour ends', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->patchJson('/api/v1/onboarding', ['tour_step' => 4])->assertOk()->assertJsonPath('tour', ['step' => 4, 'completed' => false]);
    $this->patchJson('/api/v1/onboarding', ['tour_completed' => true])->assertOk()->assertJsonPath('tour', ['step' => null, 'completed' => true]);
    $this->patchJson('/api/v1/onboarding', ['tour_step' => 99])->assertJsonValidationErrors('tour_step');
});

it('gives each role its own steps', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $bia = User::factory()->create();
    householdOf($leo, [[$ana, 'member'], [$bia, 'viewer']]);

    Sanctum::actingAs($ana);
    $member = $this->getJson('/api/v1/onboarding')->assertOk()->json();

    expect($member['joined'])->toBeTrue()
        ->and(collect($member['steps'])->pluck('key')->all())->toBe(['first_transaction', 'cards', 'recurring_income', 'recurring_bills', 'categories']);

    // O "Só leitura" não cadastra nada: fica só com as boas-vindas e o tour.
    Sanctum::actingAs($bia);
    $viewer = $this->getJson('/api/v1/onboarding')->assertOk()->json();

    expect($viewer['steps'])->toBe([])
        ->and($viewer['total'])->toBe(0);

    $this->patchJson('/api/v1/onboarding', ['welcome_seen' => true, 'tour_step' => 2])->assertOk();
});

it('keeps the progress of each person in each household', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);
    $this->patchJson('/api/v1/onboarding', ['welcome_seen' => true, 'skip' => 'cards'])->assertOk();

    // Uma casa nova começa do zero, e a primeira continua como estava.
    $second = $this->postJson('/api/v1/households', ['name' => 'Praia'])->assertCreated()->json('id');
    expect($this->getJson('/api/v1/onboarding')->json('welcome_seen'))->toBeFalse();

    $first = Household::query()->where('name', '!=', 'Praia')->value('id');
    $this->putJson('/api/v1/households/current', ['household_id' => $first])->assertOk();
    $state = $this->getJson('/api/v1/onboarding')->json();

    expect($state['welcome_seen'])->toBeTrue()
        ->and(stepStatus($state, 'cards'))->toBe('skipped')
        ->and($second)->not->toBe($first);
});

it('needs a login', function () {
    $this->getJson('/api/v1/onboarding')->assertUnauthorized();
});
