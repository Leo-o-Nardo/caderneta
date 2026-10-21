<?php

namespace App\Http\Resources;

use App\Models\InstallmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InstallmentPlan */
class InstallmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'total_cents' => $this->total_cents,
            'installments' => $this->installments,
            'purchased_on' => $this->purchased_on->toDateString(),
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            'card' => $this->card ? ['id' => $this->card->id, 'name' => $this->card->name] : null,
            'visibility' => $this->visibility,
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'can_edit' => $request->user()->can('update', $this->resource),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
