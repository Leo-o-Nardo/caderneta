<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCategoriesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Despesas e receitas têm cada uma a própria ordem.
            'kind' => ['required', Rule::enum(CategoryKind::class)],
            // Todas as categorias ativas do tipo, na nova ordem.
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
