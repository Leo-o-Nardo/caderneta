<?php

namespace App\Support;

use App\Enums\BudgetStatus;
use App\Models\Budget;

/** Quanto de um orçamento já foi gasto num mês. */
final readonly class BudgetUsage
{
    public function __construct(
        public Budget $budget,
        public int $spentCents,
    ) {}

    public function percent(): int
    {
        return intdiv($this->spentCents * 100, $this->budget->limit_cents);
    }

    public function status(): BudgetStatus
    {
        return BudgetStatus::fromPercent($this->percent());
    }

    public function toArray(): array
    {
        $category = $this->budget->category;

        return [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
            ],
            'limit_cents' => $this->budget->limit_cents,
            'spent_cents' => $this->spentCents,
            // Negativo quando o orçamento estourou.
            'remaining_cents' => $this->budget->limit_cents - $this->spentCents,
            'percent' => $this->percent(),
            'status' => $this->status()->value,
        ];
    }
}
