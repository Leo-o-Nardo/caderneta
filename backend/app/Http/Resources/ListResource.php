<?php

namespace App\Http\Resources;

use App\Models\HouseholdList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HouseholdList */
class ListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'icon' => $this->icon,
            'is_default' => $this->is_default,
            'visibility' => $this->visibility,
            'position' => $this->position,
            'items_count' => $this->whenCounted('items'),
            'open_items_count' => $this->whenCounted('open_items'),
            // Soma dos preços dos itens em aberto: "quanto daria comprar tudo da lista de desejos?".
            'open_total_cents' => $this->whenAggregated('open_items', 'price_cents', 'sum', fn ($sum) => (int) $sum),
        ];
    }
}
