<?php

use App\Assistant\AssistantContext;
use App\Assistant\Llm\Fake\FakeIntents;
use App\Assistant\Llm\Fake\FakeReplies;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\ToolCall;
use App\Enums\HouseholdRole;
use Carbon\CarbonImmutable;

/** Uma casa de exemplo numa sexta-feira, 09/10/2026, às 10h de Brasília. */
function fakeAssistantContext(): AssistantContext
{
    return new AssistantContext(
        today: CarbonImmutable::parse('2026-10-09 10:00', 'America/Sao_Paulo'),
        userName: 'Leo Souza',
        householdName: 'Casa do Leo',
        role: HouseholdRole::Owner,
        categories: [
            ['name' => 'Mercado', 'kind' => 'expense'],
            ['name' => 'Alimentação', 'kind' => 'expense'],
            ['name' => 'Delivery', 'kind' => 'expense'],
            ['name' => 'Transporte', 'kind' => 'expense'],
            ['name' => 'Contas da casa', 'kind' => 'expense'],
            ['name' => 'Compras', 'kind' => 'expense'],
            ['name' => 'Salário', 'kind' => 'income'],
        ],
        cards: [
            ['name' => 'Nubank', 'owner' => 'Leo Souza', 'is_mine' => true, 'is_default' => true],
            ['name' => 'Inter', 'owner' => 'Ana', 'is_mine' => false, 'is_default' => true],
        ],
        lists: [
            ['name' => 'Mercado', 'kind' => 'shopping', 'is_default' => true],
            ['name' => 'Desejos', 'kind' => 'wishlist', 'is_default' => false],
            ['name' => 'Casa', 'kind' => 'todo', 'is_default' => false],
        ],
        bills: [
            ['name' => 'Internet', 'amount_varies' => false],
            ['name' => 'Luz', 'amount_varies' => true],
        ],
    );
}

it('picks the tool and the arguments for common phrases', function (string $text, string $tool, array $args) {
    $call = FakeIntents::match($text, fakeAssistantContext());

    expect($call)->not->toBeNull()
        ->and($call->name)->toBe($tool)
        ->and($call->args)->toBe($args);
})->with([
    'spending in a category this month' => [
        'quanto gastei com mercado esse mês?', 'query_transactions',
        ['from' => '2026-10-01', 'to' => '2026-10-09', 'kind' => 'expense', 'category' => 'Mercado'],
    ],
    'spending last week, by category' => [
        'Quanto gastamos semana passada?', 'query_transactions',
        ['from' => '2026-09-28', 'to' => '2026-10-04', 'kind' => 'expense', 'group_by' => 'category'],
    ],
    'spending in a past month' => [
        'quanto gastei em setembro', 'query_transactions',
        ['from' => '2026-09-01', 'to' => '2026-09-30', 'kind' => 'expense', 'group_by' => 'category'],
    ],
    'a month that did not come yet is from last year' => [
        'quanto gastei em novembro?', 'query_transactions',
        ['from' => '2025-11-01', 'to' => '2025-11-30', 'kind' => 'expense', 'group_by' => 'category'],
    ],
    'the longest category name wins' => [
        'quanto gastei com contas da casa ontem', 'query_transactions',
        ['from' => '2026-10-08', 'to' => '2026-10-08', 'kind' => 'expense', 'category' => 'Contas da casa'],
    ],
    'income' => [
        'quanto recebi esse mês', 'query_transactions',
        ['from' => '2026-10-01', 'to' => '2026-10-09', 'kind' => 'income', 'group_by' => 'category'],
    ],
    'spending on a card, by day' => [
        'quanto gastei no nubank por dia', 'query_transactions',
        ['from' => '2026-10-01', 'to' => '2026-10-09', 'kind' => 'expense', 'card' => 'Nubank', 'group_by' => 'day'],
    ],
    'invoice of a card' => ['quanto está a fatura do Inter?', 'get_card_invoice', ['card' => 'Inter']],
    'month overview' => ['como está o mês?', 'get_month_overview', []],
    'overview of last month' => ['como foi o mês passado', 'get_month_overview', ['month' => '2026-09']],
    'bills' => ['quais contas vencem esse mês?', 'list_bills', []],
    'budgets' => ['como estão os orçamentos', 'get_budget_status', []],
    'a list by name' => ['o que tem na lista de desejos?', 'get_list', ['list' => 'Desejos']],
    'reminders' => ['quais são meus lembretes', 'list_reminders', []],
    'a question with "foi" is not a correction' => ['a fatura do inter foi paga?', 'get_card_invoice', ['card' => 'Inter']],
    'a budget question without a value' => ['quanto falta no orçamento do mercado?', 'get_budget_status', []],
]);

