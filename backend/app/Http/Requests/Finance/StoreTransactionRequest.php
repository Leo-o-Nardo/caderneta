<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Enums\Visibility;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CategoryKind::class)],
            // Até R$ 1 bilhão, só para barrar valores absurdos.
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'description' => ['required', 'string', 'max:120'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'category_id' => [
                'bail', 'required', 'integer',
                $this->categoryOfKind(CategoryKind::tryFrom((string) $this->input('kind'))),
            ],
            // Despesa precisa dizer como foi paga; receita não tem forma de pagamento.
            'payment_type' => ['required_if:kind,expense', 'prohibited_if:kind,income', 'nullable', Rule::enum(PaymentType::class)],
            'card_id' => ['bail', 'required_if:payment_type,credit_card', 'nullable', 'integer', $this->activeCard()],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            // "Repetir todo mês" (salário). Só receita: despesa que se repete é conta fixa.
            'repeat_monthly' => ['sometimes', 'boolean', 'declined_if:kind,expense'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_id.required_if' => __('finance.card_required'),
            'repeat_monthly.declined_if' => __('finance.repeat_income_only'),
        ];
    }
}
