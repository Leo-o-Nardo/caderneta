<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'amount_cents' => $this->amount_cents,
            'description' => $this->description,
            'occurred_on' => $this->occurred_on->toDateString(),
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ],
            'payment_type' => $this->payment_type,
            'card' => $this->card ? ['id' => $this->card->id, 'name' => $this->card->name] : null,
            'invoice_month' => $this->invoice_month?->format('Y-m'),
            'installment' => $this->installment_plan_id ? [
                'plan_id' => $this->installment_plan_id,
                'number' => $this->installment_number,
                'count' => $this->installmentPlan->installments,
            ] : null,
            // A receita fixa de onde veio o lançamento, enquanto ela continua repetindo.
            'recurring_income' => $this->recurringIncome && $this->recurringIncome->archived_at === null ? [
                'id' => $this->recurringIncome->id,
                'day_of_month' => $this->recurringIncome->day_of_month,
            ] : null,
            'visibility' => $this->visibility,
            'source' => $this->source,
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            // O app usa para decidir se mostra os botões de editar e apagar.
            'can_edit' => $request->user()->can('update', $this->resource),
        ];
    }
}
