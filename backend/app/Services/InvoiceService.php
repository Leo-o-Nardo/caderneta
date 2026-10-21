<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Transaction;
use App\Support\InvoiceCalendar;
use App\Support\InvoicePeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** O que entrou numa fatura: as compras do cartão marcadas com o mês dessa fatura. */
class InvoiceService
{
    /** @return Collection<int, Transaction> */
    public function items(Card $card, InvoicePeriod $period): Collection
    {
        return $this->query($card, $period)
            ->with(['category', 'card', 'creator', 'installmentPlan'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();
    }

    public function total(Card $card, InvoicePeriod $period): int
    {
        return (int) $this->query($card, $period)->sum('amount_cents');
    }

    /**
     * Quanto do limite está em uso: tudo o que ainda não venceu. Entram a fatura em aberto, as
     * parcelas das faturas futuras e a fatura que já fechou mas ainda não chegou no vencimento.
     */
    public function limitUsed(Card $card, CarbonInterface $today): int
    {
        $open = $card->invoiceFor($today);
        $previous = InvoiceCalendar::forMonth($card->closing_day, $card->due_day, $open->month->subMonth());
        $from = $previous->dueOn->greaterThanOrEqualTo($today->copy()->startOfDay()) ? $previous->month : $open->month;

        return (int) Transaction::query()
            ->where('card_id', $card->id)
            ->where('invoice_month', '>=', $from->toDateString())
            ->sum('amount_cents');
    }

    private function query(Card $card, InvoicePeriod $period): Builder
    {
        return Transaction::query()
            ->where('card_id', $card->id)
            ->where('invoice_month', $period->month->toDateString());
    }
}
