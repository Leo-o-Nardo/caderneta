<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Transaction;
use App\Support\BillOccurrence;
use App\Support\BudgetUsage;
use App\Support\InvoiceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * O resumo de um mês da casa atual. Como as consultas passam pelo modelo Transaction, os
 * filtros automáticos de casa e de privados já valem aqui: cada pessoa vê o resumo que pode ver.
 */
class MonthSummary
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BudgetService $budgets,
        private readonly BillService $bills,
    ) {}

    /** $today ("agora" no fuso do usuário) define as contas a vencer, que são sempre a partir de hoje. */
    public function for(CarbonImmutable $month, CarbonImmutable $today): array
    {
        $month = $month->startOfMonth();

        $totals = $this->monthQuery($month)
            ->selectRaw("coalesce(sum(transactions.amount_cents) filter (where transactions.kind = 'income'), 0) as income_cents")
            ->selectRaw("coalesce(sum(transactions.amount_cents) filter (where transactions.kind = 'expense'), 0) as expense_cents")
            ->toBase()
            ->first();

        $income = (int) $totals->income_cents;
        $expense = (int) $totals->expense_cents;

        return [
            'month' => $month->format('Y-m'),
            'income_cents' => $income,
            'expense_cents' => $expense,
            'balance_cents' => $income - $expense,
            'by_category' => $this->byCategory($month),
            'by_member' => $this->byMember($month),
            'invoices' => $this->invoices($month),
            'budgets' => $this->budgets->forMonth($month)->map(fn (BudgetUsage $usage) => $usage->toArray())->all(),
            'upcoming_bills' => $this->bills->upcoming($today)->map(fn (BillOccurrence $bill) => $bill->toArray())->all(),
        ];
    }

    private function byCategory(CarbonImmutable $month): array
    {
        return $this->monthQuery($month)
            ->where('transactions.kind', 'expense')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->selectRaw('categories.id, categories.name, categories.icon, categories.color')
            ->selectRaw('sum(transactions.amount_cents) as total_cents')
            ->orderByDesc('total_cents')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [...(array) $row, 'total_cents' => (int) $row->total_cents])
            ->all();
    }

    /** Quanto cada pessoa da casa gastou (pergunta comum: "quanto cada um gastou com mercado?"). */
    private function byMember(CarbonImmutable $month): array
    {
        return $this->monthQuery($month)
            ->where('transactions.kind', 'expense')
            ->join('users', 'users.id', '=', 'transactions.created_by')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id, users.name, sum(transactions.amount_cents) as total_cents')
            ->orderByDesc('total_cents')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [...(array) $row, 'total_cents' => (int) $row->total_cents])
            ->all();
    }

    /** As faturas que fecham neste mês, uma por cartão ativo. */
    private function invoices(CarbonImmutable $month): array
    {
        return Card::query()
            ->active()
            ->with('user')
            ->orderBy('name')
            ->get()
            ->map(function (Card $card) use ($month) {
                $period = InvoiceCalendar::forMonth($card->closing_day, $card->due_day, $month);

                return [
                    'card' => ['id' => $card->id, 'name' => $card->name, 'owner' => $card->user->name],
                    ...$period->toArray(),
                    'total_cents' => $this->invoices->total($card, $period),
                ];
            })
            ->all();
    }

    private function monthQuery(CarbonImmutable $month): Builder
    {
        return Transaction::query()->whereBetween('transactions.occurred_on', [
            $month->toDateString(),
            $month->endOfMonth()->toDateString(),
        ]);
    }
}
