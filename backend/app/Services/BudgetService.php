<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\BudgetUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    /**
     * Cada orçamento da casa com o gasto do mês. O gasto vem de uma consulta só, agrupada por
     * categoria, e respeita os privados: cada pessoa vê o orçamento com o que ela pode ver.
     *
     * @return Collection<int, BudgetUsage>
     */
    public function forMonth(CarbonImmutable $month): Collection
    {
        $month = $month->startOfMonth();

        $spent = Transaction::query()
            ->where('kind', CategoryKind::Expense->value)
            ->whereBetween('occurred_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->groupBy('category_id')
            ->selectRaw('category_id, sum(amount_cents) as total')
            ->toBase()
            ->pluck('total', 'category_id');

        return Budget::query()
            ->with('category')
            ->orderBy('category_id')
            ->get()
            ->map(fn (Budget $budget) => new BudgetUsage($budget, (int) ($spent[$budget->category_id] ?? 0)))
            ->toBase();
    }

    /**
     * O orçamento de uma categoria (ou a falta dele) com o gasto do mês e a média dos meses
     * anteriores: é o que a pessoa olha para escolher um limite realista.
     */
    public function detail(Category $category, CarbonImmutable $month): array
    {
        $this->ensureExpense($category);

        $month = $month->startOfMonth();
        [$average, $months] = $this->average($category, $month);

        return [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
            ],
            'limit_cents' => $category->budget?->limit_cents,
            'spent_cents' => $this->spent($category, $month, $month->endOfMonth()),
            // Null quando ainda não há mês fechado com lançamentos para tirar a média.
            'average_cents' => $average,
            'average_months' => $months,
        ];
    }

    /** Cria ou ajusta o limite: cada categoria tem no máximo um orçamento. */
    public function set(Category $category, int $limitCents): Budget
    {
        $this->ensureExpense($category);

        return Budget::query()->updateOrCreate(
            ['category_id' => $category->id],
            ['limit_cents' => $limitCents],
        );
    }

    public function remove(Category $category): void
    {
        Budget::query()->where('category_id', $category->id)->firstOrFail()->delete();
    }

    /**
     * A média por mês dos últimos $months meses fechados antes de $month. Só entram os meses a
     * partir do primeiro lançamento da casa: quem começou a usar o app no mês passado não tem a
     * média puxada para baixo por meses em que nada foi lançado.
     *
     * @return array{0: ?int, 1: int} a média e quantos meses entraram nela
     */
    private function average(Category $category, CarbonImmutable $month, int $months = 3): array
    {
        $first = Transaction::query()->min('occurred_on');

        if ($first === null) {
            return [null, 0];
        }

        // No mesmo fuso do mês, para a comparação e a contagem darem meses inteiros.
        $firstMonth = CarbonImmutable::parse($first, $month->timezone)->startOfMonth();
        $from = $month->subMonths($months)->max($firstMonth);
        $count = ($month->year - $from->year) * 12 + $month->month - $from->month;

        if ($count < 1) {
            return [null, 0];
        }

        return [intdiv($this->spent($category, $from, $month->subDay()), $count), $count];
    }

    /** O total gasto na categoria entre as duas datas, respeitando os lançamentos privados. */
    private function spent(Category $category, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_cents');
    }

    private function ensureExpense(Category $category): void
    {
        if ($category->kind !== CategoryKind::Expense) {
            throw ValidationException::withMessages(['category' => __('finance.budget_expense_only')]);
        }
    }
}
