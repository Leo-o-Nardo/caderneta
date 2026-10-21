<?php

namespace App\Assistant\Tools;

use App\Services\BudgetService;
use App\Support\BudgetUsage;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Month;

/** Os orçamentos da casa num mês: limite, gasto e quanto sobra (BudgetService, como no Resumo). */
final class GetBudgetStatus extends Tool
{
    private const STATUS_LABELS = [
        'ok' => 'dentro do limite',
        'warning' => 'perto do limite',
        'over' => 'estourado',
    ];

    public function __construct(private readonly BudgetService $budgets) {}

    public function name(): string
    {
        return 'get_budget_status';
    }

    public function description(): string
    {
        return 'Como estão os orçamentos por categoria num mês: o limite, quanto já foi gasto, quanto sobra e se '
            .'passou do limite. Sem mês, o mês atual.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => ['type' => 'string', 'description' => 'Mês no formato AAAA-MM. Sem ele, o mês atual.'],
            ],
        ];
    }

    public function status(): string
    {
        return 'Conferindo os orçamentos…';
    }

    protected function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $month = Month::parse($args['month'] ?? null, $context->timezone());
        $usages = $this->budgets->forMonth($month);

        $data = [
            'month' => DateRange::monthLabel($month, $context->today),
            'budgets' => $usages->map(fn (BudgetUsage $usage) => [
                'category' => $usage->budget->category->name,
                'limit' => Money::format($usage->budget->limit_cents),
                'spent' => Money::format($usage->spentCents),
                // Negativo quando estourou.
                'remaining' => Money::format($usage->budget->limit_cents - $usage->spentCents),
                'percent' => $usage->percent(),
                'status' => self::STATUS_LABELS[$usage->status()->value],
            ])->all(),
        ];

        if ($usages->isEmpty()) {
            $data['hint'] = 'A casa ainda não tem orçamentos. Eles são criados em Configurações, Orçamentos.';
        }

        return new ToolResult($data);
    }
}
