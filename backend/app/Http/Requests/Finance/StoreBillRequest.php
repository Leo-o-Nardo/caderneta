<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'category_id' => ['bail', 'required', 'integer', $this->categoryOfKind(CategoryKind::Expense)],
            // Com amount_varies, o valor esperado é só uma estimativa (opcional) e o valor exato é
            // informado a cada pagamento (luz, água).
            'expected_cents' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
            'amount_varies' => ['sometimes', 'boolean'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'autopay' => ['sometimes', 'boolean'],
            // Opcional: sem ele, a conta começa neste mês ou no próximo, se o vencimento já passou.
            'starts_on' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