it('turns what the person says into actions', function (string $text, string $tool, array $args) {
    $call = FakeIntents::match($text, fakeAssistantContext());

    expect($call)->not->toBeNull()
        ->and($call->name)->toBe($tool)
        ->and($call->args)->toBe($args);
})->with([
    'two expenses at once, with the category by keyword' => [
        'gastei 12 de café e 30 de uber', 'create_transactions',
        ['items' => [
            ['kind' => 'expense', 'amount' => 12.0, 'description' => 'Café', 'category' => 'Alimentação'],
            ['kind' => 'expense', 'amount' => 30.0, 'description' => 'Uber', 'category' => 'Transporte'],
        ]],
    ],
    'cents, yesterday and pix' => [
        'gastei 45,90 no almoço ontem no pix', 'create_transactions',
        ['items' => [
            ['kind' => 'expense', 'amount' => 45.9, 'description' => 'Almoço', 'category' => 'Alimentação', 'date' => '2026-10-08', 'payment' => 'pix'],
        ]],
    ],
    'installments on a card' => [
        'comprei uma tv de 2.500 em 10x no nubank', 'create_transactions',
        ['items' => [
            ['kind' => 'expense', 'amount' => 2500.0, 'description' => 'TV', 'category' => 'Compras', 'payment' => 'credit_card', 'card' => 'Nubank', 'installments' => 10],
        ]],
    ],
    'an income' => [
        'recebi 8.500 de salário', 'create_transactions',
        ['items' => [['kind' => 'income', 'amount' => 8500.0, 'description' => 'Salário', 'category' => 'Salário']]],
    ],
    'paying a bill without the amount' => ['paguei a luz', 'mark_bill_paid', ['bill' => 'Luz']],
    'paying a bill with the amount' => ['paguei 182,40 de luz', 'mark_bill_paid', ['bill' => 'Luz', 'amount' => 182.4]],
    'deleting' => ['apaga o uber de ontem', 'delete_transaction', ['description' => 'uber', 'date' => '2026-10-08']],
    'adding to the default list' => [
        'coloca leite, café e pão na lista', 'add_list_items',
        ['items' => [['text' => 'Leite'], ['text' => 'Café'], ['text' => 'Pão']]],
    ],
    'adding to a named list' => [
        'coloca fone de ouvido na lista de desejos', 'add_list_items',
        ['list' => 'Desejos', 'items' => [['text' => 'Fone de ouvido']]],
    ],
    'checking items' => ['peguei o leite e o pão', 'check_list_items', ['items' => ['Leite', 'Pão']]],
    'a reminder on a day, keeping the capitals' => [
        'me lembra de pagar o IPVA dia 20', 'create_reminder',
        ['text' => 'pagar o IPVA', 'when' => '2026-10-20'],
    ],
    'a monthly reminder with a time' => [
        'me lembra de trocar o filtro amanhã às 10h todo mês', 'create_reminder',
        ['text' => 'trocar o filtro', 'when' => '2026-10-10 10:00', 'recurrence' => 'monthly'],
    ],
    'an income in thousands' => [
        'recebi 2 mil de freela', 'create_transactions',
        ['items' => [['kind' => 'income', 'amount' => 2000.0, 'description' => 'Freela']]],
    ],
    'correcting the amount, with the old one to find it' => [
        'o café de ontem foi 15, não 12', 'update_transaction',
        ['description' => 'café', 'date' => '2026-10-08', 'amount' => 12.0, 'new_amount' => 15.0],
    ],
    'correcting the category' => [
        'muda a categoria do uber para compras', 'update_transaction',
        ['description' => 'uber', 'new_category' => 'Compras'],
    ],
    'correcting the card' => ['o almoço foi no nubank', 'update_transaction', ['description' => 'almoço', 'new_card' => 'Nubank']],
    'a fixed bill' => [
        'cadastra a internet de 99,90 todo dia 10', 'create_recurring_bill',
        ['name' => 'Internet', 'amount' => 99.9, 'due_day' => 10],
    ],
    'a fixed bill whose amount varies' => [
        'a água vence dia 20 e o valor muda', 'create_recurring_bill',
        ['name' => 'Água', 'amount_varies' => true, 'due_day' => 20],
    ],
    'a salary every month, in thousands' => [
        'recebo 5 mil de salário todo dia 5', 'create_recurring_income',
        ['description' => 'Salário', 'amount' => 5000.0, 'day_of_month' => 5, 'category' => 'Salário'],
    ],
    'a budget' => ['limite de 800 no mercado', 'set_budget', ['category' => 'Mercado', 'amount' => 800.0]],
    'a new list with items' => [
        'cria uma lista de churrasco com carne, carvão e gelo', 'create_list',
        ['name' => 'Churrasco', 'kind' => 'shopping', 'items' => ['Carne', 'Carvão', 'Gelo']],
    ],
    'a new wishlist' => ['cria uma lista de presentes de natal', 'create_list', ['name' => 'Presentes de natal', 'kind' => 'wishlist']],
    'deleting a list' => ['apaga a lista de churrasco', 'delete_list', ['list' => 'Churrasco']],
]);

