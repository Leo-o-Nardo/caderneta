<?php

namespace App\Http\Requests\Households;

use Illuminate\Foundation\Http\FormRequest;

class SwitchHouseholdRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'household_id' => ['required', 'integer'],
        ];
    }
}
