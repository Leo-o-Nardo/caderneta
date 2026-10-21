<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Enums\TransactionSource;
use App\Enums\Visibility;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Receitas fixas (salário): cada mês vira um lançamento comum, criado sozinho no dia pela rotina
 * incomes:launch. A regra guarda até que mês já lançou (launched_through), e é isso que impede
 * lançar duas vezes ou trazer de volta um lançamento apagado.
 *
 * A casa vem do HouseholdContext, como nos outros serviços: na API, do middleware; na rotina,
 * do próprio comando.
 */
class RecurringIncomeService
{
    public function __construct(private readonly TransactionService $transactions) {}

    /**
     * "Repetir todo mês": grava a receita informada e a regra juntas. O mês dela já conta como
     * lançado; a rotina segue a partir do próximo mês, no mesmo dia.
     */
    public function start(User $author, array $data): Transaction
    {
        return DB::transaction(function () use ($author, $data) {
            $date = CarbonImmutable::parse($data['occurred_on']);

            $income = new RecurringIncome([
                'description' => $data['description'],
                'category_id' => $data['category_id'],
                'amount_cents' => $data['amount_cents'],
                'day_of_month' => $date->day,
                'visibility' => $data['visibility'] ?? Visibility::Household->value,
            ]);
            $income->created_by = $author->id;
            $income->launched_through = $date->startOfMonth();
            $income->save();

            return $this->link($this->transactions->create($author, $data), $income);
        });
    }

    /**
     * Cadastro direto (pela Agenda ou pelo assistente), sem um lançamento junto. Começa no próximo
     * dia que cair: neste mês, se o dia ainda não passou; senão, no próximo. Se o dia é hoje, a
     * receita de hoje já é lançada, sem esperar a rotina.
     */
    public function create(User $author, array $data, CarbonImmutable $today): RecurringIncome
    {
        return DB::transaction(function () use ($author, $data, $today) {
            $income = new RecurringIncome($data);
            $income->created_by = $author->id;
            $income->launched_through = $this->firstDate($income->day_of_month, $today)->startOfMonth()->subMonth();
            $income->save();

            $this->launchDue($income, $author, $today);

            return $income->refresh();
        });
    }

    /** O primeiro dia em que uma receita cadastrada hoje cai (dia 31 vira o último dia nos meses curtos). */
    public function firstDate(int $day, CarbonImmutable $today): CarbonImmutable
    {
        $thisMonth = Month::day($today->startOfMonth(), $day);

        return $thisMonth->lt($today->startOfDay()) ? Month::day($today->startOfMonth()->addMonth(), $day) : $thisMonth;
    }

    /**
     * Lança os meses que já chegaram e ainda não foram lançados, cada um no seu dia. Se a rotina
     * ficou parada, alcança os meses que ficaram para trás. $today é "agora" no fuso de quem
     * recebe. Devolve quantos lançamentos criou.
     */
    public function launchDue(RecurringIncome $income, User $author, CarbonImmutable $today): int
    {
        return DB::transaction(function () use ($income, $author, $today) {
            // Trava a regra: duas rotinas ao mesmo tempo não lançam o mesmo mês.
            $income = RecurringIncome::withoutGlobalScopes()->lockForUpdate()->find($income->id);

            if ($income === null || $income->archived_at !== null) {
                return 0;
            }

            $launched = 0;

            // Comparando as datas como texto: o launched_through vem do banco sem fuso, e o $today
            // está no fuso da pessoa.
            while ($income->nextOn()->toDateString() <= $today->toDateString()) {
                $this->link($this->transactions->create($author, [
                    'kind' => CategoryKind::Income->value,
                    'amount_cents' => $income->amount_cents,
                    'description' => $income->description,
                    'occurred_on' => $income->nextOn()->toDateString(),
                    'category_id' => $income->category_id,
                    'visibility' => $income->visibility->value,
                ], TransactionSource::Recurring), $income);

                $income->launched_through = $income->launched_through->addMonth();
                $launched++;
            }

            $income->save();

            return $launched;
        });
    }

    /** "Parar de repetir": a regra sai da lista; os lançamentos já feitos continuam. */
    public function stop(RecurringIncome $income): void
    {
        $income->forceFill(['archived_at' => now()])->save();
    }

    private function link(Transaction $transaction, RecurringIncome $income): Transaction
    {
        $transaction->recurring_income_id = $income->id;
        $transaction->save();

        return $transaction;
    }
}
