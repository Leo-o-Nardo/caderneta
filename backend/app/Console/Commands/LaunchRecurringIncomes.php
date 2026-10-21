<?php

namespace App\Console\Commands;

use App\Models\RecurringIncome;
use App\Services\RecurringIncomeService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Lança as receitas fixas que chegaram no dia. Roda de hora em hora (routes/console.php), para o
 * dia virar no fuso de cada pessoa. Pode rodar quantas vezes quiser: o que já foi lançado não se repete.
 *
 *   php artisan incomes:launch
 */
class LaunchRecurringIncomes extends Command
{
    protected $signature = 'incomes:launch';

    protected $description = 'Lança as receitas fixas (salário…) cujo dia já chegou';

    public function handle(RecurringIncomeService $incomes, HouseholdContext $context): int
    {
        $launched = 0;

        // Todas as casas: aqui não há contexto, então nada é filtrado até o comando preencher um.
        $rules = RecurringIncome::withoutGlobalScopes()
            ->whereNull('archived_at')
            ->with(['household', 'creator'])
            ->lazyById();

        foreach ($rules as $income) {
            $author = $income->creator;
            $role = $author?->roleIn($income->household);

            // Quem recebe saiu da casa (ou virou só leitura): a receita fica parada, sem lançar.
            if (! $role?->canWrite()) {
                continue;
            }

            // Cada regra roda na casa dela e como a pessoa que recebe, igual aos jobs do assistente.
            $context->set($income->household, $role, $author);

            try {
                $launched += $incomes->launchDue($income, $author, CarbonImmutable::now($author->timezone));
            } catch (Throwable $error) {
                // Uma regra com problema não pode impedir as outras de lançar.
                report($error);
            }
        }

        $this->info("Lançamentos criados: {$launched}.");

        return self::SUCCESS;
    }
}
