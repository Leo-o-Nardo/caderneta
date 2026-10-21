<?php

use App\Models\Household;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

// Todos os testes acontecem em 07/10/2026, ao meio-dia de Brasília.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', 'America/Sao_Paulo'));
});

/** Cria uma conta fixa pela API e devolve o id. */
function createBill(Household $house, array $overrides = []): int
{
    return test()->postJson('/api/v1/bills', array_merge([
        'name' => 'Luz',
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'due_day' => 10,
    ], $overrides))->assertCreated()->json('id');
}

it('creates a bill and shows the month that is open', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/bills', [
        'name' => 'Luz',
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'due_day' => 10,
    ])
        ->assertCreated()
        ->assertJsonPath('starts_on', '2026-10')
        ->assertJsonPath('expected_cents', null)
        ->assertJsonPath('open.month', '2026-10')
        ->assertJsonPath('open.due_on', '2026-10-10')
        ->assertJsonPath('open.status', 'upcoming')
        ->assertJsonPath('open.days_until_due', 3);
});

it('starts next month when the due day of this month already passed', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/bills', [
        'name' => 'Água',
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'due_day' => 5,
    ])
        ->assertJsonPath('starts_on', '2026-11')
        ->assertJsonPath('open.due_on', '2026-11-05');
});

it('lists the overdue and upcoming bills in due date order', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    createBill($house, ['name' => 'Internet', 'due_day' => 7, 'expected_cents' => 9990, 'starts_on' => '2026-09']);
    createBill($house, ['name' => 'Condomínio', 'due_day' => 10, 'expected_cents' => 62000]);

    $this->getJson('/api/v1/bills/upcoming?days=30')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.bill.name', 'Internet')
        ->assertJsonPath('0.due_on', '2026-09-07')
        ->assertJsonPath('0.status', 'overdue')
        ->assertJsonPath('0.days_until_due', -30)
        ->assertJsonPath('1.due_on', '2026-10-07')
        ->assertJsonPath('1.status', 'due_today')
        ->assertJsonPath('2.bill.name', 'Condomínio')
        ->assertJsonPath('2.status', 'upcoming')
        ->assertJsonPath('2.amount_cents', 62000);
});

it('pays a bill with the expected value and records the expense', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['name' => 'Internet', 'expected_cents' => 9990]);

    $transactionId = $this->postJson("/api/v1/bills/{$billId}/pay")
        ->assertOk()
        ->assertJsonPath('month', '2026-10')
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('amount_cents', 9990)
        ->json('transaction_id');

    $this->getJson("/api/v1/transactions/{$transactionId}")
        ->assertJsonPath('description', 'Internet')
        ->assertJsonPath('amount_cents', 9990)
        ->assertJsonPath('occurred_on', '2026-10-07')
        ->assertJsonPath('category.name', 'Contas da casa')
        ->assertJsonPath('payment_type', 'pix');

    $this->getJson('/api/v1/bills/upcoming')->assertJsonCount(0);
});

it('asks for the amount when the bill has no expected value', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house);

    $this->postJson("/api/v1/bills/{$billId}/pay")->assertJsonValidationErrors('amount_cents');

    $this->postJson("/api/v1/bills/{$billId}/pay", ['amount_cents' => 18640, 'payment_type' => 'pix'])
        ->assertOk()
        ->assertJsonPath('amount_cents', 18640);
});

it('pays the oldest open month first and never the same month twice', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['expected_cents' => 9990, 'starts_on' => '2026-09']);

    $this->postJson("/api/v1/bills/{$billId}/pay")->assertJsonPath('month', '2026-09');
    $this->postJson("/api/v1/bills/{$billId}/pay")->assertJsonPath('month', '2026-10');

    $this->postJson("/api/v1/bills/{$billId}/pay", ['month' => '2026-10'])
        ->assertJsonValidationErrors(['month' => 'já foi pago ou pulado']);
    $this->postJson("/api/v1/bills/{$billId}/pay", ['month' => '2026-08'])
        ->assertJsonValidationErrors(['month' => 'ainda não existia']);
});

it('reopens the month when the expense is deleted', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['expected_cents' => 9990]);

    $transactionId = $this->postJson("/api/v1/bills/{$billId}/pay")->json('transaction_id');
    $this->deleteJson("/api/v1/transactions/{$transactionId}")->assertNoContent();

    $this->getJson('/api/v1/bills/month?month=2026-10')
        ->assertJsonPath('0.status', 'upcoming')
        ->assertJsonPath('0.transaction_id', null);
});

