<?php

namespace App\Http\Requests\Lists;

use Illuminate\Foundation\Http\FormRequest;

class ReorderListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Todos os ids da lista, na nova ordem.
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