it('does not call a tool for small talk or unknown requests', function (string $text) {
    expect(FakeIntents::match($text, fakeAssistantContext()))->toBeNull();
})->with(['bom dia', 'obrigado!', 'compra um carro pra mim']);

it('writes the answer only with the numbers the tool returned', function () {
    $single = LlmMessage::toolResult(new ToolCall('fake', 'query_transactions', []), [
        'period' => ['from' => '2026-10-01', 'to' => '2026-10-09', 'label' => 'de 1º a 9 de outubro'],
        'kind' => 'expense',
        'category' => 'Mercado',
        'count' => 3,
        'total_cents' => 64000,
        'total' => 'R$ 640,00',
    ]);

    $grouped = LlmMessage::toolResult(new ToolCall('fake', 'query_transactions', []), [
        'period' => ['from' => '2026-09-01', 'to' => '2026-09-30', 'label' => 'em setembro'],
        'kind' => 'expense',
        'count' => 12,
        'total_cents' => 229280,
        'total' => 'R$ 2.292,80',
        'groups' => [
            ['label' => 'Delivery', 'count' => 4, 'total_cents' => 41280, 'total' => 'R$ 412,80'],
            ['label' => 'Mercado', 'count' => 5, 'total_cents' => 98000, 'total' => 'R$ 980,00'],
            ['label' => 'Moradia', 'count' => 1, 'total_cents' => 90000, 'total' => 'R$ 900,00'],
        ],
    ]);

    expect(FakeReplies::fromTools([$single]))
        ->toBe('De 1º a 9 de outubro, foram R$ 640,00 com Mercado, em 3 lançamentos.')
        ->and(FakeReplies::fromTools([$grouped]))
        ->toBe('Em setembro, os gastos somam R$ 2.292,80 em 12 lançamentos. Os maiores: Mercado (R$ 980,00), Moradia (R$ 900,00) e Delivery (R$ 412,80).');
});

it('repeats the error of the tool to the person', function () {
    $result = LlmMessage::toolResult(new ToolCall('fake', 'get_list', []), ['error' => 'Não encontrei a lista "Viagem".']);

    expect(FakeReplies::fromTools([$result]))->toBe('Não encontrei a lista "Viagem".');
});
