<?php

namespace App\Http\Resources;

use App\Models\Card;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Card */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $today = CarbonImmutable::now($user->timezone);
        $invoices = app(InvoiceService::class);
        $current = $this->invoiceFor($today);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'closing_day' => $this->closing_day,
            'due_day' => $this->due_day,
            'limit_cents' => $this->limit_cents,
            // Tudo o que ainda não venceu (fatura aberta, parcelas futuras e a fechada não vencida).
            'limit_used_cents' => $invoices->limitUsed($this->resource, $today),
            'color' => $this->color,
            'is_default' => $this->is_default,
            'is_mine' => $this->user_id === $user->id,
            'owner' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            // A fatura em que cairia uma compra feita hoje (no fuso do usuário), com o total até agora.
            'current_invoice' => [
                ...$current->toArray(),
                'total_cents' => $invoices->total($this->resource, $current),
            ],
        ];
    }
}
