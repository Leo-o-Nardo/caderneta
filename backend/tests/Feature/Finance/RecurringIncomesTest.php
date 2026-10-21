<?php

use App\Enums\TransactionSource;
use App\Enums\Visibility;
use App\Models\Household;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

// Todos os testes começam em 07/10/2026, ao meio-dia de Brasília.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
});

/** Lança um salário com "Repetir todo mês" pela API e devolve o id da receita fixa criada. */
function repeatIncome(Household $house, array $overrides = []): int
{
    return test()->postJson('/api/v1/transactions', array_merge([
        'kind' => 'income',
        'amount_cents' => 500000,
        'description' => 'Salário',
        'occurred_on' => '2026-10-05',
        'category_id' => categoryOf($house, 'Salário', 'income')->id,
        'repeat_monthly' => true,
    ], $overrides))->assertCreated()->json('recurring_income.id');
}

/** As receitas da casa, da mais antiga para a mais nova, sem os filtros automáticos. */
function incomesOf(Household $house): Collection
{
    return Transaction::withoutGlobalScopes()
        ->where('household_id', $house->id)
        ->where('kind', 'income')
        ->orderBy('occurred_on')
        ->orderBy('id')
        ->get();
}

/** Roda a rotina como o agendador faria, no momento $at (horário de Brasília). */
function launchIncomesAt(string $at): void
{
    test()->travelTo(CarbonImmutable::parse($at, 'America/Sao_Paulo'));
    test()->artisan('incomes:launch')->assertSuccessful();
}

it('creates the income and the rule that repeats it every month', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', [
        'kind' => 'income',
        'amount_cents' => 500000,
        'description' => 'Salário',
        'occurred_on' => '2026-10-05',
        'category_id' => categoryOf($house, 'Salário', 'income')->id,
        'repeat_monthly' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('kind', 'income')
        ->assertJsonPath('source', 'app')
        ->assertJsonPath('recurring_income.day_of_month', 5);

    $this->getJson('/api/v1/recurring-incomes')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.description', 'Salário')
        ->assertJsonPath('0.amount_cents', 500000)
        ->assertJsonPath('0.day_of_month', 5)
        ->assertJsonPath('0.next_on', '2026-11-05')
        ->assertJsonPath('0.can_edit', true);
});

it('only repeats incomes', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/transactions', [
        'kind' => 'expense',
        'amount_cents' => 9990,
        'description' => 'Internet',
        'occurred_on' => '2026-10-07',
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'payment_type' => 'pix',
        'repeat_monthly' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('repeat_monthly');

    expect(RecurringIncome::withoutGlobalScopes()->count())->toBe(0)
        ->and(Transaction::withoutGlobalScopes()->count())->toBe(0);
});

it('launches the next month on the day, in the timezone of who receives, only once', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $id = repeatIncome($house);

    // 23h30 do dia 4 em Brasília já é dia 5 em UTC, mas para o Leo ainda não chegou o dia.
    launchIncomesAt('2026-11-04 23:30');
    expect(incomesOf($house))->toHaveCount(1);

    // A rotina roda de hora em hora: a segunda vez no mesmo dia não lança de novo.
    launchIncomesAt('2026-11-05 00:10');
    launchIncomesAt('2026-11-05 01:10');

    $incomes = incomesOf($house);
    $november = $incomes->last();

    expect($incomes)->toHaveCount(2)
        ->and($november->occurred_on->toDateString())->toBe('2026-11-05')
        ->and($november->amount_cents)->toBe(500000)
        ->and($november->source)->toBe(TransactionSource::Recurring)
        ->and($november->created_by)->toBe($leo->id)
        ->and($november->recurring_income_id)->toBe($id);
});

it('catches up the months it missed and keeps day 31 at the end of short months', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    repeatIncome($house, ['occurred_on' => '2026-10-31']);

    // A rotina ficou parada de novembro a fevereiro: na volta, lança os quatro meses de uma vez.
    launchIncomesAt('2027-03-01 08:00');

    expect(incomesOf($house)->map(fn (Transaction $income) => $income->occurred_on->toDateString())->all())
        ->toBe(['2026-10-31', '2026-11-30', '2026-12-31', '2027-01-31', '2027-02-28']);
});

it('does not bring back a launched income that was deleted', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    repeatIncome($house);

    launchIncomesAt('2026-11-05 08:00');
    $this->deleteJson('/api/v1/transactions/'.incomesOf($house)->last()->id)->assertNoContent();

    // O salário de novembro não veio e foi apagado: a rotina não lança de novo.
    launchIncomesAt('2026-11-20 08:00');

    expect(incomesOf($house))->toHaveCount(1);
});

it('stops repeating and keeps what was already launched', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $id = repeatIncome($house);

    $this->deleteJson("/api/v1/recurring-incomes/{$id}")->assertNoContent();
    launchIncomesAt('2026-11-05 08:00');

    expect(incomesOf($house))->toHaveCount(1);

    $this->getJson('/api/v1/recurring-incomes')->assertOk()->assertJsonCount(0);
    $this->getJson('/api/v1/transactions/'.incomesOf($house)->first()->id)
        ->assertOk()
        ->assertJsonPath('recurring_income', null);
});

