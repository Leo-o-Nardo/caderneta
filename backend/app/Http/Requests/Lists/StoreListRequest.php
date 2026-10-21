<?php

namespace App\Http\Requests\Lists;

use App\Enums\ListKind;
use App\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'kind' => ['required', Rule::enum(ListKind::class)],
            // Sem ícone, usa o padrão do tipo.
            'icon' => ['nullable', 'string', 'max:30'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
