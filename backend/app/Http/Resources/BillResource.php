<?php

namespace App\Http\Resources;

use App\Models\RecurringBill;
use App\Services\BillService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringBill */
class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = CarbonImmutable::now($request->user()->timezone);
        $bills = app(BillService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'expected_cents' => $this->expected_cents,
            'amount_varies' => $this->amount_varies,
            // O valor usado nos totais: o fixo, ou a média dos últimos pagamentos se o valor varia.
            'estimate_cents' => $bills->estimate($this->resource),
            'due_day' => $this->due_day,
            'autopay' => $this->autopay,
            'starts_on' => $this->starts_on->format('Y-m'),
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ],
            // O mês que um "Paguei" agora quitaria; null quando está tudo em dia.
            'open' => $bills->openOccurrence($this->resource, $today)?->toArray(withBill: false),
        ];
    }
}
