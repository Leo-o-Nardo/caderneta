<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Enums\Visibility;
use App\Http\Requests\Finance\Concerns\ValidatesHouseholdReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastrar uma receita fixa sem lançar nada junto (Agenda › Contas › Receitas fixas › Adicionar). */
class StoreRecurringIncomeRequest extends FormRequest
{
    use ValidatesHouseholdReferences;

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:120'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'category_id' => ['bail', 'required', 'integer', $this->categoryOfKind(CategoryKind::Income)],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }
}
