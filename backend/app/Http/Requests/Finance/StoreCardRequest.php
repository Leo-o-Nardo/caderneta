<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'limit_cents' => ['nullable', 'integer', 'min:0'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
