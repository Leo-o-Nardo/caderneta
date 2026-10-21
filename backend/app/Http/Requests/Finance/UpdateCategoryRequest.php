<?php

namespace App\Http\Requests\Finance;

use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** O tipo não pode mudar: transformaria despesas antigas em receitas. */
class UpdateCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:40', $this->uniqueName(...)],
            'icon' => ['sometimes', 'required', Rule::in(Category::ICONS)],
            'color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var Category $category */
        $category = $this->route('category');

        if (is_string($value) && Category::nameTaken($category->kind, $value, $category->id)) {
            $fail(__('finance.category_name_taken'));
        }
    }
}
