<?php

namespace App\Assistant\Actions;

use App\Assistant\Tools\ToolContext;
use App\Assistant\Tools\ToolResult;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Acha o lançamento de que a pessoa fala ("o uber de ontem"), para apagar ou corrigir. Os privados
 * de outras pessoas nem entram na busca (o filtro automático cuida disso).
 */
trait FindsTransactions
{
    /**
     * Pelo id (quando já veio dos candidatos) ou pela descrição, data e valor. Não achou: erro.
     * Achou vários: os candidatos, para a IA perguntar qual e chamar de novo com o transaction_id.
     */
    protected function findTransaction(array $args, ToolContext $context): Transaction|ToolResult
    {
        $matches = $this->searchTransactions($args, $context);

        if ($matches->isEmpty()) {
            return ToolResult::error('Não encontrei esse lançamento. Dá para conferir pela lista de lançamentos.');
        }

        if ($matches->count() > 1) {
            $options = $matches->map(fn (Transaction $item) => sprintf(
                '%s %s em %s',
                $item->description,
                self::money($item->amount_cents),
                $item->occurred_on->format('d/m'),
            ));

            // Não é erro: a IA pergunta qual e depois chama de novo com o transaction_id.
            return new ToolResult([
                'status' => 'ambiguous',
                'question' => sprintf('Encontrei %d lançamentos: %s. Qual deles?', $matches->count(), self::join($options->all())),
                'candidates' => $matches->map(fn (Transaction $item) => [
                    'transaction_id' => $item->id,
                    'description' => $item->description,
                    'amount' => self::money($item->amount_cents),
                    'date' => $item->occurred_on->toDateString(),
                ])->all(),
            ]);
        }

        return $matches->first();
    }

    /**
     * Pelo id, ou pela descrição (e data e valor, se ditos) nos últimos 90 dias. No máximo 5 candidatos.
     *
     * @return Collection<int, Transaction>
     */
    private function searchTransactions(array $args, ToolContext $context): Collection
    {
        $query = Transaction::query()->with(['category', 'card', 'installmentPlan']);

        if (isset($args['transaction_id'])) {
            return $query->whereKey($args['transaction_id'])->get();
        }

        return $query
            ->whereLike('description', "%{$args['description']}%")
            ->when(
                $args['date'] ?? null,
                fn (Builder $query, string $date) => $query->where('occurred_on', $date),
                fn (Builder $query) => $query->where('occurred_on', '>=', $context->today->subDays(90)->toDateString()),
            )
            ->when(isset($args['amount']), fn (Builder $query) => $query->where('amount_cents', self::cents($args['amount'])))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }
}
