<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Enums\Visibility;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Muda os próximos lançamentos da receita fixa. Os meses já lançados ficam como estão. */
class UpdateRecurringIncomeRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    /** Confere a permissão antes da validação: quem não pode editar recebe 403, e não erros de campo. */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recurring_income'));
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:120'],
            'amount_cents' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000000000'],
            'category_id' => ['sometimes', 'bail', 'required', 'integer', $this->categoryOfKind(CategoryKind::Income)],
            'day_of_month' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }
}
