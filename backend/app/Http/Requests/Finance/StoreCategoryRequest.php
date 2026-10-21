<?php

namespace App\Http\Requests\Finance;

use App\Enums\CategoryKind;
use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CategoryKind::class)],
            'name' => ['required', 'string', 'max:40', $this->uniqueName(...)],
            'icon' => ['required', Rule::in(Category::ICONS)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $kind = CategoryKind::tryFrom((string) $this->input('kind'));

        if ($kind && is_string($value) && Category::nameTaken($kind, $value)) {
            $fail(__('finance.category_name_taken'));
        }
    }
}
