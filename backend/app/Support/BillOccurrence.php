<?php

namespace App\Support;

use App\Enums\BillStatus;
use App\Models\BillPayment;
use App\Models\RecurringBill;
use Carbon\CarbonImmutable;

/** Um mês de uma conta fixa, calculado pelo BillService. */
final readonly class BillOccurrence
{
    public function __construct(
        public RecurringBill $bill,
        public CarbonImmutable $month,
        public CarbonImmutable $dueOn,
        // O valor pago, se já foi paga; senão o esperado ou a estimativa (nulo quando não há nenhum).
        public ?int $amountCents,
        // O valor é uma estimativa (conta de valor variável ainda não paga): o app mostra "~R$ 170".
        public bool $isEstimate,
        public BillStatus $status,
        // Negativo quando já venceu.
        public int $daysUntilDue,
        public ?BillPayment $payment,
    ) {}

    /** Ainda não foi paga nem pulada. */
    public function isOpen(): bool
    {
        return $this->payment === null;
    }

    public function toArray(bool $withBill = true): array
    {
        $bill = $withBill ? [
            'bill' => [
                'id' => $this->bill->id,
                'name' => $this->bill->name,
                'amount_varies' => $this->bill->amount_varies,
                'autopay' => $this->bill->autopay,
                'category' => [
                    'id' => $this->bill->category->id,
                    'name' => $this->bill->category->name,
                    'icon' => $this->bill->category->icon,
                    'color' => $this->bill->category->color,
                ],
            ],
        ] : [];

        return [
            ...$bill,
            'month' => $this->month->format('Y-m'),
            'due_on' => $this->dueOn->toDateString(),
            'amount_cents' => $this->amountCents,
            'is_estimate' => $this->isEstimate,
            'status' => $this->status->value,
            'days_until_due' => $this->daysUntilDue,
            'transaction_id' => $this->payment?->transaction_id,
        ];
    }
}
