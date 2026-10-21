<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // O que a pessoa editou no card. Sem ele, vale o que o card mostrou. O conteúdo é
            // validado pela ferramenta, com as mesmas regras do app.
            'payload' => ['sometimes', 'array'],
        ];
    }
}
