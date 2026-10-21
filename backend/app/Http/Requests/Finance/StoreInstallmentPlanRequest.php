<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Enums\Visibility;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstallmentPlanRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:120'],
            'category_id' => ['bail', 'required', 'integer', $this->categoryOfKind(CategoryKind::Expense)],
            'card_id' => ['bail', 'required', 'integer', $this->activeCard()],
            'installments' => ['required', 'integer', 'between:2,48'],
            // Um dos dois: o total ("2.500 em 10x") ou o valor de cada parcela ("10x de 250").
            'total_cents' => ['required_without:installment_cents', 'prohibits:installment_cents', 'integer', 'min:1'],
            'installment_cents' => ['required_without:total_cents', 'integer', 'min:1'],
            'purchased_on' => ['required', 'date_format:Y-m-d'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'card_id.required' => __('finance.card_required'),
        ];
    }
}
