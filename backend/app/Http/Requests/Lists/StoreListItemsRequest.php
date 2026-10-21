<?php

namespace App\Http\Requests\Lists;

use Illuminate\Foundation\Http\FormRequest;

/** Sempre uma lista de itens, mesmo que seja um só: [{"text": "Leite", "quantity": "2"}]. */
class StoreListItemsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.text' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:20'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'items.*.price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'items.*.url' => ['nullable', 'url', 'max:500'],
            'items.*.due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
