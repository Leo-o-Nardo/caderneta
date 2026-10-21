<?php

use App\Models\User;
use Database\Seeders\DemoHouseholdSeeder;
use Laravel\Sanctum\Sanctum;

it('builds a demo household that works through the API', function () {
    $this->seed(DemoHouseholdSeeder::class);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'leo@example.com',
        'password' => 'password',
        'device_name' => 'test',
    ])->assertOk();

    Sanctum::actingAs(User::where('email', 'leo@example.com')->firstOrFail());

    $summary = $this->getJson('/api/v1/summary')->assertOk()->json();
    $budgets = collect($summary['budgets'])->keyBy('category.name');

    expect($summary['expense_cents'])->toBeGreaterThan(0)
        ->and($budgets['Delivery']['status'])->toBe('over')
        ->and(collect($summary['upcoming_bills'])->contains(
            fn (array $bill) => $bill['bill']['name'] === 'Condomínio' && $bill['status'] === 'overdue'
        ))->toBeTrue();

    $this->getJson('/api/v1/cards')->assertJsonCount(2);
    $this->getJson('/api/v1/lists')->assertJsonCount(3);
    // IPVA e vacina (da casa) e o filtro de água (privado do Leo).
    $this->getJson('/api/v1/reminders')->assertJsonCount(3);

    // A lista privada da Ana só aparece para ela; o lembrete privado do Leo, não.
    Sanctum::actingAs(User::where('email', 'ana@example.com')->firstOrFail());
    $this->getJson('/api/v1/lists')->assertJsonCount(4);
    $this->getJson('/api/v1/reminders')->assertJsonCount(2);
});
