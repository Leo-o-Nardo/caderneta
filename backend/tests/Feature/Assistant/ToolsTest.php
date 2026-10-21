<?php

use App\Assistant\Tools\GetBudgetStatus;
use App\Assistant\Tools\GetCardInvoice;
use App\Assistant\Tools\GetList;
use App\Assistant\Tools\QueryTransactions;
use App\Assistant\Tools\ToolContext;
use App\Assistant\Tools\ToolRegistry;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\ListService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

// Todos os testes acontecem numa sexta-feira, 09/10/2026, ao meio-dia de Brasília.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'America/Sao_Paulo'));
});

/** Prepara a casa atual de $user como o job faz e devolve o contexto das ferramentas. */
function toolContextFor(User $user): ToolContext
{
    $user = $user->fresh();
    $household = Household::query()->findOrFail($user->current_household_id);
    $role = $user->roleIn($household);

    app(HouseholdContext::class)->set($household, $role, $user);

    return new ToolContext($user, $household, $role, CarbonImmutable::now($user->timezone));
}

it('sums the expenses of a period, finding the category however it is written', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    spend($house, 'Mercado', 40000, '2026-10-02');
    spend($house, 'Mercado', 24000, '2026-10-08');
    spend($house, 'Mercado', 50000, '2026-09-30'); // antes do período
    spend($house, 'Delivery', 9000, '2026-10-05'); // outra categoria

    $result = app(QueryTransactions::class)->handle(
        ['from' => '2026-10-01', 'to' => '2026-10-09', 'category' => 'mercado'],
        toolContextFor($leo),
    );

    expect($result->data)->toMatchArray([
        'category' => 'Mercado',
        'count' => 2,
        'total_cents' => 64000,
        'total' => 'R$ 640,00',
    ])
        ->and($result->data['period']['label'])->toBe('de 1º a 9 de outubro')
        ->and($result->cards)->toBe([]);
});

it('groups by category and sends a bar chart to the app', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    spend($house, 'Mercado', 40000, '2026-10-02');
    spend($house, 'Delivery', 9000, '2026-10-05');
    spend($house, 'Delivery', 3000, '2026-10-06');
    spend($house, 'Lazer', 20000, '2026-10-07');

    $result = app(QueryTransactions::class)->handle(
        ['from' => '2026-10-01', 'to' => '2026-10-09', 'group_by' => 'category'],
        toolContextFor($leo),
    );

    expect($result->data['total'])->toBe('R$ 720,00')
        ->and(array_column($result->data['groups'], 'label'))->toBe(['Mercado', 'Lazer', 'Delivery'])
        // A IA recebe os grupos sem as cores, que são só do gráfico.
        ->and($result->data['groups'][2])->toBe(['label' => 'Delivery', 'count' => 2, 'total_cents' => 12000, 'total' => 'R$ 120,00'])
        ->and($result->cards[0]['type'])->toBe('bar_chart')
        ->and($result->cards[0]['subtitle'])->toBe('De 1º a 9 de outubro')
        ->and($result->cards[0]['bars'][0])->toBe(['label' => 'Mercado', 'value_cents' => 40000, 'value' => 'R$ 400,00', 'color' => '#4F7F4F']);
});

it('lists the categories that exist when the name is unknown', function () {
    $leo = User::factory()->create();
    householdOf($leo);

    $result = app(QueryTransactions::class)->handle(
        ['from' => '2026-10-01', 'to' => '2026-10-09', 'category' => 'viagens'],
        toolContextFor($leo),
    );

    expect($result->failed())->toBeTrue()
        ->and($result->data['error'])->toStartWith('Não encontrei a categoria "viagens". As de despesa são: Mercado, ');
});

it('returns the validation error to the AI instead of breaking', function () {
    $leo = User::factory()->create();
    householdOf($leo);

    $result = app(QueryTransactions::class)->handle(['from' => '09/10/2026', 'to' => '2026-10-09'], toolContextFor($leo));

    expect($result->failed())->toBeTrue()
        ->and($result->data['error'])->toStartWith('Argumentos inválidos:');
});

