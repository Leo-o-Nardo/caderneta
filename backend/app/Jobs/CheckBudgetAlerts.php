<?php

namespace App\Jobs;

use App\Alerts\BudgetAlerts;
use App\Models\Budget;
use App\Models\Household;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Confere o orçamento de uma categoria depois de um gasto lançado ou editado (pelo app, pelo
 * assistente, por uma conta paga ou por um parcelamento). Roda pela fila, para não atrasar a
 * resposta de quem lançou.
 */
final class CheckBudgetAlerts implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $householdId,
        public readonly int $categoryId,
    ) {}

    /**
     * Põe a conferência na fila só quando ela pode dar aviso: gasto do mês atual (com um dia de
     * folga para cada lado, por causa dos fusos) numa categoria com orçamento. Sai depois do
     * commit, para o job já enxergar o gasto gravado.
     *
     * Não pode se chamar "queue": num job, o Laravel usa um método com esse nome para enfileirar.
     */
    public static function dispatchFor(int $householdId, int $categoryId, CarbonInterface $date): void
    {
        $month = $date->format('Y-m');

        if ($month !== now()->subDay()->format('Y-m') && $month !== now()->addDay()->format('Y-m')) {
            return;
        }

        // O orçamento é da categoria, e a categoria é de uma casa só: não precisa do filtro de casa.
        if (! Budget::withoutGlobalScopes()->where('category_id', $categoryId)->exists()) {
            return;
        }

        self::dispatch($householdId, $categoryId)->afterCommit();
    }

    public function handle(BudgetAlerts $alerts): void
    {
        $household = Household::query()->find($this->householdId);

        if ($household !== null) {
            $alerts->check($household, $this->categoryId);
        }
    }
}
