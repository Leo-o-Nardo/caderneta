<?php

namespace App\Http\Requests\Lists;

use Illuminate\Foundation\Http\FormRequest;

/** Também é a rota do toque na tela: {"checked": true} marca o item como pego. */
class UpdateListItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'required', 'string', 'max:120'],
            'quantity' => ['sometimes', 'nullable', 'string', 'max:20'],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'checked' => ['sometimes', 'boolean'],
        ];
    }
}
