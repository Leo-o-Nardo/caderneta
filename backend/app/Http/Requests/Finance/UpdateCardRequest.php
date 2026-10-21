<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:40'],
            'closing_day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'due_day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'limit_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
