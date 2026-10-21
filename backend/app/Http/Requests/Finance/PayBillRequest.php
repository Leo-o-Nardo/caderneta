<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentType;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tudo é opcional: um "Paguei" sem nada usa o mês em aberto, o valor esperado, hoje e débito. */
class PayBillRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'amount_cents' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'payment_type' => ['nullable', Rule::enum(PaymentType::class)],
            'card_id' => ['bail', 'required_if:payment_type,credit_card', 'nullable', 'integer', $this->activeCard()],
        ];
    }

    public function messages(): array
    {
        return [
            'card_id.required_if' => __('finance.card_required'),
        ];
    }
}
