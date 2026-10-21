<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Enums\Visibility;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** O tipo (despesa ou receita) não muda: para isso, apaga e lança de novo. */
class UpdateTransactionRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    /** Confere a permissão antes da validação: quem não pode editar recebe 403, e não erros de campo. */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transaction'));
    }

    public function rules(): array
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');
        $isIncome = $transaction->kind === CategoryKind::Income;

        return [
            'amount_cents' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000000000'],
            'description' => ['sometimes', 'required', 'string', 'max:120'],
            'occurred_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'category_id' => ['sometimes', 'bail', 'required', 'integer', $this->categoryOfKind($transaction->kind)],
            'payment_type' => ['sometimes', $isIncome ? 'prohibited' : 'required', Rule::enum(PaymentType::class)],
            'card_id' => ['bail', 'required_if:payment_type,credit_card', 'nullable', 'integer', $this->activeCard()],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'card_id.required_if' => __('finance.card_required'),
        ];
    }
}
