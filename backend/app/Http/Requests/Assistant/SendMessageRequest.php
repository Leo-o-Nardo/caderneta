<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.config('assistant.max_chars')],
            'source' => ['nullable', Rule::in(['chat', 'home'])],
        ];
    }
}
