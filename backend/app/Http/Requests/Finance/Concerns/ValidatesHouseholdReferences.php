<?php

namespace App\Http\Requests\Finance\Concerns;

use App\Enums\CategoryKind;
use App\Models\Card;
use App\Models\Category;
use Closure;

/**
 * Regras que conferem se a categoria e o cartão informados existem na casa atual. As consultas
 * passam pelo filtro automático da casa, então um id de outra casa simplesmente não é encontrado.
 */
trait ValidatesHouseholdReferences
{
    /** Categoria ativa da casa e do mesmo tipo do lançamento (despesa não usa categoria de receita). */
    protected function categoryOfKind(?CategoryKind $kind): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kind) {
            $category = Category::query()->active()->find($value);

            if (! $category) {
                $fail(__('finance.invalid_category'));
            } elseif ($kind && $category->kind !== $kind) {
                $fail(__('finance.category_kind_mismatch'));
            }
        };
    }

    protected function activeCard(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! Card::query()->active()->whereKey($value)->exists()) {
                $fail(__('finance.invalid_card'));
            }
        };
    }
}
