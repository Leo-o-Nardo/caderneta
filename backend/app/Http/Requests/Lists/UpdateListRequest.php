<?php

namespace App\Http\Requests\Lists;

use App\Enums\ListKind;
use App\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:40'],
            'kind' => ['sometimes', Rule::enum(ListKind::class)],
            'icon' => ['sometimes', 'required', 'string', 'max:30'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            // Só "true" faz sentido: para deixar de ser a padrão, outra lista vira a padrão.
            'is_default' => ['sometimes', 'accepted'],
        ];
    }
}
