<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Uma fatura do cartão, identificada pelo mês em que fecha. */
final readonly class InvoicePeriod
{
    public function __construct(
        public CarbonImmutable $month,
        public CarbonImmutable $closesOn,
        public CarbonImmutable $dueOn,
    ) {}

    public function toArray(): array
    {
        return [
            'month' => $this->month->format('Y-m'),
            'closes_on' => $this->closesOn->toDateString(),
            'due_on' => $this->dueOn->toDateString(),
        ];
    }
}
