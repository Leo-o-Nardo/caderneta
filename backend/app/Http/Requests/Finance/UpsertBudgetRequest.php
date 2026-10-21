<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpsertBudgetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'limit_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
        ];
    }
}