it('undoes a payment and deletes its expense', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['expected_cents' => 9990]);

    $this->postJson("/api/v1/bills/{$billId}/pay")->assertOk();
    $this->deleteJson("/api/v1/bills/{$billId}/payments/2026-10")->assertNoContent();

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseCount('bill_payments', 0);
    $this->getJson('/api/v1/bills/month?month=2026-10')->assertJsonPath('0.status', 'upcoming');
});

it('skips a month without recording an expense', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house);

    $this->postJson("/api/v1/bills/{$billId}/skip", ['month' => '2026-10'])
        ->assertOk()
        ->assertJsonPath('status', 'skipped');

    $this->assertDatabaseCount('transactions', 0);
    $this->getJson('/api/v1/bills/upcoming')->assertJsonCount(0);
});

it('uses the last day of short months and hides months before the bill existed', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    createBill($house, ['due_day' => 31]);

    $this->getJson('/api/v1/bills/month?month=2027-02')->assertJsonPath('0.due_on', '2027-02-28');
    $this->getJson('/api/v1/bills/month?month=2026-08')->assertJsonCount(0);
});

it('does not let a viewer change the bills', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'viewer']]);
    $leo->refresh();

    Sanctum::actingAs($leo);
    $billId = createBill($house, ['expected_cents' => 9990]);

    Sanctum::actingAs($ana);
    $this->getJson('/api/v1/bills')->assertOk()->assertJsonCount(1);
    $this->postJson("/api/v1/bills/{$billId}/pay")->assertForbidden();
    $this->deleteJson("/api/v1/bills/{$billId}")->assertForbidden();
});

it('does not touch the bills of another household', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo);
    $anaHouse = householdOf($ana);

    Sanctum::actingAs($ana);
    $billId = createBill($anaHouse, ['expected_cents' => 9990]);

    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/bills')->assertJsonCount(0);
    $this->getJson("/api/v1/bills/{$billId}")->assertNotFound();
    $this->postJson("/api/v1/bills/{$billId}/pay")->assertNotFound();
    $this->patchJson("/api/v1/bills/{$billId}", ['name' => 'Hackeada'])->assertNotFound();
});

it('uses the informed estimate of a variable bill before any payment', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    createBill($house, ['amount_varies' => true, 'expected_cents' => 17000]);

    $this->getJson('/api/v1/bills/month?month=2026-10')
        ->assertJsonPath('0.amount_cents', 17000)
        ->assertJsonPath('0.is_estimate', true)
        ->assertJsonPath('0.bill.amount_varies', true);
});

it('adjusts the estimate to the average of the last 3 payments', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['amount_varies' => true, 'expected_cents' => 17000, 'starts_on' => '2026-06']);

    foreach (['2026-06' => 15000, '2026-07' => 18200, '2026-08' => 16500, '2026-09' => 19000] as $month => $cents) {
        $this->postJson("/api/v1/bills/{$billId}/pay", ['month' => $month, 'amount_cents' => $cents])->assertOk();
    }

    // Só os 3 últimos contam: (18200 + 16500 + 19000) / 3 = 17900.
    $this->getJson('/api/v1/bills/month?month=2026-10')
        ->assertJsonPath('0.amount_cents', 17900)
        ->assertJsonPath('0.is_estimate', true);

    $this->getJson("/api/v1/bills/{$billId}")->assertJsonPath('estimate_cents', 17900);
});

it('asks for the exact amount of a variable bill even when it has an estimate', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['amount_varies' => true, 'expected_cents' => 17000]);

    $this->postJson("/api/v1/bills/{$billId}/pay")->assertJsonValidationErrors('amount_cents');

    $this->postJson("/api/v1/bills/{$billId}/pay", ['amount_cents' => 18640])
        ->assertOk()
        ->assertJsonPath('amount_cents', 18640)
        ->assertJsonPath('is_estimate', false);
});

it('shows the last months of a bill', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);
    $billId = createBill($house, ['expected_cents' => 9990, 'starts_on' => '2026-08']);

    $this->postJson("/api/v1/bills/{$billId}/pay", ['month' => '2026-08'])->assertOk();
    $this->postJson("/api/v1/bills/{$billId}/skip", ['month' => '2026-09'])->assertOk();

    // Do mais recente ao mais antigo; julho e antes não aparecem (a conta começou em agosto).
    $this->getJson("/api/v1/bills/{$billId}")
        ->assertOk()
        ->assertJsonPath('name', 'Luz')
        ->assertJsonPath('open.month', '2026-10')
        ->assertJsonCount(3, 'history')
        ->assertJsonPath('history.0.month', '2026-10')
        ->assertJsonPath('history.0.status', 'upcoming')
        ->assertJsonPath('history.1.status', 'skipped')
        ->assertJsonPath('history.2.status', 'paid')
        ->assertJsonPath('history.2.amount_cents', 9990);
});
