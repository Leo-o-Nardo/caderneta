<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'category_id' => ['sometimes', 'bail', 'required', 'integer', $this->categoryOfKind(CategoryKind::Expense)],
            'expected_cents' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000000'],
            'amount_varies' => ['sometimes', 'boolean'],
            'due_day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'autopay' => ['sometimes', 'boolean'],
        ];
    }
}
