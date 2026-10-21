<?php

namespace App\Http\Resources;

use App\Models\Card;
use App\Models\Transaction;
use App\Support\InvoicePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Recebe ['card' => Card, 'period' => InvoicePeriod, 'items' => as compras da fatura]. */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Card $card */
        $card = $this->resource['card'];
        /** @var InvoicePeriod $period */
        $period = $this->resource['period'];
        /** @var Collection<int, Transaction> $items */
        $items = $this->resource['items'];

        $today = CarbonImmutable::now($request->user()->timezone)->startOfDay();

        return [
            'card' => ['id' => $card->id, 'name' => $card->name, 'owner' => $card->user->name],
            ...$period->toArray(),
            // No dia do fechamento a fatura já fechou: compras desse dia vão para a próxima.
            'is_closed' => $today->greaterThanOrEqualTo($period->closesOn),
            'total_cents' => $items->sum('amount_cents'),
            'installments_cents' => $items->whereNotNull('installment_plan_id')->sum('amount_cents'),
            'items' => TransactionResource::collection($items),
        ];
    }
}
