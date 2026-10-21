<?php

namespace App\Alerts;

use App\Enums\BudgetStatus;
use App\Models\Household;
use App\Services\BudgetService;
use App\Support\BudgetUsage;
use App\Support\HouseholdContext;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * O aviso de orçamento: a categoria passou de 80% ou de 100% do limite do mês. Vai para todos da
 * casa, cada um com o gasto que vê no Resumo (os lançamentos privados dos outros ficam de fora),
 * e cada degrau sai uma vez por mês. Quem chama é o job CheckBudgetAlerts, depois de cada gasto
 * lançado ou editado.
 */
final class BudgetAlerts
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly BudgetService $budgets,
        private readonly HouseholdContext $context,
    ) {}

    /** @return int quantos avisos saíram */
    public function check(Household $household, int $categoryId): int
    {
        $sent = 0;

        foreach ($household->members()->get() as $user) {
            // O mês de cada pessoa, no fuso dela.
            $now = CarbonImmutable::now($user->timezone);

            // O gasto como esta pessoa vê no Resumo.
            $usage = $this->context->runAs(
                $household,
                $user->membership->role,
                $user,
                fn () => $this->budgets->forMonth($now)->first(fn (BudgetUsage $item) => $item->budget->category_id === $categoryId),
            );

            // Sem orçamento (apagado nesse meio-tempo) ou ainda abaixo de 80%.
            $threshold = match ($usage?->status()) {
                BudgetStatus::Over => 100,
                BudgetStatus::Warning => 80,
                default => null,
            };

            if ($threshold === null) {
                continue;
            }

            $message = $this->alerts->send(
                $user,
                $household,
                'budget',
                "budget:{$categoryId}:{$now->format('Y-m')}:{$threshold}",
                self::text($usage, $threshold),
                self::card($usage, $now, $threshold),
            );

            $sent += $message !== null ? 1 : 0;
        }

        return $sent;
    }

    /** "Mercado chegou a 85% do limite do mês: R$ 850,00 de R$ 1.000,00." */
    private static function text(BudgetUsage $usage, int $threshold): string
    {
        $name = $usage->budget->category->name;
        $limit = $usage->budget->limit_cents;

        $status = match (true) {
            $threshold === 80 => "chegou a {$usage->percent()}% do limite do mês",
            $usage->spentCents > $limit => 'passou do limite do mês',
            default => 'chegou ao limite do mês',
        };

        return sprintf('%s %s: %s de %s.', $name, $status, Money::format($usage->spentCents), Money::format($limit));
    }

    /** O card: a barra do mês e "Ver lançamentos" (os gastos da categoria no mês). */
    private static function card(BudgetUsage $usage, CarbonImmutable $now, int $threshold): array
    {
        return [
            'type' => 'budget_alert',
            ...$usage->toArray(),
            'month' => $now->format('Y-m'),
            // 80 ou 100: o degrau que este aviso marca.
            'threshold' => $threshold,
        ];
    }
}
