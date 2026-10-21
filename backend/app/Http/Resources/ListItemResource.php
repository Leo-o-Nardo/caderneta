<?php

namespace App\Http\Resources;

use App\Models\ListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListItem */
class ListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list_id' => $this->list_id,
            'text' => $this->text,
            'quantity' => $this->quantity,
            'note' => $this->note,
            'price_cents' => $this->price_cents,
            'url' => $this->url,
            'due_on' => $this->due_on?->toDateString(),
            'position' => $this->position,
            'is_checked' => $this->checked_at !== null,
            'checked_at' => $this->checked_at?->setTimezone($request->user()->timezone)->toIso8601String(),
            'checked_by' => $this->checker ? ['id' => $this->checker->id, 'name' => $this->checker->name] : null,
        ];
    }
}