it('only counts the household of who asks and the private expenses that are theirs', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);

    Sanctum::actingAs($ana);
    spend($house, 'Compras', 20000, '2026-10-03', extra: ['visibility' => 'private']);
    Sanctum::actingAs($leo);
    spend($house, 'Mercado', 10000, '2026-10-03');

    $bia = User::factory()->create();
    $otherHouse = householdOf($bia);
    Sanctum::actingAs($bia);
    spend($otherHouse, 'Mercado', 99999, '2026-10-03');

    $query = ['from' => '2026-10-01', 'to' => '2026-10-09'];

    expect(app(QueryTransactions::class)->handle($query, toolContextFor($leo))->data['total_cents'])->toBe(10000)
        ->and(app(QueryTransactions::class)->handle($query, toolContextFor($ana))->data['total_cents'])->toBe(30000);
});

it('opens the default list with what is still missing', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $context = toolContextFor($leo);

    $list = listOf($house, 'Mercado');
    $added = app(ListService::class)->addItems($list, $leo, [
        ['text' => 'Leite', 'quantity' => '2'],
        ['text' => 'Café'],
        ['text' => 'Pão'],
    ])['added'];
    app(ListService::class)->check($list, $leo, [$added->last()->id]);

    $result = app(GetList::class)->handle([], $context);

    expect($result->data)->toMatchArray([
        'list' => 'Mercado',
        'open_count' => 2,
        'checked_count' => 1,
        'items' => [['text' => 'Leite', 'quantity' => '2'], ['text' => 'Café']],
    ]);
});

it('opens the invoice of the default card of who asks, or asks which card', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    $nubank = cardOf($house, $leo, ['name' => 'Nubank', 'is_default' => true]);
    cardOf($house, $ana, ['name' => 'Inter', 'is_default' => true]);

    Sanctum::actingAs($leo);
    // Depois do fechamento (dia 3): entra na fatura que fecha em novembro.
    spend($house, 'Compras', 30000, '2026-10-05', extra: ['payment_type' => 'credit_card', 'card_id' => $nubank->id]);

    expect(app(GetCardInvoice::class)->handle([], toolContextFor($leo))->data)->toMatchArray([
        'card' => 'Nubank',
        'month' => 'novembro',
        'closes_on' => '03/11',
        'due_on' => '10/11',
        'is_closed' => false,
        'total' => 'R$ 300,00',
        'count' => 1,
    ]);

    // Para a Ana, "a fatura" é a do cartão padrão dela.
    expect(app(GetCardInvoice::class)->handle([], toolContextFor($ana))->data['card'])->toBe('Inter');

    $bia = User::factory()->create();
    app(HouseholdService::class)->addMember($house, $bia, HouseholdRole::Member);

    // A Bia não tem cartão e a casa tem dois: precisa dizer qual.
    expect(app(GetCardInvoice::class)->handle([], toolContextFor($bia))->data['error'])
        ->toBe('Qual cartão? Os cartões da casa são: Inter, Nubank.');
});

it('shows how the budgets of the month are going', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    Sanctum::actingAs($leo);

    $this->putJson('/api/v1/budgets/'.categoryOf($house, 'Mercado')->id, ['limit_cents' => 80000]);
    spend($house, 'Mercado', 64000, '2026-10-04');

    expect(app(GetBudgetStatus::class)->handle([], toolContextFor($leo))->data)->toBe([
        'month' => 'outubro',
        'budgets' => [[
            'category' => 'Mercado',
            'limit' => 'R$ 800,00',
            'spent' => 'R$ 640,00',
            'remaining' => 'R$ 160,00',
            'percent' => 80,
            'status' => 'perto do limite',
        ]],
    ]);
});

it('does not offer the tools that write to a viewer', function () {
    $registry = app(ToolRegistry::class);
    $names = fn (HouseholdRole $role) => array_map(fn ($tool) => $tool->name(), $registry->availableFor($role));

    $writing = [
        'create_transactions', 'update_transaction', 'mark_bill_paid', 'delete_transaction', 'delete_list', 'add_list_items',
        'check_list_items', 'create_reminder', 'create_list', 'create_recurring_bill', 'create_recurring_income', 'set_budget',
    ];

    expect($names(HouseholdRole::Owner))->toContain('query_transactions', ...$writing)
        ->and($names(HouseholdRole::Viewer))->toContain('query_transactions', 'get_list')
        // Nenhuma das que gravam.
        ->and(array_intersect($names(HouseholdRole::Viewer), $writing))->toBe([])
        ->and($registry->find('apagar_tudo'))->toBeNull();
});
