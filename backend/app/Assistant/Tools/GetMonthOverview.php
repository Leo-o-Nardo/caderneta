<?php

namespace App\Assistant\Tools;

use App\Services\MonthSummary;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Month;

/** O "como está o mês?": os mesmos números da aba Resumo, resumidos para a IA contar em poucas frases. */
final class GetMonthOverview extends Tool
{
    public function __construct(private readonly MonthSummary $summary) {}

    public function name(): string
    {
        return 'get_month_overview';
    }

    public function description(): string
    {
        return 'Resumo de um mês: quanto entrou, quanto saiu, o saldo, as categorias com mais gasto, as faturas dos '
            .'cartões, os orçamentos estourados e as próximas contas. Use para "como está o mês?" ou "como foi setembro?".';
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
        return 'Montando o resumo do mês…';
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
        $summary = $this->summary->for($month, $context->today);
        $isCurrent = $month->isSameMonth($context->today);

        $budgets = collect($summary['budgets']);
        $invoices = array_filter($summary['invoices'], fn (array $invoice) => $invoice['total_cents'] > 0);

        return new ToolResult([
            'month' => DateRange::monthLabel($month, $context->today),
            'is_current_month' => $isCurrent,
            'income' => Money::format($summary['income_cents']),
            'expense' => Money::format($summary['expense_cents']),
            'balance' => Money::format($summary['balance_cents']),
            'top_categories' => array_map(
                fn (array $category) => ['name' => $category['name'], 'total' => Money::format($category['total_cents'])],
                array_slice($summary['by_category'], 0, 5),
            ),
            'invoices' => array_values(array_map(fn (array $invoice) => [
                'card' => $invoice['card']['name'],
                'total' => Money::format($invoice['total_cents']),
                'due_on' => self::shortDate($invoice['due_on']),
            ], $invoices)),
            'budgets_over' => $budgets->where('status', 'over')->pluck('category.name')->all(),
            'budgets_warning' => $budgets->where('status', 'warning')->pluck('category.name')->all(),
            // As contas a vencer são sempre a partir de hoje: só fazem sentido no mês atual.
            'next_bills' => $isCurrent ? array_map(fn (array $bill) => [
                'name' => $bill['bill']['name'],
                'due_on' => self::shortDate($bill['due_on']),
                'amount' => $bill['amount_cents'] !== null ? Money::format($bill['amount_cents']) : null,
                'is_estimate' => $bill['is_estimate'],
                'is_overdue' => $bill['status'] === 'overdue',
            ], array_slice($summary['upcoming_bills'], 0, 3)) : [],
        ]);
    }
}
