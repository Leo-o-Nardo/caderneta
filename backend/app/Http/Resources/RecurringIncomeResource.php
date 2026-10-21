<?php

namespace App\Http\Resources;

use App\Models\RecurringIncome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringIncome */
class RecurringIncomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'day_of_month' => $this->day_of_month,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ],
            'visibility' => $this->visibility,
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            // O dia em que a rotina vai lançar o próximo mês.
            'next_on' => $this->resource->nextOn()->toDateString(),
            // O app usa para decidir se mostra os botões de editar e de parar.
            'can_edit' => $request->user()->can('update', $this->resource),
        ];
    }
}
