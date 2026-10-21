<?php

namespace App\Alerts;

use App\Enums\CategoryKind;
use App\Models\Household;
use App\Models\Transaction;
use App\Services\BillService;
use App\Support\BillOccurrence;
use App\Support\HouseholdContext;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * O resumo da semana (alerts:weekly, de hora em hora): domingo, a partir das 19h no fuso de cada
 * pessoa, com os números dela. O gasto da semana (segunda a domingo) comparado com a anterior, as
 * categorias que mais pesaram e as contas dos próximos 7 dias. O texto é montado pelo app, sem IA.
 */
final class WeeklySummary
{
    // A partir de que hora de domingo (no fuso de cada pessoa) o resumo sai.
    public const HOUR = 19;

    // Quantas categorias aparecem nas barras.
    private const TOP_CATEGORIES = 4;

    // As contas que vencem nos próximos N dias.
    private const BILL_DAYS = 7;

    public function __construct(
        private readonly AlertService $alerts,
        private readonly BillService $bills,
        private readonly HouseholdContext $context,
    ) {}

    /** @return int quantos resumos saíram */
    public function sendDue(): int
    {
        $sent = 0;

        foreach (Household::query()->lazyById() as $household) {
            foreach ($household->members()->get() as $user) {
                $now = CarbonImmutable::now($user->timezone);

                if ($now->dayOfWeek !== CarbonInterface::SUNDAY || $now->hour < self::HOUR) {
                    continue;
                }

                // A semana ISO ("2026-W42"): um resumo por semana e por casa.
                $key = "weekly:{$household->id}:{$now->format('o-\WW')}";

                // Desligado, ou já mandado numa hora anterior: nem monta o resumo.
                if (! $user->wantsAlert('weekly') || $this->alerts->wasSent($user, $key)) {
                    continue;
                }

                // Os números como esta pessoa vê (com os privados dela, sem os dos outros).
                $card = $this->context->runAs($household, $user->membership->role, $user, fn () => $this->card($now));

                if ($card === null) {
                    continue;
                }

                $message = $this->alerts->send($user, $household, 'weekly', $key, self::text($card), $card);
                $sent += $message !== null ? 1 : 0;
            }
        }

        return $sent;
    }

    /** O card do resumo, ou null numa semana sem nada a dizer (nenhum gasto nas duas semanas e nenhuma conta). */
    private function card(CarbonImmutable $now): ?array
    {
        $today = $now->startOfDay();
        $from = $today->startOfWeek(CarbonInterface::MONDAY);

        $spent = $this->spent($from, $today);
        $previous = $this->spent($from->subWeek(), $from->subDay());

        // As que vencem de amanhã até daqui a 7 dias (as de hoje já tiveram o aviso do dia).
        $bills = $this->bills->upcoming($today, self::BILL_DAYS)
            ->filter(fn (BillOccurrence $occurrence) => $occurrence->dueOn->gt($today))
            ->values();

        if ($spent === 0 && $previous === 0 && $bills->isEmpty()) {
            return null;
        }

        return [
            'type' => 'weekly_summary',
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
            'spent_cents' => $spent,
            'previous_cents' => $previous,
            'categories' => $this->topCategories($from, $today),
            'bills' => $bills->map(fn (BillOccurrence $occurrence) => $occurrence->toArray())->all(),
        ];
    }

    private function spent(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) Transaction::query()
            ->where('kind', CategoryKind::Expense->value)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_cents');
    }

    /** As categorias que mais pesaram na semana, da maior para a menor. */
    private function topCategories(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Transaction::query()
            ->where('transactions.kind', CategoryKind::Expense->value)
            ->whereBetween('transactions.occurred_on', [$from->toDateString(), $to->toDateString()])
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->selectRaw('categories.id, categories.name, categories.icon, categories.color')
            ->selectRaw('sum(transactions.amount_cents) as total_cents')
            ->orderByDesc('total_cents')
            ->orderBy('categories.name')
            ->limit(self::TOP_CATEGORIES)
            ->toBase()
            ->get()
            ->map(fn (object $row) => [...(array) $row, 'total_cents' => (int) $row->total_cents])
            ->all();
    }

    /**
     * "Resumo da semana: R$ 350,00 em gastos, 13% a menos que na semana anterior. 1 conta vence
     * nos próximos 7 dias."
     */
    private static function text(array $card): string
    {
        $spent = $card['spent_cents'];
        $previous = $card['previous_cents'];

        $text = $spent === 0
            ? 'Resumo da semana: nenhum gasto lançado'
            : 'Resumo da semana: '.Money::format($spent).' em gastos';

        if ($spent > 0 && $previous > 0) {
            $change = (int) round(abs($spent - $previous) * 100 / $previous);

            $text .= match (true) {
                $change === 0 => ', o mesmo que na semana anterior',
                $spent < $previous => ", {$change}% a menos que na semana anterior",
                default => ", {$change}% a mais que na semana anterior",
            };
        }

        $bills = count($card['bills']);

        return $text.'.'.match ($bills) {
            0 => '',
            1 => ' 1 conta vence nos próximos 7 dias.',
            default => " {$bills} contas vencem nos próximos 7 dias.",
        };
    }
}