it('uses the new amount and day in the next launches', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $id = repeatIncome($house);

    $this->patchJson("/api/v1/recurring-incomes/{$id}", ['category_id' => categoryOf($house, 'Contas da casa')->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');

    $this->patchJson("/api/v1/recurring-incomes/{$id}", ['amount_cents' => 550000, 'day_of_month' => 10])
        ->assertOk()
        ->assertJsonPath('next_on', '2026-11-10');

    launchIncomesAt('2026-11-07 08:00');
    expect(incomesOf($house))->toHaveCount(1);

    launchIncomesAt('2026-11-10 08:00');
    $november = incomesOf($house)->last();

    expect($november->occurred_on->toDateString())->toBe('2026-11-10')
        ->and($november->amount_cents)->toBe(550000);
});

it('keeps a private income hidden from the rest of the household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($ana);
    $id = repeatIncome($house, ['visibility' => 'private']);

    launchIncomesAt('2026-11-05 08:00');

    // O lançamento automático sai privado também.
    expect(incomesOf($house)->pluck('visibility')->all())->toBe([Visibility::Private, Visibility::Private]);

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/recurring-incomes')->assertOk()->assertJsonCount(0);
    $this->getJson("/api/v1/recurring-incomes/{$id}")->assertNotFound();
    $this->getJson('/api/v1/transactions?kind=income')->assertOk()->assertJsonCount(0, 'data');
});

it('lets only who registered it, or the owner, change it', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $bia = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member'], [$bia, 'member']]);
    Sanctum::actingAs($ana);
    $id = repeatIncome($house);

    Sanctum::actingAs($bia);
    $this->getJson('/api/v1/recurring-incomes')->assertOk()->assertJsonPath('0.can_edit', false);
    $this->patchJson("/api/v1/recurring-incomes/{$id}", ['amount_cents' => 1])->assertForbidden();
    $this->deleteJson("/api/v1/recurring-incomes/{$id}")->assertForbidden();

    Sanctum::actingAs($leo);
    $this->patchJson("/api/v1/recurring-incomes/{$id}", ['amount_cents' => 600000])
        ->assertOk()
        ->assertJsonPath('amount_cents', 600000);
});

it('does not launch for someone who left the household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($ana);
    repeatIncome($house);

    app(HouseholdService::class)->removeMember($house, $ana);
    launchIncomesAt('2026-11-05 08:00');

    expect(incomesOf($house))->toHaveCount(1);
});

it('launches each income in its own household', function () {
    $leo = User::factory()->create();
    $bia = User::factory()->create();
    $leoHouse = householdOf($leo);
    $biaHouse = householdOf($bia);

    Sanctum::actingAs($leo);
    repeatIncome($leoHouse);
    Sanctum::actingAs($bia);
    repeatIncome($biaHouse, ['amount_cents' => 300000]);

    // O Leo cria outra casa e passa a usar ela: o salário continua caindo na casa da receita fixa.
    $other = householdOf($leo);

    launchIncomesAt('2026-11-05 08:00');

    expect(incomesOf($leoHouse)->pluck('amount_cents')->all())->toBe([500000, 500000])
        ->and(incomesOf($biaHouse)->pluck('amount_cents')->all())->toBe([300000, 300000])
        ->and(incomesOf($other))->toHaveCount(0);
});

it('registers from the Agenda, starting on the next day it falls', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $salary = [
        'description' => 'Salário',
        'amount_cents' => 500000,
        'category_id' => categoryOf($house, 'Salário', 'income')->id,
    ];

    // Hoje é dia 7: o dia 15 ainda vem neste mês; o dia 5 já passou, então começa em novembro.
    $this->postJson('/api/v1/recurring-incomes', [...$salary, 'day_of_month' => 15])
        ->assertCreated()
        ->assertJsonPath('next_on', '2026-10-15');
    $this->postJson('/api/v1/recurring-incomes', [...$salary, 'description' => 'Aluguel do quarto', 'day_of_month' => 5])
        ->assertCreated()
        ->assertJsonPath('next_on', '2026-11-05');

    // Nenhuma das duas lança nada agora.
    expect(incomesOf($house))->toHaveCount(0);
});

it('launches right away when the day of the income is today', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $id = $this->postJson('/api/v1/recurring-incomes', [
        'description' => 'Salário',
        'amount_cents' => 500000,
        'category_id' => categoryOf($house, 'Salário', 'income')->id,
        'day_of_month' => 7,
    ])->assertCreated()->assertJsonPath('next_on', '2026-11-07')->json('id');

    $today = incomesOf($house)->first();

    expect(incomesOf($house))->toHaveCount(1)
        ->and($today->occurred_on->toDateString())->toBe('2026-10-07')
        ->and($today->source)->toBe(TransactionSource::Recurring)
        ->and($today->recurring_income_id)->toBe($id);

    // A rotina, rodando depois, não lança o mesmo dia de novo.
    launchIncomesAt('2026-10-07 18:00');
    expect(incomesOf($house))->toHaveCount(1);
});

it('validates the income it registers', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/recurring-incomes', [
        'description' => '',
        'amount_cents' => 0,
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'day_of_month' => 32,
    ])->assertUnprocessable()->assertJsonValidationErrors(['description', 'amount_cents', 'category_id', 'day_of_month']);

    expect(RecurringIncome::withoutGlobalScopes()->count())->toBe(0);
});
