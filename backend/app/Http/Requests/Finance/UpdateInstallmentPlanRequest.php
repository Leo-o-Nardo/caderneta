<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Enums\Visibility;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valor, cartão e número de parcelas não mudam: para isso, apaga o parcelamento e lança de novo. */
class UpdateInstallmentPlanRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    /** Confere a permissão antes da validação: quem não pode editar recebe 403, e não erros de campo. */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('installment_plan'));
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:120'],
            'category_id' => ['sometimes', 'bail', 'required', 'integer', $this->categoryOfKind(CategoryKind::Expense)],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }
}
